import assert from 'node:assert/strict';
import { test } from 'node:test';

import { createReviewDraftRepository, createReviewDraftSession, DRAFT_OWNER_KEY, installReviewDraftPrivacy } from '../../resources/js/Support/reviewDraft.js';

const context = (user = 1, work = 10, revision = 'a') => ({ user_id: user, work_key: `canonical:${work}`, revision: revision.repeat(64) });
const baseline = { body: '', rating: '', contains_spoiler: false, completed_at: '2026-10-25' };
class MemoryStorage {
    data = new Map();
    writes = 0;
    get length() { return this.data.size; }
    key(index) { return [...this.data.keys()][index] ?? null; }
    getItem(key) { return this.data.get(key) ?? null; }
    setItem(key, value) { this.writes++; this.data.set(key, String(value)); }
    removeItem(key) { this.data.delete(key); }
}
function setup({ storage = new MemoryStorage(), user = 1 } = {}) {
    let time = 1000000;
    const repository = createReviewDraftRepository({ storage, now: () => time });
    repository.activate(user);
    const tasks = new Map();
    let sequence = 0;
    const makeSession = () => {
        const applied = { values: null, state: null };
        const session = createReviewDraftSession({
            repository,
            apply: (values) => { applied.values = { ...values }; },
            onState: (state) => { applied.state = state; },
            schedule: (callback, delay) => { const id = ++sequence; tasks.set(id, { callback, delay }); return id; },
            cancel: (id) => tasks.delete(id),
        });
        return { session, applied };
    };
    return {
        storage, repository, tasks, makeSession,
        pauseTyping() { const pending = [...tasks.values()]; tasks.clear(); pending.forEach(({ callback }) => callback()); },
        advance(ms) { time += ms; },
    };
}

test('typing is debounced and stores only the latest review fields after a pause', () => {
    const app = setup();
    const { session, applied } = app.makeSession();
    session.start(context(), baseline);
    const initialWrites = app.storage.writes;
    session.change('body', 'N');
    session.change('body', 'Naruto');
    session.change('rating', 4.5);
    session.change('contains_spoiler', true);
    session.change('completed_at', '2026-10-24');

    assert.equal(app.storage.writes, initialWrites);
    assert.equal(app.tasks.size, 1);
    assert.equal([...app.tasks.values()][0].delay, 600);
    app.pauseTyping();
    assert.equal(app.storage.writes, initialWrites + 1);
    assert.deepEqual(app.repository.read(context()).values, { body: 'Naruto', rating: 4.5, contains_spoiler: true, completed_at: '2026-10-24' });
    assert.equal(applied.state.status, 'saved');
    session.dispose();
});

test('closing before the debounce flushes the draft and reopening another dialog recovers it', () => {
    const app = setup();
    const first = app.makeSession();
    first.session.start(context(), baseline);
    first.session.change('body', 'My unfinished review.');
    first.session.change('rating', 3.5);
    first.session.dispose();
    const second = app.makeSession();

    second.session.start(context(), baseline);

    assert.equal(second.applied.state.status, 'recovered');
    assert.equal(second.applied.values.body, 'My unfinished review.');
    assert.equal(second.applied.values.rating, 3.5);
    assert.equal(app.tasks.size, 0);
    second.session.dispose();
});

test('drafts for different canonical works do not overwrite each other', () => {
    const app = setup();
    const first = app.makeSession();
    first.session.start(context(), baseline);
    first.session.change('body', 'First work');
    first.session.dispose();
    const second = app.makeSession();
    second.session.start(context(1, 11), baseline);
    second.session.change('body', 'Second work');
    second.session.dispose();

    assert.equal(app.repository.read(context()).values.body, 'First work');
    assert.equal(app.repository.read(context(1, 11)).values.body, 'Second work');
    assert.equal(app.repository.read(context(2)), null);
});

test('a full browser reload preserves the same account draft', () => {
    const app = setup();
    app.repository.write(context(), { ...baseline, body: 'Recover after refreshing.' });
    const reloaded = createReviewDraftRepository({ storage: app.storage, now: () => 1000000 });
    reloaded.activate(1);
    let restored;
    const session = createReviewDraftSession({ repository: reloaded, apply: (values) => { restored = values; } });

    session.start(context(), baseline);

    assert.equal(session.state.status, 'recovered');
    assert.equal(restored.body, 'Recover after refreshing.');
    session.dispose();
});

test('discarding a conflicting draft keeps the latest published review', () => {
    const app = setup();
    app.repository.write(context(), { ...baseline, body: 'An old unfinished draft.' });
    const { session, applied } = app.makeSession();
    const fresh = { ...baseline, body: 'Latest server review.', rating: 5 };
    session.start(context(1, 10, 'b'), fresh);

    session.discard();
    session.dispose();

    assert.deepEqual(applied.values, fresh);
    assert.equal(app.repository.read(context()), null);
});

test('canonical IDs and unmapped literature IDs have separate namespaces', () => {
    const app = setup();
    const unmapped = { ...context(), work_key: 'literature:10' };
    app.repository.write(context(), { ...baseline, body: 'Canonical work.' });
    app.repository.write(unmapped, { ...baseline, body: 'Unmapped literature.' });

    assert.equal(app.repository.read(context()).values.body, 'Canonical work.');
    assert.equal(app.repository.read(unmapped).values.body, 'Unmapped literature.');
});

test('a changed server revision keeps current data and requires explicit draft restoration', () => {
    const app = setup();
    app.repository.write(context(), { ...baseline, body: 'Old local draft.', rating: 3 });
    const { session, applied } = app.makeSession();
    const fresh = { ...baseline, body: 'Latest published review.', rating: 5 };

    session.start(context(1, 10, 'b'), fresh);

    assert.equal(applied.state.status, 'conflict');
    assert.equal(applied.state.blocked, true);
    assert.deepEqual(applied.values, fresh);
    session.change('body', 'Cannot silently replace a conflict.');
    assert.deepEqual(applied.values, fresh);
    session.restore();
    app.pauseTyping();
    assert.equal(applied.values.body, 'Old local draft.');
    assert.equal(app.repository.read(context()).revision, 'b'.repeat(64));
    session.dispose();
});

test('an unconfirmed server version never automatically restores an old draft', () => {
    const app = setup();
    app.repository.write(context(), { ...baseline, body: 'Local draft.' });
    const { session, applied } = app.makeSession();

    session.start(context(), baseline, { trusted: false });

    assert.equal(applied.values.body, '');
    assert.equal(applied.state.status, 'conflict');
    session.dispose();
    assert.equal(app.repository.read(context()).values.body, 'Local draft.');
});

test('unconfirmed old page props cannot erase a draft just because its text matches them', () => {
    const app = setup();
    const oldPage = { ...baseline, body: 'Previously saved text.' };
    app.repository.write(context(), oldPage);
    const { session, applied } = app.makeSession();

    session.start(context(), oldPage, { trusted: false });

    assert.equal(applied.state.status, 'conflict');
    assert.equal(app.repository.read(context()).values.body, 'Previously saved text.');
    session.dispose();
});

test('manual discard removes a pending draft and resets the form to current server values', () => {
    const app = setup();
    const { session, applied } = app.makeSession();
    session.start(context(), baseline);
    session.change('body', 'Please discard this.');
    session.discard();
    app.pauseTyping();
    session.dispose();

    assert.equal(app.repository.read(context()), null);
    assert.deepEqual(applied.values, baseline);
    assert.equal(applied.state.status, 'discarded');
});

test('successful publication removes the draft and pending callbacks cannot recreate it', () => {
    const app = setup();
    const { session } = app.makeSession();
    session.start(context(), baseline);
    session.change('body', 'Now ready to publish.');
    session.flush();
    session.change('rating', 4);

    session.published();
    app.pauseTyping();
    session.dispose();

    assert.equal(app.repository.read(context()), null);
    assert.equal(app.tasks.size, 0);
});

test('a failed submission retains the last text for recovery', () => {
    const app = setup();
    const first = app.makeSession();
    first.session.start(context(), baseline);
    first.session.change('body', 'Keep this when validation or the network fails.');
    first.session.flush();
    first.session.dispose();
    const second = app.makeSession();

    second.session.start(context(), baseline);

    assert.equal(second.applied.values.body, 'Keep this when validation or the network fails.');
    second.session.dispose();
});

test('drafts already present on the server are cleaned without a conflict prompt', () => {
    const app = setup();
    const published = { ...baseline, body: 'Already saved.', rating: 4 };
    app.repository.write(context(), published);
    const { session, applied } = app.makeSession();

    session.start(context(1, 10, 'b'), published);

    assert.equal(app.repository.read(context()), null);
    assert.equal(applied.state.status, 'idle');
    assert.deepEqual(applied.values, published);
    session.dispose();
});

test('a newly selected star takes precedence while the recovered review text is preserved', () => {
    const app = setup();
    app.repository.write(context(), { ...baseline, body: 'Keep my words.', rating: 4 });
    const { session, applied } = app.makeSession();

    session.start(context(), baseline, { initialRating: 2.5 });
    app.pauseTyping();

    assert.equal(applied.values.body, 'Keep my words.');
    assert.equal(applied.values.rating, 2.5);
    assert.equal(app.repository.read(context()).values.rating, 2.5);
    session.dispose();
});

test('an idle dialog does not erase a newer draft written in another tab', () => {
    const app = setup();
    const { session } = app.makeSession();
    session.start(context(), baseline);
    app.repository.write(context(), { ...baseline, body: 'A newer draft from another tab.' });

    session.dispose();

    assert.equal(app.repository.read(context()).values.body, 'A newer draft from another tab.');
});

test('publication does not erase an unsaved edit made in another tab during submission', () => {
    const app = setup();
    const { session } = app.makeSession();
    session.start(context(), baseline);
    session.change('body', 'Submitted text.');
    session.flush();
    app.repository.write(context(), { ...baseline, body: 'Newer unpublished words.' });

    session.published();

    assert.equal(app.repository.read(context()).values.body, 'Newer unpublished words.');
    session.dispose();
});

test('logout clears all review drafts and cancels writers without removing unrelated storage', () => {
    const app = setup();
    app.storage.setItem('unrelated-preference', 'keep');
    const { session, applied } = app.makeSession();
    session.start(context(), baseline);
    session.change('body', 'Private pending draft.');

    app.repository.activate(null);
    app.pauseTyping();
    session.change('body', 'A stale tab must not save.');

    assert.equal(applied.state.status, 'account-changed');
    assert.equal(applied.values.body, '');
    assert.equal(app.repository.read(context()), null);
    assert.equal(app.storage.getItem('unrelated-preference'), 'keep');
    session.dispose();
});

test('switching accounts across tabs clears previous drafts and locks the stale editor', () => {
    const app = setup();
    const { session, applied } = app.makeSession();
    session.start(context(), baseline);
    session.change('body', 'Account one private text.');
    session.flush();
    const secondAccount = createReviewDraftRepository({ storage: app.storage });

    secondAccount.activate(2);
    app.repository.syncOwner();

    assert.equal(applied.state.blocked, true);
    assert.equal(applied.values.body, '');
    assert.equal(secondAccount.read(context()), null);
    assert.equal(secondAccount.read(context(2)), null);
    assert.equal(app.storage.length, 1);
    session.dispose();
});

test('storage restrictions do not prevent editing or falsely claim that the draft was saved', () => {
    const storage = new MemoryStorage();
    storage.setItem = () => { throw new Error('Quota exceeded.'); };
    const app = setup({ storage });
    const { session, applied } = app.makeSession();
    session.start(context(), baseline);
    session.change('body', 'I can still write my review.');

    app.pauseTyping();

    assert.equal(applied.values.body, 'I can still write my review.');
    assert.equal(applied.state.status, 'unavailable');
    assert.equal(applied.state.blocked, false);
    session.dispose();
});

test('corrupt, foreign, or expired records are removed rather than recovered', () => {
    const app = setup();
    app.repository.write(context(), baseline);
    const key = [...app.storage.data.keys()].find((item) => item !== DRAFT_OWNER_KEY);
    const record = JSON.parse(app.storage.getItem(key));
    app.storage.setItem(key, JSON.stringify({ ...record, user_id: 2 }));
    assert.equal(app.repository.read(context()), null);
    app.repository.write(context(), baseline);
    app.advance(31 * 24 * 60 * 60 * 1000);
    assert.equal(app.repository.read(context()), null);
    app.storage.setItem(key, '{bad json');
    assert.equal(app.repository.read(context()), null);
});

test('draft storage excludes credentials, emails, and unrelated form fields', () => {
    const app = setup();
    app.repository.write(context(), { ...baseline, body: 'Draft text.', email: 'private@example.com', _token: 'secret-csrf', password: 'do-not-store' });
    const record = app.repository.read(context());

    assert.deepEqual(Object.keys(record.values), ['body', 'rating', 'contains_spoiler', 'completed_at']);
    assert.equal(JSON.stringify(record).includes('secret-csrf'), false);
    assert.equal(JSON.stringify(record).includes('private@example.com'), false);
});

test('native and Inertia logout paths invoke privacy cleanup', () => {
    for (const native of [true, false]) {
        const app = setup();
        const surface = () => {
            const handlers = new Map();
            return { location: { origin: 'http://literahaven.test' }, addEventListener: (name, callback) => handlers.set(name, callback), removeEventListener: (name) => handlers.delete(name), fire: (name, event) => handlers.get(name)?.(event) };
        };
        const browserWindow = surface();
        const browserDocument = surface();
        const callbacks = new Map();
        const router = { on: (name, callback) => { callbacks.set(name, callback); return () => callbacks.delete(name); } };
        const cleanup = installReviewDraftPrivacy({ repository: app.repository, browserWindow, browserDocument, router, userId: 1, logoutUrl: '/logout' });
        app.repository.write(context(), { ...baseline, body: 'Private draft.' });

        if (native) browserDocument.fire('submit', { target: { tagName: 'FORM', action: 'http://literahaven.test/logout' } });
        else callbacks.get('before')({ detail: { visit: { url: new URL('http://literahaven.test/logout') } } });

        assert.equal(app.repository.userId, null);
        assert.equal(app.repository.read(context()), null);
        cleanup();
    }
});
