import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let LiteraturePreviewSources;

before(async () => {
    server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    ({ default: LiteraturePreviewSources } = await server.ssrLoadModule('/resources/js/Components/LiteraturePreviewSources.jsx'));
});

after(async () => { await server?.close(); });

const render = (sources) => renderToStaticMarkup(React.createElement(LiteraturePreviewSources, { sources }));

test('labels catalog providers separately from the synopsis provider', () => {
    const html = render({ catalog: ['AniList', 'Kitsu'], synopsis: 'Wikipedia EN' });

    assert.match(html, /aria-label="Data sources"/);
    assert.match(html, /<dt[^>]*>Literature<\/dt><dd[^>]*>AniList · Kitsu<\/dd>/);
    assert.match(html, /<dt[^>]*>Synopsis<\/dt><dd[^>]*>Wikipedia EN<\/dd>/);
});

test('identifies curated synopsis data without suggesting it came from the catalog API', () => {
    const html = render({ catalog: ['Hardcover'], synopsis: 'Literahaven curated metadata' });

    assert.match(html, /Literahaven curated metadata/);
    assert.match(html, />Hardcover<\/dd>/);
});

test('missing attribution stays explicit and older preview responses remain supported', () => {
    assert.equal(render(undefined), '');
    assert.equal((render({ catalog: [], synopsis: null }).match(/Not available/g) ?? []).length, 2);
});

test('provider names are rendered as escaped text without exposing external URLs as actions', () => {
    const html = render({ catalog: ['<script>bad()</script>'], synopsis: 'Books & stories' });

    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /Books &amp; stories/);
    assert.doesNotMatch(html, /<script>|<a\b|<button\b/);
});
