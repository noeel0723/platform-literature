import assert from 'node:assert/strict';
import { before, after, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let ReviewDraftStatus;
before(async () => {
    server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    ({ default: ReviewDraftStatus } = await server.ssrLoadModule('/resources/js/Components/ReviewDraftStatus.jsx'));
});
after(async () => { await server?.close(); });
const render = (draft, processing = false) => renderToStaticMarkup(React.createElement(ReviewDraftStatus, { draft, processing }));
function buttons(element) {
    if (!React.isValidElement(element)) return [];
    if (element.type === 'button') return [element];
    return React.Children.toArray(element.props.children).flatMap(buttons);
}

test('saved drafts have a small polite indicator and a discard action', () => {
    const html = render({ status: 'saved', hasDraft: true });
    assert.match(html, /Draft saved locally/);
    assert.match(html, /role="status"/);
    assert.match(html, />Discard draft<\/button>/);
    assert.doesNotMatch(html, /Restore draft/);
});

test('conflict controls invoke the actual restore and discard callbacks', () => {
    const calls = [];
    const tree = ReviewDraftStatus({ draft: { status: 'conflict', hasDraft: true, restore: () => calls.push('restore'), discard: () => calls.push('discard') } });

    for (const button of buttons(tree)) button.props.onClick();

    assert.deepEqual(calls, ['restore', 'discard']);
    assert.match(render({ status: 'conflict', hasDraft: true }), /saved review may have changed/);
});

test('draft controls are disabled during publication', () => {
    const html = render({ status: 'conflict', hasDraft: true }, true);
    assert.equal((html.match(/disabled=""/g) ?? []).length, 2);
});

test('idle forms do not leave an empty indicator row', () => {
    assert.equal(render({ status: 'idle', hasDraft: false }), '');
});

test('storage failure never claims that a draft was saved', () => {
    const html = render({ status: 'unavailable', hasDraft: true });
    assert.match(html, /Local draft storage is unavailable/);
    assert.doesNotMatch(html, /Draft saved locally/);
});
