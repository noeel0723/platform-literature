import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let FormatPreferences;
let OnboardingProgress;
let ReadingActionsIntro;
before(async () => {
    server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    ({ FormatPreferences, OnboardingProgress, ReadingActionsIntro } = await server.ssrLoadModule('/resources/js/Components/OnboardingSteps.jsx'));
});
after(async () => { await server?.close(); });
const formats = [{ value: 'novel', label: 'Novel' }, { value: 'manga', label: 'Manga' }, { value: 'manhwa', label: 'Manhwa' }, { value: 'western-comic', label: 'Western Comic' }];

function findInputs(element) {
    if (!React.isValidElement(element)) return [];
    if (element.type === 'input') return [element];
    return React.Children.toArray(element.props.children).flatMap(findInputs);
}

test('formats use labeled native checkboxes with the saved selections', () => {
    const html = renderToStaticMarkup(React.createElement(FormatPreferences, { formats, selected: ['manga'], onChange: () => {} }));
    assert.equal((html.match(/type="checkbox"/g) ?? []).length, 4);
    assert.equal((html.match(/checked=""/g) ?? []).length, 1);
    assert.match(html, /Western Comic/);
    assert.match(html, /value="western-comic"/);
    assert.match(html, /Favorite literature formats/);
});

test('format input interactions pass their existing format identifiers to the parent', () => {
    const changes = [];
    const tree = FormatPreferences({ formats, selected: [], onChange: (format) => changes.push(format) });

    for (const input of findInputs(tree)) input.props.onChange();

    assert.deepEqual(changes, ['novel', 'manga', 'manhwa', 'western-comic']);
});

test('format selection is disabled while the next step is saving', () => {
    const html = renderToStaticMarkup(React.createElement(FormatPreferences, { formats, selected: [], disabled: true }));
    assert.match(html, /<fieldset disabled=""/);
});

test('progress has exactly three steps and announces the active step', () => {
    const html = renderToStaticMarkup(React.createElement(OnboardingProgress, { step: 2 }));
    assert.equal((html.match(/<li\b/g) ?? []).length, 3);
    assert.equal((html.match(/aria-current="step"/g) ?? []).length, 1);
    assert.match(html, /Onboarding progress/);
    assert.match(html, /Find a read/);
});

test('the final introduction only presents the four existing reading actions', () => {
    const html = renderToStaticMarkup(React.createElement(ReadingActionsIntro));
    assert.equal((html.match(/<li\b/g) ?? []).length, 4);
    for (const action of ['Readlist', 'Completed', 'Rating', 'Review']) assert.match(html, new RegExp(`>${action}</h2>`));
    assert.doesNotMatch(html, /<a\b|<button\b/);
});
