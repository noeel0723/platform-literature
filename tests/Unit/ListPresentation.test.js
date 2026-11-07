import assert from 'node:assert/strict';
import { after, before, beforeEach, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, Show, ListActions, ListBanner;
const controls = { processing: false, calls: [], reset() { this.processing = false; this.calls = []; } };
globalThis.__listPresentationControls = controls;

before(async () => {
    const virtual = 'virtual:list-presentation-inertia';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'list-presentation-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) { if (id === `\0${virtual}`) return `export { Link } from '@inertiajs/react'; export const Head = () => null; export const usePage = () => ({ props: {} });`; },
            transform(code, id) {
                if (id.endsWith('/Support/useFeedbackAction.js')) return `export default () => ({ processing: globalThis.__listPresentationControls.processing, visit: (...args) => globalThis.__listPresentationControls.calls.push(args) });`;
                if (id.endsWith('/Components/ListComments.jsx')) return `export default () => 'Existing comments';`;
                if (id.endsWith('/Pages/Lists/Show.jsx')) return code.replace("from '@inertiajs/react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    ({ default: Show } = await server.ssrLoadModule('/resources/js/Pages/Lists/Show.jsx'));
    ({ default: ListActions } = await server.ssrLoadModule('/resources/js/Components/ListActions.jsx'));
    ({ default: ListBanner } = await server.ssrLoadModule('/resources/js/Components/ListBanner.jsx'));
});
after(async () => { await server?.close(); delete globalThis.__listPresentationControls; });
beforeEach(() => controls.reset());

const owner = { name: 'Literahaven', initials: 'LH', url: '/members/literahaven', avatar_url: null };
const literature = { id: 1, title: 'First story', year: 1999, url: '/literatures/first', cover_url: '/first.jpg', backdrop_url: '/backdrop.jpg' };
const list = { title: 'A reading list', description: 'Stories worth reading.', owner, is_ranked: true, is_official: true, is_private: false,
    hero: literature, likes_count: 4, is_liked: false, like_url: '/lists/stories/like', unlike_url: '/lists/stories/like',
    progress: { completed: 1, total: 4, percentage: 25 }, edit_url: '/lists/stories/edit', delete_url: '/lists/stories', reorder_url: '/lists/stories/reorder',
    items: { total: 4, current_page: 2, last_page: 2, prev_page_url: '/lists/stories?page=1', next_page_url: null,
        data: [{ id: 25, position: 25, literature, completed: true, remove_url: '/lists/stories/items/25' }] },
};
const props = { list, canEdit: false, comments: { total: 3 }, commenting: { authenticated: true } };
const render = (Component, input) => renderToStaticMarkup(React.createElement(Component, input));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('detail hierarchy keeps owner and title before covers, rank below cover, comments after contents', () => {
    const html = render(Show, props);
    assert.ok(html.indexOf('List by') < html.indexOf('<h1'));
    assert.ok(html.indexOf('<h1') < html.indexOf('Literature in list order'));
    assert.ok(html.indexOf('Cover of First story') < html.indexOf('Position 25'));
    assert.ok(html.indexOf('Position 25') < html.indexOf('Existing comments'));
    assert.match(html, /Official/);
    assert.match(html, /aria-label="Completed"/);
    assert.match(html, /Page 2 of 2/);
    assert.doesNotMatch(html, /Move First story|Remove<|Delete list/);
});

test('banner uses backdrop and attribution, falls back to blurred first cover and omits empty list banner', () => {
    const html = render(ListBanner, { literature });
    assert.match(html, /src="\/backdrop.jpg"/);
    assert.match(html, /First story \(1999\)/);
    assert.match(html, /fetchPriority="high"/i);
    assert.match(render(ListBanner, { literature: { ...literature, backdrop_url: null } }), /blur-sm/);
    assert.equal(render(ListBanner, { literature: null }), '');
});

test('progress exposes real completed count and accessible percentage, including zero', () => {
    const html = render(ListActions, { list });
    assert.match(html, /1 of 4 literature/);
    assert.match(html, /role="progressbar"/);
    assert.match(html, /aria-valuenow="25"/);
    assert.match(html, /width:25%/);
    assert.match(render(ListActions, { list: { ...list, progress: { completed: 0, total: 0, percentage: 0 } } }), /aria-valuenow="0"/);
});

test('like and unlike call backend methods and pending action is disabled', () => {
    for (const liked of [false, true]) {
        const tree = Show({ ...props, list: { ...list, is_liked: liked } });
        const actions = elements(tree, ListActions)[0];
        actions.props.onLike();
        assert.deepEqual(controls.calls.at(-1), [liked ? 'delete' : 'post', '/lists/stories/like']);
    }
    const html = render(ListActions, { list: { ...list, is_liked: true }, commenting: { authenticated: true }, processing: true });
    assert.match(html, /aria-pressed="true"/);
    assert.match(html, /aria-busy="true" disabled/);
});

test('guests open login, unverified readers get verification and blocked readers have disabled action', () => {
    const previousWindow = globalThis.window;
    const events = [];
    globalThis.window = { location: new URL('http://literahaven.test/lists/stories'), dispatchEvent: (event) => events.push(event.type) };
    try {
        const guestList = { ...list, like_url: null, progress: null };
        const tree = ListActions({ list: guestList, commenting: { authenticated: false } });
        const buttons = elements(tree, 'button');
        buttons[0].props.onClick();
        buttons[1].props.onClick();
        assert.deepEqual(events, ['literahaven:open-login', 'literahaven:open-login']);
        const guest = render(ListActions, { list: guestList });
        assert.doesNotMatch(guest, /role="progressbar"/);
        assert.match(guest, /Log in to track your reading progress/);
        assert.match(render(ListActions, { list, commenting: { authenticated: true, verification_url: '/email/verify' } }), /href="\/email\/verify"/);
        const blocked = ListActions({ list: { ...list, like_url: null }, commenting: { authenticated: true } });
        assert.equal(elements(blocked, 'button')[0].props.disabled, true);
    } finally { globalThis.window = previousWindow; }
});

test('owner management keeps reorder and remove functional and unranked lists omit positions', () => {
    const twoItems = [{ ...list.items.data[0] }, { ...list.items.data[0], id: 26, position: 26 }];
    const tree = Show({ ...props, canEdit: true, list: { ...list, items: { ...list.items, data: twoItems } } });
    const buttons = elements(tree, 'button');
    buttons.find((button) => button.props['aria-label'] === 'Move First story later').props.onClick();
    assert.deepEqual(controls.calls[0], ['patch', list.reorder_url, { item_ids: [26, 25] }]);
    buttons.find((button) => button.props.children === 'Remove').props.onClick();
    assert.deepEqual(controls.calls[1], ['delete', '/lists/stories/items/25']);
    const html = render(Show, { ...props, list: { ...list, is_ranked: false } });
    assert.doesNotMatch(html, /aria-label="Position/);
});
