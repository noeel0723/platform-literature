import assert from 'node:assert/strict';
import { after, before, beforeEach, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, LoginPanel, controls;
before(async () => {
    const virtual = 'virtual:login-panel-form';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'login-panel-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id !== `\0${virtual}`) return;
                return `export const posts = [], resets = [];
                    export const form = { data: {}, errors: {}, processing: false,
                        post: (...args) => posts.push(args), reset: (...fields) => resets.push(fields), setData() {} };
                    export const reset = () => { posts.length = 0; resets.length = 0; form.errors = {}; form.processing = false; form.data = { email: 'a_reader', password: 'password', remember: true }; };
                    export const useForm = () => form; export const useEffect = () => {};
                    export const useRef = () => ({ current: null });`;
            },
            transform(code, id) {
                if (id.endsWith('/Components/LoginPanel.jsx')) return code
                    .replace("from '@inertiajs/react'", `from '${virtual}'`)
                    .replace("from 'react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    controls = await server.ssrLoadModule(virtual);
    ({ default: LoginPanel } = await server.ssrLoadModule('/resources/js/Components/LoginPanel.jsx'));
});
after(async () => { await server?.close(); });
beforeEach(() => { controls.reset(); });

const props = { routes: { login: '/login', password_request: '/forgot-password' }, inline: true };
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('login lets the server perform full navigation instead of reloading after an Inertia page swap', () => {
    const form = elements(LoginPanel(props), 'form')[0];
    let prevented = false;
    form.props.onSubmit({ preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(controls.posts.length, 1);
    const [url, options] = controls.posts[0];
    assert.equal(url, '/login');
    assert.equal(options.preserveScroll, true);
    assert.equal(options.onSuccess, undefined);
    options.onFinish();
    assert.deepEqual(controls.resets, [['password']]);
});

test('an in-flight login disables Sign in and ignores repeated form submissions', () => {
    controls.form.processing = true;
    const tree = LoginPanel(props);
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(controls.posts.length, 0);
    assert.equal(elements(tree, 'button').find((button) => button.props.type === 'submit').props.disabled, true);
    assert.match(renderToStaticMarkup(tree), /Signing in…/);
});

test('failed login still shows inline validation errors and retains the username and remember choice', () => {
    controls.form.data.password = '';
    controls.form.errors.email = 'The username, email address, or password is incorrect.';
    const html = renderToStaticMarkup(React.createElement(LoginPanel, props));
    assert.match(html, /The username, email address, or password is incorrect/);
    assert.match(html, /value="a_reader"/);
    assert.match(html, /type="checkbox"[^>]*checked/);
    assert.doesNotMatch(html, /Signing in…/);
});
