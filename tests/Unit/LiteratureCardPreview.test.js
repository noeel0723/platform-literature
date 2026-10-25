import assert from 'node:assert/strict';
import test from 'node:test';

import { focusablePreviewActions, previewActionIndex, previewPosition, previewTabBoundary } from '../../resources/js/Support/literatureCardPreview.js';

test('positions the panel beside a cover when there is room on the right', () => {
    assert.deepEqual(previewPosition({ left: 100, right: 200, bottom: 500 }, { left: 0, top: 0, width: 1024, height: 768 }, 300), {
        width: 256, maxHeight: 744, left: 208, top: 200,
    });
});

test('flips to the left rather than clipping at the right viewport edge', () => {
    assert.equal(previewPosition({ left: 800, right: 900, bottom: 500 }, { left: 0, top: 0, width: 1024, height: 768 }, 300).left, 536);
});

test('keeps a mobile panel inside both horizontal edges', () => {
    assert.deepEqual(previewPosition({ left: 20, right: 145, bottom: 500 }, { left: 0, top: 0, width: 240, height: 700 }, 300), {
        width: 216, maxHeight: 676, left: 12, top: 200,
    });
});

test('caps tall contents and keeps the panel above a mobile keyboard', () => {
    assert.deepEqual(previewPosition({ left: 20, right: 145, bottom: 700 }, { left: 0, top: 0, width: 375, height: 250 }, 600), {
        width: 256, maxHeight: 226, left: 12, top: 12,
    });
});

test('respects the visual viewport offset after zooming or panning', () => {
    assert.deepEqual(previewPosition({ left: 50, right: 170, bottom: 300 }, { left: 40, top: 100, width: 300, height: 400 }, 250), {
        width: 256, maxHeight: 376, left: 52, top: 112,
    });
});

test('clamps the panel inside the top and bottom edges after scrolling', () => {
    const viewport = { left: 0, top: 0, width: 1024, height: 768 };
    assert.equal(previewPosition({ left: 100, right: 200, bottom: 20 }, viewport, 300).top, 12);
    assert.equal(previewPosition({ left: 100, right: 200, bottom: 1200 }, viewport, 300).top, 456);
});

test('arrow navigation wraps between the first and last available actions', () => {
    assert.equal(previewActionIndex('ArrowDown', 3, 4), 0);
    assert.equal(previewActionIndex('ArrowUp', 0, 4), 3);
    assert.equal(previewActionIndex('ArrowDown', -1, 4), 0);
    assert.equal(previewActionIndex('ArrowDown', 1, 4), 2);
});

test('Home and End reach the boundary actions without activating them', () => {
    assert.equal(previewActionIndex('Home', 2, 5), 0);
    assert.equal(previewActionIndex('End', 2, 5), 4);
    assert.equal(previewActionIndex('Enter', 2, 5), null);
    assert.equal(previewActionIndex('ArrowDown', 0, 0), null);
});

test('Tab enters the panel and leaves after the final action instead of trapping focus', () => {
    assert.equal(previewTabBoundary(-1, 4, false), 'first');
    assert.equal(previewTabBoundary(3, 4, false), 'next');
    assert.equal(previewTabBoundary(1, 4, false), null);
});

test('Shift Tab returns to the cover trigger at the beginning of the panel', () => {
    assert.equal(previewTabBoundary(-1, 4, true), 'trigger');
    assert.equal(previewTabBoundary(0, 4, true), 'trigger');
    assert.equal(previewTabBoundary(2, 4, true), null);
});

test('keyboard navigation skips disabled, inert, hidden and negative-tabindex actions', () => {
    const action = (overrides = {}) => ({
        tabIndex: 0,
        matches: () => false,
        closest: () => null,
        getClientRects: () => [{}],
        ownerDocument: { defaultView: { getComputedStyle: () => ({ visibility: 'visible' }) } },
        ...overrides,
    });
    const enabled = action();
    const elements = [enabled, action({ tabIndex: -1 }), action({ matches: () => true }), action({ closest: () => ({}) }), action({ getClientRects: () => [] }), action({ ownerDocument: { defaultView: { getComputedStyle: () => ({ visibility: 'hidden' }) } } })];

    assert.deepEqual(focusablePreviewActions({ querySelectorAll: () => elements }), [enabled]);
});
