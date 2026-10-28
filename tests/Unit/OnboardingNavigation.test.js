import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let Onboarding;
let bindings;

before(async () => {
    const moduleId = 'virtual:onboarding-navigation-test';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'onboarding-navigation-test',
            enforce: 'pre',
            resolveId(id) { if (id === moduleId) return `\0${moduleId}`; },
            load(id) {
                if (id !== `\0${moduleId}`) return;
                return `
                    let form, search;
                    export const setForm = (value) => { form = value; };
                    export const setSearch = (value) => { search = value; };
                    export const useForm = (data) => 'q' in data ? search : form;
                    export const Head = () => null;
                    export const useEffect = () => {};
                    export const useRef = (current) => ({ current });
                    export const useState = (value) => [value, () => {}];
                `;
            },
            transform(code, id) {
                if (!id.endsWith('/resources/js/Pages/Onboarding/Show.jsx')) return;
                return code
                    .replace("from '@inertiajs/react'", `from '${moduleId}'`)
                    .replace("from 'react'", `from '${moduleId}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null },
        appType: 'custom',
    });
    bindings = await server.ssrLoadModule(moduleId);
    ({ default: Onboarding } = await server.ssrLoadModule('/resources/js/Pages/Onboarding/Show.jsx'));
});

after(async () => { await server?.close(); });

function buttons(element) {
    if (!React.isValidElement(element)) return [];
    if (element.type === 'button') return [element];
    return React.Children.toArray(element.props.children).flatMap(buttons);
}

function page(step, { processing = false, formats = ['novel'], query = '' } = {}) {
    const requests = [];
    let transform = (data) => data;
    const form = {
        data: { formats }, processing, errors: {}, clearErrors() {},
        // Inertia React's transform returns void, so it cannot be chained with patch.
        transform(callback) { transform = callback; },
        patch(url, options) { requests.push({ url, data: transform(this.data), options }); },
    };
    bindings.setForm(form);
    bindings.setSearch({
        data: { q: query }, processing: false, errors: {},
        get(url, options) { requests.push({ method: 'get', url, data: this.data, options }); },
    });
    const tree = Onboarding({ step, preferredFormats: formats, formats: [], routes: { update: '/onboarding', discovery: '/onboarding', browse: '/literature/browse' } });
    const find = (label) => buttons(tree).find((button) => button.props.children === label);
    function discovery(element) {
        if (!React.isValidElement(element)) return;
        if (element.type?.name === 'OnboardingDiscovery') return element;
        return React.Children.toArray(element.props.children).map(discovery).find(Boolean);
    }
    return { requests, find, discovery: discovery(tree) };
}

for (const [step, label, action] of [[1, 'Next', 'next'], [2, 'Back', 'back'], [2, 'Next', 'next'], [3, 'Finish', 'complete'], [1, 'Skip', 'skip']]) {
    test(`${label} on step ${step} submits its action and selected formats`, () => {
        const { requests, find } = page(step);

        find(label).props.onClick();

        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, '/onboarding');
        assert.deepEqual(requests[0].data, { formats: ['novel'], action });
        assert.equal(requests[0].options.preserveScroll, true);
    });
}

test('Next supports exploring all formats without selecting a preference', () => {
    const { requests, find } = page(1, { formats: [] });

    find('Next').props.onClick();

    assert.deepEqual(requests[0].data, { formats: [], action: 'next' });
});

test('rapid Next clicks submit once and the controls unlock after the request finishes', () => {
    const { requests, find } = page(1);
    const next = find('Next');

    next.props.onClick();
    next.props.onClick();
    assert.equal(requests.length, 1);
    requests[0].options.onFinish();
    next.props.onClick();

    assert.equal(requests.length, 2);
});

test('a failed request can be retried after finishing', () => {
    const { requests, find } = page(1);

    find('Next').props.onClick();
    requests[0].options.onError({ formats: 'Please choose a supported format.' });
    requests[0].options.onFinish();
    find('Next').props.onClick();

    assert.equal(requests.length, 2);
    assert.deepEqual(requests[1].data, { formats: ['novel'], action: 'next' });
});

test('pending saves disable navigation and ignore further submissions', () => {
    const { requests, find } = page(1, { processing: true });

    find('Skip').props.onClick();

    assert.equal(find('Skip').props.disabled, true);
    assert.equal(requests.length, 0);
});

test('search submits to local onboarding discovery without leaving the current step', () => {
    const { requests, discovery } = page(2, { query: 'Earth' });
    let prevented = false;

    discovery.props.onSearch({ preventDefault() { prevented = true; } });

    assert.equal(prevented, true);
    assert.equal(requests[0].method, 'get');
    assert.equal(requests[0].url, '/onboarding');
    assert.deepEqual(requests[0].data, { q: 'Earth' });
    assert.equal(requests[0].options.preserveState, true);
    assert.equal(requests[0].options.preserveScroll, true);
    assert.equal(requests[0].options.replace, true);
});
