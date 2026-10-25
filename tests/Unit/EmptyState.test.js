import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let EmptyState;

before(async () => {
    server = await createServer({
        configFile: false,
        plugins: [react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null },
        appType: 'custom',
    });
    ({ default: EmptyState } = await server.ssrLoadModule('/resources/js/Components/EmptyState.jsx'));
});

after(async () => { await server?.close(); });

test('renders a concise title and description without an unrelated action', () => {
    const html = renderToStaticMarkup(React.createElement(EmptyState, { title: 'No reviews yet.', description: 'Reader reviews will appear here.' }));

    assert.match(html, /No reviews yet\./);
    assert.match(html, /Reader reviews will appear here\./);
    assert.doesNotMatch(html, /<a\b|<button\b/);
});

test('renders an available CTA using its existing destination', () => {
    const html = renderToStaticMarkup(React.createElement(EmptyState, { title: 'No matching titles.', action: { href: '/catalog', label: 'Clear search' } }));

    assert.match(html, /href="\/catalog"/);
    assert.match(html, />Clear search<\/a>/);
});

test('does not render a dead CTA when its destination or label is missing', () => {
    for (const action of [null, { label: 'New list' }, { href: '' }, { href: '/catalog' }]) {
        const html = renderToStaticMarkup(React.createElement(EmptyState, { title: 'No custom lists yet.', action }));

        assert.doesNotMatch(html, /<a\b|<button\b/);
    }
});

test('preserves list semantics and data attributes for compact nested empty states', () => {
    const html = renderToStaticMarkup(React.createElement(EmptyState, { as: 'li', compact: true, title: 'No activity yet.', 'data-profile-empty': true }));

    assert.match(html, /^<li\b/);
    assert.match(html, /data-profile-empty="true"/);
    assert.match(html, /No activity yet\./);
    assert.match(html, /<\/li>$/);
});

test('supports existing text-only messages without an empty description placeholder', () => {
    const html = renderToStaticMarkup(React.createElement(EmptyState, null, 'No favorite literature has been selected.'));

    assert.match(html, /No favorite literature has been selected\./);
    assert.equal((html.match(/<p\b/g) ?? []).length, 1);
});

test('escapes titles and descriptions rather than interpreting reader content as markup', () => {
    const html = renderToStaticMarkup(React.createElement(EmptyState, { title: '<script>bad()</script>', description: 'Fantasy & adventure' }));

    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /Fantasy &amp; adventure/);
});
