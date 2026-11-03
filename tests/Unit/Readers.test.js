import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let ReaderCard;
let Readers;
let AdminDirectory;
let ReaderSearch;
let AllReaders;
let controls;

before(async () => {
    const controlId = '\0reader-test-controls';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'reader-controls-test', enforce: 'pre',
            resolveId(id, importer) {
                if (id === 'virtual:reader-controls') return controlId;
                if (importer?.endsWith('/ReaderCard.jsx') && id === '../Support/useFeedbackAction') return controlId;
            },
            load(id) {
                if (id !== controlId) return;
                return `export const calls = [];
                    export { Link } from '@inertiajs/react'; export const Head = () => null;
                    export const action = { processing: false, visit: (...args) => calls.push(args) };
                    export default function useFeedbackAction() { return action; }
                    export const form = { data: { q: '' }, errors: {}, processing: false, setData: (...args) => calls.push(args), get: (...args) => calls.push(args) };
                    export const useForm = () => form;`;
            },
            transform(code, id) {
                if (id.endsWith('/resources/js/Components/ReaderSearch.jsx') || id.includes('/resources/js/Pages/Readers/')) return code
                    .replace("from '@inertiajs/react'", "from 'virtual:reader-controls'");
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    controls = await server.ssrLoadModule('virtual:reader-controls');
    ({ default: ReaderCard } = await server.ssrLoadModule('/resources/js/Components/ReaderCard.jsx'));
    ({ default: Readers, AdminDirectory } = await server.ssrLoadModule('/resources/js/Pages/Readers/Index.jsx'));
    ({ default: ReaderSearch } = await server.ssrLoadModule('/resources/js/Components/ReaderSearch.jsx'));
    ({ default: AllReaders } = await server.ssrLoadModule('/resources/js/Pages/Readers/All.jsx'));
});
after(async () => { await server?.close(); });

const reader = { name: 'A Reader', username: 'reader', url: '/members/reader', initials: 'AR', avatar_url: null, followers_count: 1, reviews_count: 2, follow_url: '/members/reader/follow', unfollow_url: '/members/reader/follow', viewer_follows: false, follows_viewer: false, is_self: false, favorites: [{ id: 1, title: 'Earth', initials: 'EA', cover_url: null, url: '/literatures/earth' }] };
const routes = { index: '/readers', login: '/login', verification: null };
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}
const render = (changes = {}) => renderToStaticMarkup(React.createElement(ReaderCard, { reader: { ...reader, ...changes }, routes }));

test('reader cards link to real profiles and favorite literature with readable public counts and avatar fallback', () => {
    const html = render();
    assert.match(html, /View A Reader&#x27;s profile/);
    assert.match(html, /AR/);
    assert.match(html, /1 follower/);
    assert.match(html, /2 reviews/);
    assert.match(html, /href="\/literatures\/earth"/);
    assert.match(html, /aria-label="Follow A Reader"/);
    assert.doesNotMatch(html, /Follows you|>Following<|>Friends</);
    assert.match(render({ name: '<script>unsafe</script>' }), /&lt;script&gt;unsafe&lt;\/script&gt;/);
});

test('follow back and following reflect server relationships and submit the corresponding existing method', () => {
    controls.calls.length = 0;
    controls.action.processing = false;
    const follow = ReaderCard({ reader: { ...reader, follows_viewer: true }, routes });
    const followButton = elements(follow, 'button')[0];
    assert.equal(followButton.props['aria-label'], 'Follow back A Reader');
    followButton.props.onClick();
    assert.deepEqual(controls.calls[0], ['post', '/members/reader/follow']);
    const following = ReaderCard({ reader: { ...reader, viewer_follows: true }, routes });
    elements(following, 'button')[0].props.onClick();
    assert.deepEqual(controls.calls[1], ['delete', '/members/reader/follow']);
    assert.match(render({ viewer_follows: true }), />Following</);
    assert.match(render({ follows_viewer: true }), /Follows you/);
    assert.match(render({ viewer_follows: true, follows_viewer: true }), />Friends</);
});

test('a pending follow disables the control and announces busy state', () => {
    controls.action.processing = true;
    try {
        const button = elements(ReaderCard({ reader, routes }), 'button')[0];
        assert.equal(button.props.disabled, true);
        assert.equal(button.props['aria-busy'], true);
    } finally { controls.action.processing = false; }
});

test('self cards have no follow action and empty favorites have a descriptive fallback', () => {
    const html = render({ is_self: true, follow_url: null, favorites: [] });
    assert.doesNotMatch(html, /<button|to follow A Reader/);
    assert.match(html, /Your profile/);
    assert.match(html, /No favorite literature yet/);
});

test('guest follow opens the existing login panel while unverified follow retains the verification link', () => {
    const previousWindow = globalThis.window;
    const events = [];
    globalThis.window = { dispatchEvent: (event) => events.push(event.type) };
    try {
        const guest = ReaderCard({ reader: { ...reader, follow_url: null }, routes });
        const link = elements(guest, 'a')[0];
        let prevented = false;
        link.props.onClick({ preventDefault() { prevented = true; } });
        assert.equal(prevented, true);
        assert.deepEqual(events, ['literahaven:open-login']);
        const unverified = ReaderCard({ reader: { ...reader, follow_url: null }, routes: { ...routes, verification: '/email/verify' } });
        const verificationLink = elements(unverified, 'a')[0];
        assert.equal(verificationLink.props.href, '/email/verify');
        assert.equal(verificationLink.props.onClick, undefined);
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
    }
});

test('search submits to local Readers navigation and disables duplicate submissions while searching', () => {
    controls.calls.length = 0;
    controls.form.processing = false;
    const props = { query: '', action: routes.index };
    const form = elements(ReaderSearch(props), 'form')[0];
    let prevented = false;
    form.props.onSubmit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(controls.calls[0][0], '/readers');
    assert.equal(controls.calls[0][1].preserveScroll, true);
    controls.form.processing = true;
    try {
        elements(ReaderSearch(props), 'form')[0].props.onSubmit({ preventDefault() {} });
        assert.equal(controls.calls.length, 1);
    } finally { controls.form.processing = false; }
});

test('official directory cards show public lists and an official label without reading statistics or favorites', () => {
    const admin = { ...reader, is_official: true, lists_count: 1, favorites: undefined };
    const html = renderToStaticMarkup(React.createElement(AdminDirectory, { admins: { data: [admin], last_page: 1 }, routes }));
    assert.match(html, /Official Account/);
    assert.match(html, /Official/);
    assert.match(html, /1 list/);
    assert.doesNotMatch(html, /reviews|Favorite literature|No favorite literature/);
    assert.equal(AdminDirectory({ admins: { data: [] }, routes }), null);
});

test('compact reader rows show public metrics and Friends while reusing the existing follow action', () => {
    controls.calls.length = 0;
    const row = { ...reader, completed_count: 9, lists_count: 3, viewer_follows: true, follows_viewer: true };
    const html = renderToStaticMarkup(React.createElement(ReaderCard, { reader: row, routes, variant: 'row' }));
    assert.match(html, />Friends</);
    assert.match(html, /Completed/);
    assert.match(html, /Public lists/);
    assert.match(html, /Followers/);
    assert.doesNotMatch(html, /Favorite literature/);
    const tree = ReaderCard({ reader: row, routes, variant: 'row' });
    elements(tree, 'button')[0].props.onClick();
    assert.deepEqual(controls.calls[0], ['delete', '/members/reader/follow']);
});

test('the overview adds reader rows with View more and hides the link when there are no additional readers', () => {
    const row = { ...reader, completed_count: 1, lists_count: 0 };
    const props = { readers: { data: [], total: 0 }, directory: { data: [row], total: 25, more_url: '/readers/all?q=reader' }, query: '', routes };
    const html = renderToStaticMarkup(React.createElement(Readers, props));
    assert.match(html, /View more/);
    assert.match(html, /href="\/readers\/all\?q=reader"/);
    assert.match(html, /Most followed first/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(Readers, { ...props, directory: { ...props.directory, more_url: null } })), /View more/);
});

test('the full directory shows page navigation with real previous and next destinations', () => {
    const html = renderToStaticMarkup(React.createElement(AllReaders, {
        readers: { data: [{ ...reader, completed_count: 1, lists_count: 0 }], total: 45, current_page: 2, last_page: 3, prev_page_url: '/readers/all?q=reader&page=1', next_page_url: '/readers/all?q=reader&page=3' },
        query: 'reader', routes: { ...routes, index: '/readers/all', overview: '/readers' },
    }));
    assert.match(html, /Readers pagination/);
    assert.match(html, /Page 2 of 3/);
    assert.match(html, /href="\/readers\/all\?q=reader&amp;page=3"/);
    assert.match(html, /rel="next"/);
    assert.match(html, /Readers overview/);
});
