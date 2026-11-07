import assert from 'node:assert/strict';
import { after, before, beforeEach, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, controls, ListComments;
before(async () => {
    const virtual = 'virtual:list-comments-form';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'list-comments-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id !== `\0${virtual}`) return;
                return `export { Link } from '@inertiajs/react';
                    export const posts = [], deletes = [], resets = [];
                    export let deleting = false;
                    export const form = { data: { body: '' }, errors: {}, processing: false,
                        post: (...args) => posts.push(args), reset: (...fields) => { resets.push(fields); form.data.body = ''; },
                        setData: (key, value) => { form.data[key] = value; }, setError: (key, value) => { form.errors[key] = value; } };
                    export const reset = () => { posts.length = 0; deletes.length = 0; resets.length = 0; deleting = false; form.data.body = 'A useful list.'; form.errors = {}; form.processing = false; };
                    export const useForm = () => form;
                    export default () => ({ processing: deleting, visit: (...args) => deletes.push(args) });`;
            },
            transform(code, id) {
                if (id.endsWith('/Components/ListComments.jsx')) return code
                    .replace("from '@inertiajs/react'", `from '${virtual}'`)
                    .replace("from '../Support/useFeedbackAction'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    controls = await server.ssrLoadModule(virtual);
    ({ default: ListComments } = await server.ssrLoadModule('/resources/js/Components/ListComments.jsx'));
});
after(async () => { await server?.close(); });
beforeEach(() => { controls.reset(); });

const row = { id: 1, body: 'A useful list.\nThanks for sharing.', created_at: '2026-11-07T00:00:00Z', user: { name: 'A Reader', url: '/members/a-reader', initials: 'AR', avatar_url: null }, delete_url: null };
const props = { comments: { data: [row], total: 1, last_page: 1, current_page: 1 }, commenting: { authenticated: true, store_url: '/lists/favorites/comments', verification_url: null } };
const render = (input = props) => renderToStaticMarkup(React.createElement(ListComments, input));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('comment rows expose reader links, timestamps, readable text and a correctly labeled composer', () => {
    const html = render();
    assert.match(html, /1 comment<\/h2>/);
    assert.match(html, /href="\/members\/a-reader"/);
    assert.match(html, /dateTime="2026-11-07T00:00:00Z"/i);
    assert.match(html, /A useful list\.\nThanks for sharing\./);
    assert.match(html, /for="list-comment-body"/);
    assert.match(html, /max[Ll]ength="3000"/);
    assert.match(html, /Post comment/);
    assert.doesNotMatch(html, />Delete<\/button>/);
});

test('posting preserves scroll and clears the draft only after success', () => {
    const tree = ListComments(props);
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    const [url, options] = controls.posts[0];
    assert.equal(url, '/lists/favorites/comments');
    assert.equal(options.preserveScroll, true);
    assert.equal(controls.form.data.body, 'A useful list.');
    options.onSuccess();
    assert.deepEqual(controls.resets, [['body']]);
    assert.equal(controls.form.data.body, '');
});

test('network failure keeps the draft and displays a field-associated error', () => {
    elements(ListComments(props), 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(controls.posts[0][1].onNetworkError(), false);
    assert.equal(controls.form.data.body, 'A useful list.');
    const html = render();
    assert.match(html, /aria-describedby="list-comment-error"/);
    assert.match(html, /role="alert"/);
    assert.match(html, /Your comment could not be posted/);
});

test('in-flight submissions prevent repeats and disable comment deletion', () => {
    controls.form.processing = true;
    const tree = ListComments({ ...props, comments: { ...props.comments, data: [{ ...row, delete_url: '/lists/favorites/comments/1' }] } });
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(controls.posts.length, 0);
    assert.equal(elements(tree, 'button').every((button) => button.props.disabled === true), true);
    assert.match(renderToStaticMarkup(tree), /Posting…/);
});

test('deletion uses only the server-provided URL', () => {
    const tree = ListComments({ ...props, comments: { ...props.comments, data: [{ ...row, delete_url: '/lists/favorites/comments/1' }] } });
    elements(tree, 'button').find((button) => button.props['aria-label'] === 'Delete comment by A Reader').props.onClick();
    assert.deepEqual(controls.deletes, [['delete', '/lists/favorites/comments/1']]);
});

test('guests, unverified readers and restricted accounts see the appropriate action without a composer', () => {
    const previousWindow = globalThis.window;
    const events = [];
    globalThis.window = { location: new URL('http://literahaven.test/lists/favorites'), dispatchEvent: (event) => events.push(event.type) };
    try {
        const guest = ListComments({ ...props, commenting: { authenticated: false } });
        elements(guest, 'button')[0].props.onClick();
        assert.deepEqual(events, ['literahaven:open-login']);
        assert.equal(elements(guest, 'form').length, 0);
        const unverified = render({ ...props, commenting: { authenticated: true, verification_url: '/email/verify' } });
        assert.match(unverified, /href="\/email\/verify"/);
        assert.doesNotMatch(unverified, /<textarea/);
        assert.match(render({ ...props, commenting: { authenticated: true } }), /Commenting is unavailable/);
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
    }
});

test('empty states and server pagination remain compact and comment text is escaped', () => {
    assert.match(render({ ...props, comments: { ...props.comments, data: [], total: 0 } }), /No comments yet/);
    const html = render({ ...props, comments: { ...props.comments, data: [{ ...row, body: '<script>alert(1)</script>' }], total: 21, last_page: 2, next_page_url: '/lists/favorites?comments_page=2#comments', prev_page_url: null } });
    assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /aria-label="List comments pagination"/);
    assert.match(html, /href="\/lists\/favorites\?comments_page=2#comments"/);
});
