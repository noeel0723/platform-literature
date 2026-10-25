import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let ActionToastMessage;
before(async () => {
    server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    ({ ActionToastMessage } = await server.ssrLoadModule('/resources/js/Components/ActionToast.jsx'));
});
after(async () => { await server?.close(); });
const render = (toast, blocked = false) => renderToStaticMarkup(React.createElement(ActionToastMessage, { toast, blocked }));

test('success is announced politely and offers a real Undo button plus a labeled dismiss button', () => {
    const html = render({ message: 'Added to your Readlist.', undo: { url: '/undo' } });
    assert.match(html, /role="status"/);
    assert.match(html, />Undo<\/button>/);
    assert.match(html, /aria-label="Dismiss notification"/);
});

test('pending Undo prevents duplicate clicks and announces loading', () => {
    const html = render({ message: 'Added.', undo: { url: '/undo' }, busy: true });
    assert.match(html, /Undoing…/);
    assert.equal((html.match(/disabled=""/g) ?? []).length, 2);
});

test('failure is announced as an alert and does not expose a fake Undo', () => {
    const html = render({ tone: 'error', message: 'Please refresh and try again.' });
    assert.match(html, /role="alert"/);
    assert.doesNotMatch(html, />Undo<\/button>/);
});

test('another pending mutation disables Undo while keeping dismiss available', () => {
    const html = render({ message: 'Added.', undo: { url: '/undo' } }, true);
    assert.equal((html.match(/disabled=""/g) ?? []).length, 1);
    assert.match(html, /aria-label="Dismiss notification"/);
});

test('toast text escapes user-controlled titles', () => {
    const html = render({ message: 'Added to <script>bad()</script>.' });
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /&lt;script&gt;/);
});
