import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let bindings;
let DeleteAccountSection;

before(async () => {
    const virtual = 'virtual:delete-account-hooks';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'delete-account-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id !== `\0${virtual}`) return;
                return `export let form; export const refs = [];
                    export const prepare = (value) => { form = value; refs.length = 0; };
                    export const useForm = () => form;
                    export const useRef = (value) => { const ref = { current: value }; refs.push(ref); return ref; };`;
            },
            transform(code, id) {
                if (id.endsWith('/Components/DeleteAccountSection.jsx')) return code
                    .replace("from '@inertiajs/react'", `from '${virtual}'`)
                    .replace("from 'react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    bindings = await server.ssrLoadModule(virtual);
    ({ default: DeleteAccountSection } = await server.ssrLoadModule('/resources/js/Components/DeleteAccountSection.jsx'));
});
after(async () => { await server?.close(); });

function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

function section(overrides = {}) {
    const visits = [], resets = [], updates = [];
    let cleared = 0, focused = 0;
    const form = { data: { password: '', confirmation: false }, errors: {}, processing: false,
        delete: (...args) => visits.push(args), reset: (...args) => resets.push(args),
        clearErrors: () => cleared++, setData: (...args) => updates.push(args), ...overrides };
    bindings.prepare(form);
    const tree = DeleteAccountSection({ deleteUrl: '/profile', homeUrl: '/' });
    bindings.refs[0].current = { open: true };
    bindings.refs[1].current = { focus: () => focused++ };
    bindings.refs[2].current = { focus: () => focused++ };
    return { tree, visits, resets, updates, form, focused: () => focused, cleared: () => cleared };
}

test('the deletion form explains permanence and requires a current password and explicit confirmation', () => {
    const { tree, updates } = section();
    const html = renderToStaticMarkup(tree);
    assert.match(html, /This cannot be undone/);
    const [password, confirmation] = elements(tree, 'input');
    assert.equal(password.props.autoComplete, 'current-password');
    assert.equal(password.props.required, true);
    assert.equal(confirmation.props.required, true);
    password.props.onChange({ target: { value: 'current-password' } });
    confirmation.props.onChange({ target: { checked: true } });
    assert.deepEqual(updates, [['password', 'current-password'], ['confirmation', true]]);
});

test('submission uses DELETE, prevents duplicate requests, and clears the password after failure', () => {
    const context = section();
    const submit = elements(context.tree, 'form')[0].props.onSubmit;
    submit({ preventDefault() {} });
    submit({ preventDefault() {} });
    assert.equal(context.visits.length, 1);
    assert.equal(context.visits[0][0], '/profile');
    const options = context.visits[0][1];
    assert.equal(options.errorBag, 'deleteAccount');
    options.onError({ password: 'The password is incorrect.' });
    options.onFinish();
    assert.equal(context.focused(), 1);
    assert.deepEqual(context.resets, [['password']]);
    submit({ preventDefault() {} });
    assert.equal(context.visits.length, 2);
});

test('cancel clears the confirmation form and returns focus without deleting', () => {
    const context = section();
    elements(context.tree, 'button').find((button) => button.props.children === 'Cancel').props.onClick();
    assert.deepEqual(context.resets, [[]]);
    assert.equal(context.cleared(), 1);
    assert.equal(bindings.refs[0].current.open, false);
    assert.equal(context.focused(), 1);
    assert.deepEqual(context.visits, []);
});

test('success clears local drafts and reloads the landing page to refresh the separately mounted header', () => {
    const previousWindow = globalThis.window;
    const values = new Map([['literahaven:review-draft-owner', '123'], ['literahaven:review-draft:v1:123:canonical:1', 'draft']]);
    const redirects = [];
    globalThis.window = { location: { assign: (url) => redirects.push(url) }, localStorage: {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: (key) => values.delete(key),
        get length() { return values.size; }, key: (index) => [...values.keys()][index],
    } };
    try {
        const context = section();
        elements(context.tree, 'form')[0].props.onSubmit({ preventDefault() {} });
        context.visits[0][1].onSuccess({ props: {} });
        assert.deepEqual(redirects, ['/']);
        assert.equal(values.has('literahaven:review-draft:v1:123:canonical:1'), false);
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
    }
});

test('pending deletion disables controls and validation errors remain accessible', () => {
    const context = section({ processing: true, errors: { password: 'The password is incorrect.' } });
    assert.equal(elements(context.tree, 'fieldset')[0].props.disabled, true);
    assert.match(renderToStaticMarkup(context.tree), /role="alert"[^>]*>The password is incorrect/);
    elements(context.tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.deepEqual(context.visits, []);
});
