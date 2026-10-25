import assert from 'node:assert/strict';
import { test } from 'node:test';

import { CONNECTION_MESSAGE, actionErrorMessage, createFeedbackStore, feedbackVisitOptions, requestAction } from '../../resources/js/Support/actionFeedback.js';

const feedback = (id = 'saved') => ({ id, message: 'Added to your Readlist.', scope: 'reading:1', undo: { url: '/action-feedback/token/undo', expires_at: new Date(Date.now() + 90000).toISOString() } });
const response = (status, payload, extra = {}) => ({ status, ok: status >= 200 && status < 300, json: async () => payload, ...extra });

test('publishes a single contextual toast and ignores repeated Inertia flash data', () => {
    const store = createFeedbackStore();
    let updates = 0;
    const unsubscribe = store.subscribe(() => { updates++; });
    store.publish(feedback());
    store.publish(feedback());

    assert.equal(updates, 1);
    assert.equal(store.getSnapshot().toast.message, 'Added to your Readlist.');
    store.dismiss('saved');
    store.publish(feedback());
    assert.equal(store.getSnapshot().toast, null);
    unsubscribe();
});

test('undo calls the provided backend URL before refreshing saved data', async () => {
    const calls = [];
    const store = createFeedbackStore({
        undoRequest: async (url) => { calls.push(url); return { feedback: { id: 'undone', message: 'Action undone.' } }; },
        refresh: async (scope) => { calls.push(scope); },
    });
    store.publish(feedback());

    await store.undo();

    assert.deepEqual(calls, ['/action-feedback/token/undo', 'reading:1']);
    assert.equal(store.getSnapshot().toast.message, 'Action undone.');
    assert.equal(store.getSnapshot().toast.undo, null);
});

test('rapid duplicate Undo clicks issue only one backend request and block new submissions', async () => {
    let resolveRequest;
    let count = 0;
    const store = createFeedbackStore({ undoRequest: () => { count++; return new Promise((resolve) => { resolveRequest = resolve; }); } });
    store.publish(feedback());
    const first = store.undo();
    const second = store.undo();

    assert.equal(store.getSnapshot().toast.busy, true);
    assert.equal(store.begin(), null);
    assert.equal(count, 1);
    resolveRequest({ feedback: { id: 'undone', message: 'Action undone.' } });
    await Promise.all([first, second]);
    assert.equal(store.getSnapshot().toast.busy, false);
});

test('pending submissions disable Undo until they finish and release only once', async () => {
    let calls = 0;
    const store = createFeedbackStore({ undoRequest: async () => { calls++; return { feedback: { id: 'undone', message: 'Action undone.' } }; } });
    store.publish(feedback());
    const finish = store.begin();
    await store.undo();
    assert.equal(calls, 0);
    finish();
    finish();
    assert.equal(store.getSnapshot().pending, 0);
    await store.undo();
    assert.equal(calls, 1);
});

test('failed Undo does not claim success or refresh frontend data', async () => {
    let refreshed = false;
    const store = createFeedbackStore({ undoRequest: async () => { throw new Error('This item has changed. Please refresh.'); }, refresh: () => { refreshed = true; } });
    store.publish(feedback());

    await store.undo();

    assert.equal(refreshed, false);
    assert.equal(store.getSnapshot().toast.tone, 'error');
    assert.equal(store.getSnapshot().toast.message, 'This item has changed. Please refresh.');
    assert.equal(store.getSnapshot().toast.undo, null);
    assert.equal(store.getSnapshot().toast.busy, false);
});

test('refresh failure after successful Undo reports the actual backend outcome', async () => {
    const store = createFeedbackStore({
        undoRequest: async () => ({ feedback: { id: 'undone', message: 'Action undone.' } }),
        refresh: async () => { throw new Error('offline'); },
    });
    store.publish(feedback());

    await store.undo();

    assert.equal(store.getSnapshot().toast.message, 'Action undone. Refresh the page to see the updated details.');
});

test('expired or non-reversible feedback does not offer Undo', () => {
    const store = createFeedbackStore();
    store.publish({ ...feedback(), undo: { url: '/undo', expires_at: '2000-01-01T00:00:00Z' } });
    assert.equal(store.getSnapshot().toast.undo, null);
    store.publish({ id: 'saved-profile', message: 'Profile saved.' });
    assert.equal(store.getSnapshot().toast.undo, null);
});

test('Inertia callbacks show validation and network feedback without browser error popups', () => {
    const store = createFeedbackStore();
    let successes = 0;
    const options = feedbackVisitOptions({ onSuccess: () => { successes++; } }, store);
    options.onSuccess({ props: { actionFeedback: feedback() } });
    assert.equal(successes, 1);
    assert.equal(store.getSnapshot().toast.id, 'saved');
    options.onError({ status: 'Please choose a valid reading status.' });
    assert.equal(store.getSnapshot().toast.message, 'Please choose a valid reading status.');
    assert.equal(options.onNetworkError(new Error('offline')), false);
    assert.equal(store.getSnapshot().toast.message, CONNECTION_MESSAGE);
    assert.equal(options.onHttpException({ status: 500, data: '<html>stack trace</html>' }), false);
    assert.equal(store.getSnapshot().toast.message, 'The change could not be saved. Please try again in a moment.');
});

test('mutation transport sends JSON with CSRF protection and same-origin credentials', async () => {
    let config;
    const payload = await requestAction('/reading-list', 'PUT', { status: 'completed' }, {
        csrfToken: 'test-csrf', fetcher: async (url, options) => { assert.equal(url, '/reading-list'); config = options; return response(200, { feedback: feedback() }); },
    });

    assert.equal(config.method, 'PUT');
    assert.equal(config.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(config.credentials, 'same-origin');
    assert.deepEqual(JSON.parse(config.body), { status: 'completed' });
    assert.equal(payload.feedback.id, 'saved');
});

test('network and malformed responses explain that persisted state must be checked', async () => {
    await assert.rejects(requestAction('/undo', 'POST', {}, { fetcher: async () => { throw new TypeError('Failed to fetch'); } }), { message: CONNECTION_MESSAGE });
    await assert.rejects(requestAction('/undo', 'POST', {}, { fetcher: async () => response(200, {}) }), { message: CONNECTION_MESSAGE });
});

test('server failures and expired sessions become readable errors', async () => {
    await assert.rejects(requestAction('/undo', 'POST', {}, { fetcher: async () => response(409, { message: 'This item has changed.' }) }), { message: 'This item has changed.' });
    await assert.rejects(requestAction('/undo', 'POST', {}, { fetcher: async () => response(200, {}, { redirected: true }) }), { message: 'Your session has expired. Refresh the page and sign in again.' });
    assert.equal(actionErrorMessage(422, { errors: { title: ['Choose a title.'] } }), 'Choose a title.');
    assert.equal(actionErrorMessage(500, { message: 'Internal database details' }), 'The change could not be saved. Please try again in a moment.');
});
