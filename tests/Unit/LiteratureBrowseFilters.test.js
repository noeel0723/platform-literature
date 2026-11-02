import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let LiteratureBrowseFilters;
let visits;
before(async () => {
    const moduleId = '\0browse-router-test';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'browse-router-test', enforce: 'pre',
            resolveId(id) { if (id === 'virtual:browse-router') return moduleId; },
            load(id) { if (id === moduleId) return 'export const visits = []; export const router = { get: (...args) => visits.push(args) };'; },
            transform(code, id) {
                if (id.endsWith('/resources/js/Components/LiteratureBrowseFilters.jsx')) return code.replace("from '@inertiajs/react'", "from 'virtual:browse-router'");
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    ({ visits } = await server.ssrLoadModule('virtual:browse-router'));
    ({ default: LiteratureBrowseFilters } = await server.ssrLoadModule('/resources/js/Components/LiteratureBrowseFilters.jsx'));
});
after(async () => { await server?.close(); });

const options = { types: [{ value: 'novel', label: 'Novel' }, { value: 'manga', label: 'Manga' }, { value: 'manhwa', label: 'Manhwa' }, { value: 'western-comic', label: 'Comic' }], decades: [], ratings: [], genres: [], sorts: [] };
const props = { browseUrl: '/literature/browse', options, includeType: true, compact: true };
function findType(element) {
    if (!React.isValidElement(element)) return null;
    if (element.props.name === 'type') return element;
    return React.Children.toArray(element.props.children).map(findType).find(Boolean);
}

test('the More type filter offers all four formats and reflects the selected format', () => {
    const html = renderToStaticMarkup(React.createElement(LiteratureBrowseFilters, { ...props, filters: { type: 'manga' } }));
    assert.match(html, /aria-label="Literature type"/);
    assert.match(html, /All types/);
    for (const label of ['Novel', 'Manga', 'Manhwa', 'Comic']) assert.match(html, new RegExp(`>${label}</option>`));
    assert.match(html, /value="manga" selected/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(LiteratureBrowseFilters, { ...props, includeType: false })), /Literature type/);
});

test('changing type preserves the other filters and resets pagination', () => {
    const previousWindow = globalThis.window;
    globalThis.window = { location: { origin: 'http://literahaven.test' } };
    visits.length = 0;
    try {
        const tree = LiteratureBrowseFilters({ ...props, filters: { q: 'Moon', type: 'novel', decade: 2010, rating: 'highest', genre: 'fantasy', sort: 'year-desc', page: 3 } });
        findType(tree).props.onChange('manga');
        const target = new URL(visits[0][0], 'http://literahaven.test');
        assert.equal(target.pathname, '/literature/browse');
        assert.deepEqual(Object.fromEntries(target.searchParams), { q: 'Moon', type: 'manga', decade: '2010', rating: 'highest', genre: 'fantasy', sort: 'year-desc' });
        assert.deepEqual(visits[0][2], { preserveScroll: true, preserveState: true });
        findType(tree).props.onChange('');
        assert.equal(new URL(visits[1][0], 'http://literahaven.test').searchParams.has('type'), false);
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
    }
});
