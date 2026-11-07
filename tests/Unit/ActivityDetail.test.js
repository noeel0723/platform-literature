import assert from 'node:assert/strict';
import { after, before, beforeEach, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, controls, Show, Actions, QuickLog;
before(async () => {
    const virtual = 'virtual:activity-detail-controls';
    server = await createServer({ configFile: false, plugins: [{
        name: 'activity-detail-test', enforce: 'pre',
        resolveId(id) { if (id === virtual) return `\0${virtual}`; },
        load(id) {
            if (id !== `\0${virtual}`) return;
            return `export { Link } from '@inertiajs/react';
                export const Head = () => null;
                export const useRef = () => ({ current: null });
                export const useEffect = () => {};
                export const state = [], requests = [], reloads = [], visits = [], feedback = [];
                export let fail = false;
                export const useState = (initial) => [initial, (value) => state.push(value)];
                export const router = { reload: (options) => reloads.push(options), visit: (...args) => visits.push(args) };
                export const actionFeedback = { begin: () => () => {}, publish: (value) => feedback.push(value), error: (value) => feedback.push(value) };
                export const ACTION_RESTORED_EVENT = 'action-restored';
                export const requestAction = async (...args) => { requests.push(args); if (fail) throw new Error('Network unavailable'); return { feedback: { id: 'ok', message: 'Saved' } }; };
                export const setFailure = () => { fail = true; };
                export const reset = () => { state.length = 0; requests.length = 0; reloads.length = 0; visits.length = 0; feedback.length = 0; fail = false; };`;
        },
        transform(code, id) {
            if (id.endsWith('/Pages/Activity/Show.jsx')) return code.replace("from '@inertiajs/react'", `from '${virtual}'`).replace("from 'react'", `from '${virtual}'`).replace("from '../../Support/actionFeedback'", `from '${virtual}'`);
            if (id.endsWith('/Components/QuickLogDialog.jsx')) return 'export default function QuickLog() { return null; }';
        },
    }, react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    controls = await server.ssrLoadModule(virtual);
    ({ default: Show } = await server.ssrLoadModule('/resources/js/Pages/Activity/Show.jsx'));
    ({ default: Actions } = await server.ssrLoadModule('/resources/js/Components/ActivityLiteratureActions.jsx'));
    ({ default: QuickLog } = await server.ssrLoadModule('/resources/js/Components/QuickLogDialog.jsx'));
});
after(async () => { await server?.close(); });
beforeEach(() => controls.reset());
const props = {
    activity: { id: 1, type: 'reviewed', label: 'Review', user: { name: 'A Reader', url: '/members/a-reader', initial: 'A', avatar_url: null },
        is_owner: false, occurred_at: '2026-11-07T01:00:00Z', completed_at: '2026-11-01', rating: 4.5,
        body: 'Full review content.\nSecond paragraph.', contains_spoiler: false, report: null, discussion_title: null, discussion_url: null,
        review: { is_liked: false, likes_count: 3, like_url: '/reviews/1/like', unlike_url: '/reviews/1/like' } },
    literature: { id: 1, title: 'A Shared Story', year: 1999, author: 'A Writer', type_label: 'Novel', url: '/literatures/story', cover_url: '/cover.jpg' },
    preview: { csrf_token: 'test-token', viewer: { reading_status: 'completed' }, literature: { id: 1, review: { rating: 3, body: 'My own review' }, review_url: '/literatures/story/review?activity=1' },
        routes: { reading_update: '/reading', reading_destroy: '/reading', create_list: '/lists/create', quick_log_search: '/quick-log', where_to_read: null },
        lists: [{ id: 1, title: 'Private favorites', is_private: true, contains: false, store_url: '/lists/1/items', destroy_url: '/lists/1/items/2' }] },
    routes: { feed: '/activity', own_activity: '/activity?scope=you', actor_activity: null, report: '/reports' }, reportReasons: {},
};
const render = (input = props) => renderToStaticMarkup(React.createElement(Show, input));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}
const findActions = (tree) => elements(tree, Actions)[0];

test('dedicated activity displays reviewer, full body, date and a separate literature link', () => {
    const html = render();
    assert.match(html, /Review by/);
    assert.match(html, /A Shared Story/);
    assert.match(html, /Full review content\.\nSecond paragraph/);
    assert.match(html, /Completed on/);
    assert.match(html, /4.5 \/ 5/);
    assert.match(html, /View literature details/);
    assert.match(html, /Your literature actions/);
    assert.doesNotMatch(html, /Clone|Trailer|Watchlist/);
});

test('spoiler content stays hidden until explicitly revealed', () => {
    const html = render({ ...props, activity: { ...props.activity, contains_spoiler: true } });
    assert.match(html, /Reveal spoiler review/);
    assert.doesNotMatch(html, /Full review content/);
});

test('like uses JSON and reloads current activity with scroll preserved; duplicate clicks are blocked', async () => {
    const tree = Show(props);
    const like = elements(tree, 'button').find((button) => button.props['aria-pressed'] === false);
    await like.props.onClick();
    await like.props.onClick();
    assert.equal(controls.requests.length, 1);
    assert.deepEqual(controls.requests[0].slice(0, 3), ['/reviews/1/like', 'POST', {}]);
    assert.equal(controls.requests[0][3].csrfToken, 'test-token');
    assert.equal(controls.reloads[0].preserveScroll, true);
    assert.deepEqual(controls.reloads[0].only, ['activity', 'preview']);
    assert.equal(controls.visits.length, 0);
    controls.reloads[0].onFinish();
});

test('readlist and custom-list controls call the existing backend endpoints', async () => {
    let tree = Show(props);
    await findActions(tree).props.onReading('want_to_read');
    assert.deepEqual(controls.requests[0].slice(0, 3), ['/reading', 'PUT', { status: 'want_to_read' }]);
    controls.reloads[0].onFinish();
    await findActions(tree).props.onList(props.preview.lists[0]);
    assert.deepEqual(controls.requests[1].slice(0, 3), ['/lists/1/items', 'POST', { literature_id: 1 }]);
    controls.reloads[1].onFinish();
});

test('removing the current readers completed activity returns to the feed instead of a deleted URL', async () => {
    const input = { ...props, activity: { ...props.activity, type: 'completed', is_owner: true, review: null } };
    const tree = Show(input);
    await findActions(tree).props.onReading('completed');
    assert.deepEqual(controls.requests[0].slice(0, 3), ['/reading', 'DELETE', {}]);
    assert.equal(controls.visits[0][0], '/activity');
    controls.visits[0][1].onFinish();
});

test('network failures show feedback without navigating and allow retry', async () => {
    controls.setFailure();
    const tree = Show(props);
    await findActions(tree).props.onReading('want_to_read');
    assert.ok(controls.feedback.includes('Network unavailable'));
    assert.equal(controls.visits.length, 0);
    assert.equal(controls.reloads.length, 0);
    await findActions(tree).props.onReading('want_to_read');
    assert.equal(controls.requests.length, 2);
});

test('review and rating actions open the existing Quick Log with the viewers own data', () => {
    const tree = Show(props);
    findActions(tree).props.onReview(2.5);
    assert.deepEqual(controls.state.at(-1), { rating: 2.5 });
    const dialog = elements(tree, QuickLog)[0];
    assert.equal(dialog.props.initialLiterature.review.body, 'My own review');
    assert.match(dialog.props.initialLiterature.review_url, /activity=1/);
    const html = render({ ...props, activity: { ...props.activity, type: 'completed', label: 'Completed', body: null, review: null } });
    assert.doesNotMatch(html, /Like review/);
    assert.match(html, /Marked as completed/);
});
