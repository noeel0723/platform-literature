import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let ReaderCard;
let Readers;
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
                    export const action = { processing: false, visit: (...args) => calls.push(args) };
                    export default function useFeedbackAction() { return action; }
                    export const form = { data: { q: '' }, errors: {}, processing: false, setData: (...args) => calls.push(args), get: (...args) => calls.push(args) };
                    export const useForm = () => form;`;
            },
            transform(code, id) {
                if (id.endsWith('/resources/js/Pages/Readers/Index.jsx')) return code
                    .replace("import { Head, Link, useForm } from '@inertiajs/react';", "import { Head, Link } from '@inertiajs/react'; import { useForm } from 'virtual:reader-controls';");
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    controls = await server.ssrLoadModule('virtual:reader-controls');
    ({ default: ReaderCard } = await server.ssrLoadModule('/resources/js/Components/ReaderCard.jsx'));
    ({ default: Readers } = await server.ssrLoadModule('/resources/js/Pages/Readers/Index.jsx'));
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
    assert.doesNotMatch(html, /Follows you|✓ Following/);
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
    assert.match(render({ viewer_follows: true }), /✓ Following/);
    assert.match(render({ follows_viewer: true }), /Follows you/);
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
    const props = { readers: { data: [], total: 0, last_page: 1 }, query: '', routes };
    const form = elements(Readers(props), 'form')[0];
    let prevented = false;
    form.props.onSubmit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(controls.calls[0][0], '/readers');
    assert.equal(controls.calls[0][1].preserveScroll, true);
    controls.form.processing = true;
    try {
        elements(Readers(props), 'form')[0].props.onSubmit({ preventDefault() {} });
        assert.equal(controls.calls.length, 1);
    } finally { controls.form.processing = false; }
});
