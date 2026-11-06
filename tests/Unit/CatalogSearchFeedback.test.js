import assert from 'node:assert/strict';
import { after, before, beforeEach, afterEach, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, hooks, useCatalogSearch, Feedback, Context, Catalog;
let previousWindow;
let timers;

before(async () => {
    const virtual = 'virtual:catalog-search-test';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'catalog-search-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id !== `\0${virtual}`) return;
                return `import React from 'react';
                    export const Head = () => null;
                    export const Link = ({ href, children }) => React.createElement('a', { href }, children);
                    export const visits = []; export const router = { get: (...args) => visits.push(args) };
                    let slots = [], cursor = 0; export const effects = [];
                    export const reset = () => { slots = []; cursor = 0; effects.length = 0; visits.length = 0; };
                    export const render = () => { cursor = 0; };
                    export const useState = (value) => {
                        const index = cursor++; if (!(index in slots)) slots[index] = value;
                        return [slots[index], (next) => { slots[index] = typeof next === 'function' ? next(slots[index]) : next; }];
                    };
                    export const useRef = (value) => { const index = cursor++; return slots[index] ??= { current: value }; };
                    export const useEffect = (effect) => { effects.push(effect); };`;
            },
            transform(code, id) {
                if (id.endsWith('/Support/useCatalogSearch.js') || id.endsWith('/Pages/Catalog/Index.jsx')) return code
                    .replace("from 'react'", `from '${virtual}'`)
                    .replace("from '@inertiajs/react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    hooks = await server.ssrLoadModule(virtual);
    ({ default: useCatalogSearch } = await server.ssrLoadModule('/resources/js/Support/useCatalogSearch.js'));
    ({ default: Feedback } = await server.ssrLoadModule('/resources/js/Components/CatalogSearchFeedback.jsx'));
    ({ default: Context } = await server.ssrLoadModule('/resources/js/Components/LiteratureDiscoveryContext.jsx'));
    ({ default: Catalog } = await server.ssrLoadModule('/resources/js/Pages/Catalog/Index.jsx'));
});
after(async () => { await server?.close(); });
beforeEach(() => {
    hooks.reset();
    previousWindow = globalThis.window;
    timers = new Map();
    globalThis.window = {
        location: { origin: 'http://literahaven.test' },
        setTimeout: (callback, delay) => { const id = Symbol(); timers.set(id, { callback, delay }); return id; },
        clearTimeout: (id) => timers.delete(id),
    };
});
afterEach(() => { if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow; });

const current = () => { hooks.render(); return useCatalogSearch(); };
const render = (Component, props) => renderToStaticMarkup(React.createElement(Component, props));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('search announces external retrieval, prevents duplicate visits, and clears loading on completion', () => {
    const request = current().visitOptions('  Earth  ');
    assert.equal(request.onBefore(), true);
    request.onStart();
    assert.equal(current().pending, true);
    assert.equal(current().query, 'Earth');
    assert.equal(current().visitOptions('Another title').onBefore(), false);
    assert.equal([...timers.values()][0].delay, 10_000);
    const html = render(Feedback, current());
    assert.match(html, /role="status"/);
    assert.match(html, /Searching catalog sources/);
    assert.match(html, /external sources and our collection/);
    request.onFinish();
    assert.equal(current().pending, false);
    assert.equal(timers.size, 0);
    assert.equal(render(Feedback, current()), '');
});

test('long searches get an honest waiting message and a later search starts with a fresh timer', () => {
    const request = current().visitOptions('Earth');
    request.onStart();
    [...timers.values()][0].callback();
    assert.match(render(Feedback, current()), /Still waiting for catalog sources/);
    request.onFinish();
    current().visitOptions('Moon').onStart();
    assert.equal(current().slow, false);
    assert.equal(current().query, 'Moon');
});

test('cancel uses the actual Inertia cancellation token and clears loading without an error', () => {
    const request = current().visitOptions('Earth');
    let cancelled = 0;
    request.onCancelToken({ cancel: () => { cancelled++; request.onFinish(); } });
    request.onStart();
    const button = elements(Feedback({ ...current(), onCancel: current().cancel }), 'button')[0];
    button.props.onClick();
    assert.equal(cancelled, 1);
    assert.equal(current().pending, false);
    assert.equal(current().error, '');
    assert.equal(timers.size, 0);
    assert.equal(current().visitOptions().onBefore(), true);
});

test('network, server, and validation failures restore results with an accessible retry message', () => {
    for (const failure of ['onNetworkError', 'onHttpException', 'onError']) {
        hooks.reset();
        const request = current().visitOptions('Earth');
        request.onStart();
        assert.equal(request[failure](), false);
        request.onFinish();
        assert.equal(current().pending, false);
        assert.equal(timers.size, 0);
        const html = render(Feedback, current());
        assert.match(html, /role="alert"/);
        assert.match(html, /Check your connection and try again/);
        assert.match(html, /previous results are still available/);
        const retry = current().visitOptions('Retry');
        retry.onStart();
        assert.equal(current().error, '');
        retry.onFinish();
    }
});

test('leaving the page cleans up the slow-search timer and ignores late callbacks', () => {
    current();
    const cleanup = hooks.effects[0]();
    const request = current().visitOptions('Earth');
    request.onStart();
    const lateTimer = [...timers.values()][0].callback;
    cleanup();
    assert.equal(timers.size, 0);
    lateTimer();
    request.onNetworkError();
    request.onFinish();
    assert.equal(current().slow, false);
    assert.equal(current().error, '');
});

test('format-only browsing does not claim to contact external sources', () => {
    const request = current().visitOptions('');
    request.onStart();
    assert.equal(timers.size, 0);
    const html = render(Feedback, current());
    assert.match(html, /Loading catalog/);
    assert.doesNotMatch(html, /Searching catalog sources|across external sources/);
});

test('Catalog form and format links use loading lifecycle without changing query or navigation options', () => {
    const props = { query: 'Earth', selectedType: 'novel', literatures: [], canExpand: false, sourceWarning: null, types: { novel: 'Novel', manga: 'Manga' }, routes: { catalog: '/catalog', latest: '/catalog/latest?q=Earth' } };
    let tree = Catalog(props);
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    const [url, data, options] = hooks.visits[0];
    assert.equal(url, '/catalog');
    assert.deepEqual(data, { q: 'Earth', type: 'novel' });
    assert.equal(options.preserveScroll, true);
    assert.equal(options.preserveState, true);
    assert.equal(options.replace, true);
    options.onStart();
    hooks.render(); tree = Catalog(props);
    assert.equal(elements(tree, 'button').find((button) => button.props.type === 'submit').props.disabled, true);
    const feedback = elements(tree, Feedback)[0];
    assert.equal(feedback.props.pending, true);
    const filters = elements(elements(tree, 'nav')[0], hooks.Link);
    assert.equal(filters[1].props.href, '/catalog?q=Earth&type=novel');
    assert.equal(filters[1].props.onBefore(), false);
    assert.equal(filters[1].props['aria-disabled'], true);
    assert.equal(elements(tree, 'div').some((element) => element.props['aria-busy'] === true), true);
    assert.equal(elements(tree, 'div').some((element) => element.props['data-catalog-results'] !== undefined), false);
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(hooks.visits.length, 1);
    options.onFinish();
    hooks.render(); tree = Catalog(props);
    assert.equal(elements(tree, 'button').find((button) => button.props.type === 'submit').props.disabled, false);
});

test('page context distinguishes community collection, external Catalog, and already-retrieved More results', () => {
    assert.match(render(Context, {}), /On Literahaven/);
    assert.match(render(Context, {}), /Search and filters use our local collection/);
    assert.match(render(Context, { catalog: true }), /Search external sources/);
    assert.match(render(Context, { catalog: true }), /take a little longer/);
    assert.match(render(Context, { catalog: true, results: true }), /results are already available on Literahaven/);
    const html = render(Feedback, { pending: true, query: '<script>bad()</script>' });
    assert.match(html, /&lt;script&gt;/);
    assert.doesNotMatch(html, /<script>/);
});
