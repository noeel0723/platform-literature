import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let ProfileBanner;
let ProfileBannerEditor;

before(async () => {
    const moduleId = 'virtual:banner-editor-hooks';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'banner-editor-test', enforce: 'pre',
            resolveId(id) { if (id === moduleId) return `\0${moduleId}`; },
            load(id) { if (id === `\0${moduleId}`) return 'export const useEffect = () => {}; export const useState = (initial) => [initial, () => {}];'; },
            transform(code, id) { if (id.endsWith('/resources/js/Components/ProfileBannerEditor.jsx')) return code.replace("from 'react'", `from '${moduleId}'`); },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    ({ default: ProfileBanner } = await server.ssrLoadModule('/resources/js/Components/ProfileBanner.jsx'));
    ({ default: ProfileBannerEditor } = await server.ssrLoadModule('/resources/js/Components/ProfileBannerEditor.jsx'));
});

after(async () => { await server?.close(); });

const profile = { name: 'Reader', banner_url: '/storage/profile-banners/sample.jpg', has_banner: true, banner_position_x: 35, banner_position_y: 70, is_owner: true };
const render = (overrides = {}) => renderToStaticMarkup(React.createElement(ProfileBanner, { profile: { ...profile, ...overrides } }));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}
function editor(overrides = {}) {
    const changes = [];
    const form = { data: { banner: null, remove_banner: false, banner_position_x: 35, banner_position_y: 70 }, processing: false, errors: {}, clearErrors() {}, setData: (...args) => changes.push(args), ...overrides };
    return { changes, tree: ProfileBannerEditor({ profile, form }), form };
}

test('public banner uses the saved focal position without inline editing controls for owners or visitors', () => {
    assert.match(render(), /object-position:35% 70%/);
    assert.doesNotMatch(render(), /Edit banner|<a\b/);
    assert.doesNotMatch(render({ is_owner: false }), /Edit banner|<a\b/);
    assert.equal(render({ banner_url: null }), '');
});

test('banner has descriptive image text and escapes the profile name', () => {
    const html = render({ name: '<script>Reader</script>' });
    assert.match(html, /profile banner/);
    assert.doesNotMatch(html, /<script>/);
});

test('selecting an image clears removal and resets the focal position for the new banner', () => {
    const { tree, changes } = editor();
    const file = { type: 'image/jpeg', size: 1000 };
    elements(tree, 'input').find((input) => input.props.type === 'file').props.onChange({ target: { files: [file], value: 'selected.jpg' } });
    assert.deepEqual(changes, [[{ banner: file, remove_banner: false, banner_position_x: 50, banner_position_y: 50 }]]);
});

test('unsupported and oversized files never replace the current banner', () => {
    const { tree, changes } = editor();
    const input = elements(tree, 'input').find((element) => element.props.type === 'file');
    for (const file of [{ type: 'image/svg+xml', size: 100 }, { type: 'image/jpeg', size: 5242881 }]) input.props.onChange({ target: { files: [file], value: 'bad' } });
    assert.deepEqual(changes, []);
});

test('keyboard-accessible position sliders update independent numeric coordinates', () => {
    const { tree, changes } = editor();
    const sliders = elements(tree, 'input').filter((input) => input.props.type === 'range');
    sliders[0].props.onChange({ target: { value: '20' } });
    sliders[1].props.onChange({ target: { value: '90' } });
    assert.deepEqual(changes, [['banner_position_x', 20], ['banner_position_y', 90]]);
});

test('remove is staged in the form and the current image can be restored before saving', () => {
    const { tree, changes } = editor();
    elements(tree, 'button').find((button) => button.props.children === 'Remove banner').props.onClick();
    assert.deepEqual(changes, [[{ banner: null, remove_banner: true, banner_position_x: 50, banner_position_y: 50 }]]);
    const removed = editor({ data: changes[0][0] });
    elements(removed.tree, 'button').find((button) => button.props.children === 'Restore current banner').props.onClick();
    assert.deepEqual(removed.changes, [[{ banner: null, remove_banner: false, banner_position_x: 35, banner_position_y: 70 }]]);
});

test('upload and adjustment controls disable while saving and server validation errors are announced', () => {
    const { tree } = editor({ processing: true, errors: { banner: 'Invalid image.' } });
    assert.equal(elements(tree, 'input').find((input) => input.props.type === 'file').props.disabled, true);
    assert.equal(elements(tree, 'fieldset')[0].props.disabled, true);
    assert.match(renderToStaticMarkup(tree), /role="alert"[^>]*>Invalid image\./);
});
