import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let OnboardingDiscovery;
before(async () => {
    server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    ({ default: OnboardingDiscovery } = await server.ssrLoadModule('/resources/js/Components/OnboardingDiscovery.jsx'));
});
after(async () => { await server?.close(); });

const literature = { id: 1, title: 'Earth Chronicles', author: 'Local Writer', type_label: 'Novel', year: 2001, url: '/literatures/earth', cover_url: null, initials: 'EC' };
const search = { data: { q: '' }, errors: {}, processing: false, setData() {} };
const props = { discovery: { query: '', groups: [{ format: 'Novel', literatures: [literature] }], results: [] }, search, browseUrl: '/literature/browse', onSearch() {} };
const render = (overrides = {}) => renderToStaticMarkup(React.createElement(OnboardingDiscovery, { ...props, ...overrides }));

function find(element, type) {
    if (!React.isValidElement(element)) return;
    if (element.type === type) return element;
    return React.Children.toArray(element.props.children).map((child) => find(child, type)).find(Boolean);
}

test('popular groups show title, author, format and year with safe new-tab detail links', () => {
    const html = render();
    assert.match(html, /Popular Novel/);
    assert.match(html, /Earth Chronicles/);
    assert.match(html, /Local Writer/);
    assert.match(html, /Novel · 2001/);
    assert.match(html, /href="\/literatures\/earth" target="_blank" rel="noopener noreferrer"/);
    assert.doesNotMatch(html, /Popular Manga|\/catalog/);
});

test('search displays local results instead of the default popular groups', () => {
    const html = render({ discovery: { query: 'Earth', groups: [], results: [literature] } });
    assert.match(html, /Search results for/);
    assert.match(html, /Earth Chronicles/);
    assert.doesNotMatch(html, /Popular reads in your favorite formats/);
});

test('the native search form invokes the supplied local-search handler', () => {
    const events = [];
    const tree = OnboardingDiscovery({ ...props, onSearch: (event) => events.push(event) });
    const event = { type: 'submit' };

    find(tree, 'form').props.onSubmit(event);

    assert.deepEqual(events, [event]);
});

test('typing a title or author updates the search form query', () => {
    const changes = [];
    const tree = OnboardingDiscovery({ ...props, search: { ...search, setData: (...args) => changes.push(args) } });

    find(tree, 'input').props.onChange({ target: { value: 'Local Writer' } });

    assert.deepEqual(changes, [['q', 'Local Writer']]);
});

test('unavailable formats and empty local searches have contextual messages', () => {
    const emptyFormat = render({ discovery: { query: '', groups: [{ format: 'Manhwa', literatures: [] }], results: [] } });
    const emptySearch = render({ discovery: { query: 'Unknown', groups: [], results: [] } });
    assert.match(emptyFormat, /No manhwa in the local library yet/);
    assert.match(emptySearch, /No matching literature in the local library/);
});

test('search controls disable during submission and announce busy state', () => {
    const html = render({ search: { ...search, processing: true } });
    assert.equal((html.match(/disabled=""/g) ?? []).length, 2);
    assert.match(html, /aria-busy="true"/);
    assert.match(html, /Searching…/);
});

test('validation errors are announced and user-provided search text is escaped', () => {
    const html = render({ search: { ...search, errors: { q: 'Search is too long.' } }, discovery: { query: '<script>bad()</script>', groups: [], results: [] } });
    assert.match(html, /role="alert"/);
    assert.match(html, /Search is too long/);
    assert.doesNotMatch(html, /<script>/);
});
