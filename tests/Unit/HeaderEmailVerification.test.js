import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import React from 'react';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let bindings;
let AppHeader;
let Search;

before(async () => {
    const moduleId = 'virtual:header-verification-hooks';
    const quickLogId = '\0header-verification-quick-log';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'header-verification-test', enforce: 'pre',
            resolveId(id, importer) {
                if (id === moduleId) return `\0${moduleId}`;
                if (importer?.endsWith('/resources/js/Components/AppHeader.jsx') && id === './QuickLogDialog') return quickLogId;
            },
            load(id) {
                if (id === quickLogId) return 'export default function QuickLogDialog() { return null; }';
                if (id !== `\0${moduleId}`) return;
                return `export const effects = [], updates = [];
                    let stateIndex = 0, mobileOpen = false;
                    export const reset = (mobile = false) => { stateIndex = 0; mobileOpen = mobile; effects.length = 0; updates.length = 0; };
                    export const useState = (initial) => [stateIndex++ === 0 ? mobileOpen : typeof initial === 'function' ? initial() : initial, (value) => updates.push(value)];
                    export const useEffect = (callback) => effects.push(callback);
                    export const useRef = (value) => ({ current: value });`;
            },
            transform(code, id) {
                if (id.endsWith('/resources/js/Components/AppHeader.jsx')) return code.replace("from 'react'", `from '${moduleId}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    bindings = await server.ssrLoadModule(moduleId);
    ({ default: AppHeader, Search } = await server.ssrLoadModule('/resources/js/Components/AppHeader.jsx'));
});

after(async () => { await server?.close(); });

test('global search expands before submitting and keeps the existing GET query contract', () => {
    bindings.reset(false);
    const tree = Search({ action: '/search', initialQuery: 'haikyu' });
    let prevented = false;
    tree.props.onSubmit({ preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.deepEqual(bindings.updates, [true]);
    assert.equal(tree.props.action, '/search');
    assert.equal(tree.props.method, 'GET');
    const input = elements(tree, 'input')[0];
    assert.equal(input.props.name, 'q');
    assert.equal(input.props.defaultValue, 'haikyu');
    assert.equal(input.props.tabIndex, -1);
});

test('expanded search focuses the field, prevents empty submissions, and restores button focus on Escape', () => {
    bindings.reset(true);
    const tree = Search({ action: '/search', initialQuery: 'haikyu' });
    const input = elements(tree, 'input')[0];
    let focused = 0, prevented = 0;
    input.props.ref.current = { value: ' ', focus: () => { focused++; } };
    bindings.effects[0]();
    assert.equal(focused, 1);
    assert.equal(input.props.tabIndex, 0);
    assert.equal(input.props['aria-hidden'], false);
    tree.props.onSubmit({ preventDefault: () => { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal(focused, 2);
    input.props.ref.current.value = 'haikyu';
    tree.props.onSubmit({ preventDefault: () => { prevented++; } });
    assert.equal(prevented, 1);
    tree.props.onKeyDown({ key: 'Escape', currentTarget: { querySelector: () => ({ focus: () => { focused++; } }) } });
    assert.equal(bindings.updates.at(-1), false);
    assert.equal(focused, 3);
});

function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

function withHeader(required, callback, { mobileOpen = false, pathname = '/', home = '/' } = {}) {
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;
    const redirects = [];
    const listeners = {};
    globalThis.window = { location: { origin: 'http://literahaven.test', pathname, search: '', assign: (url) => redirects.push(url) }, addEventListener() {}, removeEventListener() {} };
    globalThis.document = { addEventListener: (name, callback) => { listeners[name] = callback; }, removeEventListener() {} };
    bindings.reset(mobileOpen);
    try {
        const tree = AppHeader({
            user: { name: 'Unverified reader', username: 'reader', email_verified: false, requires_email_verification: required, initials: 'UR', navigation: [{ label: 'Home', url: home }, { label: 'Profile', url: '/members/reader' }], edit_url: '/profile/edit', logout_url: '/logout' },
            routes: { home, literature: '/literature', lists: '/lists', readers: '/readers', catalog: '/catalog', search: '/search', verification: '/email/verify', quick_log_search: '/quick-log/literatures' },
            logo_url: '/literahaven-logo.png',
        });
        callback({ tree, redirects, listeners });
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
        if (previousDocument === undefined) delete globalThis.document; else globalThis.document = previousDocument;
    }
}

test('the header hides the verification warning when verification is not required even for an unverified email', () => {
    withHeader(false, ({ tree }) => assert.doesNotMatch(renderToStaticMarkup(tree), /Verify your email to use interactive features/));
    withHeader(true, ({ tree }) => assert.match(renderToStaticMarkup(tree), /Verify your email to use interactive features/));
});

test('Quick Log opens normally when verification is disabled instead of redirecting an unverified user', () => {
    withHeader(false, ({ tree, redirects }) => {
        elements(tree, 'button').find((button) => button.props['aria-label'] === 'Log a rating or review').props.onClick();
        assert.deepEqual(redirects, []);
        assert.deepEqual(bindings.updates, [true]);
    });
});

test('Quick Log still redirects to verification when the server requires it', () => {
    withHeader(true, ({ tree, redirects }) => {
        elements(tree, 'button').find((button) => button.props['aria-label'] === 'Log a rating or review').props.onClick();
        assert.deepEqual(redirects, ['/email/verify']);
        assert.deepEqual(bindings.updates, []);
    });
});

test('Inertia navigation refreshes verification requirements in the separately mounted header', () => {
    withHeader(false, ({ listeners }) => {
        const cleanup = bindings.effects[0]();
        listeners['inertia:navigate']({ detail: { page: { props: { emailVerificationRequired: true } } } });
        assert.equal(bindings.updates.at(-1), true);
        listeners['inertia:navigate']({ detail: { page: { props: { emailVerificationRequired: false } } } });
        assert.equal(bindings.updates.at(-1), false);
        cleanup();
    });
});

test('main navigation includes global Lists while mobile account links still include Home', () => {
    withHeader(false, ({ tree }) => {
        const desktop = elements(tree, 'nav').find((nav) => nav.props['aria-label'] === 'Main navigation');
        const mobile = elements(tree, 'nav').find((nav) => nav.props['aria-label'] === 'Mobile navigation');
        for (const nav of [desktop, mobile]) {
            const links = elements(nav, 'a');
            assert.deepEqual(links.map((link) => link.props.children), ['Literature', 'Lists', 'Readers', 'Catalog', ...(nav === mobile ? ['Home', 'Profile', 'Edit profile'] : [])]);
            assert.deepEqual(links.slice(0, 4).map((link) => link.props.href), ['/literature', '/lists', '/readers', '/catalog']);
        }
    }, { mobileOpen: true });
});

test('global Lists is active on both the directory and a list detail page', () => {
    for (const pathname of ['/lists', '/lists/a-reading-list']) {
        withHeader(false, ({ tree }) => {
            const desktop = elements(tree, 'nav').find((nav) => nav.props['aria-label'] === 'Main navigation');
            const lists = elements(desktop, 'a').find((link) => link.props.children === 'Lists');
            assert.equal(lists.props.href, '/lists');
            assert.equal(lists.props['aria-current'], 'page');
        }, { pathname });
    }
});

test('the logo still points to admin moderation without Home in the main navigation', () => {
    withHeader(false, ({ tree }) => {
        const desktop = elements(tree, 'nav').find((nav) => nav.props['aria-label'] === 'Main navigation');
        assert.equal(elements(desktop, 'a').some((link) => link.props.children === 'Home'), false);
        assert.match(renderToStaticMarkup(tree), /href="\/admin\/moderation"[^>]*aria-label="Literahaven - Home"/);
    }, { home: '/admin/moderation', pathname: '/admin/moderation' });
});
