import assert from 'node:assert/strict';
import { after, before, beforeEach, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, controls, Edit, AuthorShow;
before(async () => {
    const virtual = 'virtual:author-editor-form';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'author-editor-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id !== `\0${virtual}`) return;
                return `export { Link } from '@inertiajs/react';
                    export const Head = () => null;
                    export const useEffect = () => {};
                    export const useRef = () => ({ current: null });
                    export const useState = () => [null, () => {}];
                    export const posts = [], changes = [];
                    export const form = { data: {}, errors: {}, progress: null, processing: false,
                        post: (...args) => posts.push(args), setError: (key, value) => { form.errors[key] = value; },
                        setData: (key, value) => { changes.push([key, value]); form.data[key] = value; } };
                    export const useForm = (data) => { form.data = { ...data, ...form.data }; return form; };
                    export const reset = () => { posts.length = 0; changes.length = 0; form.data = {}; form.errors = {}; form.progress = null; form.processing = false; };`;
            },
            transform(code, id) {
                if (id.endsWith('/Pages/Admin/AuthorMetadata/Edit.jsx')) return code
                    .replace("from '@inertiajs/react'", `from '${virtual}'`)
                    .replace("from 'react'", `from '${virtual}'`);
                if (id.endsWith('/Pages/Author/Show.jsx')) return code.replace("from '@inertiajs/react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    controls = await server.ssrLoadModule(virtual);
    ({ default: Edit } = await server.ssrLoadModule('/resources/js/Pages/Admin/AuthorMetadata/Edit.jsx'));
    ({ default: AuthorShow } = await server.ssrLoadModule('/resources/js/Pages/Author/Show.jsx'));
});
after(async () => { await server?.close(); });
beforeEach(() => controls.reset());

const author = { name: 'Test Writer', url: '/authors/test-writer', biography_override: 'Curated description',
    image_url_override: 'https://example.com/manual.jpg', uploaded_image_url: '/storage/author-portraits/upload.jpg',
    api_biography: 'Original biography', api_image_url: 'https://example.com/original.jpg' };
const props = { author, updateUrl: '/admin/authors/test-writer/metadata' };
const render = (Component, input) => renderToStaticMarkup(React.createElement(Component, input));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('editor has labeled description, safe photo controls and current upload preview', () => {
    const html = render(Edit, props);
    assert.match(html, /Edit author/);
    assert.match(html, /for="author-biography"/);
    assert.match(html, /for="author-image"/);
    assert.match(html, /accept="image\/jpeg,image\/png,image\/webp"/);
    assert.match(html, /Curated description/);
    assert.match(html, /src="\/storage\/author-portraits\/upload.jpg"/);
    assert.match(html, /Original biography/);
    assert.match(html, /Maximum 5 MB/);
});

test('save uses multipart method spoofing and pending save prevents duplicate requests', () => {
    const tree = Edit(props);
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(controls.form.data._method, 'PUT');
    const [url, options] = controls.posts[0];
    assert.equal(url, props.updateUrl);
    assert.equal(options.forceFormData, true);
    assert.equal(options.preserveScroll, true);
    controls.form.processing = true;
    elements(Edit(props), 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(controls.posts.length, 1);
    const html = render(Edit, props);
    assert.match(html, /type="submit" disabled/);
    assert.match(html, /Saving/);
});

test('network failure keeps entered values and field validation remains accessible', () => {
    const tree = Edit(props);
    elements(tree, 'textarea')[0].props.onChange({ target: { value: 'Unsaved draft' } });
    elements(tree, 'form')[0].props.onSubmit({ preventDefault() {} });
    controls.posts[0][1].onNetworkError();
    assert.equal(controls.form.data.biography_override, 'Unsaved draft');
    assert.match(render(Edit, props), /The changes could not be sent/);
    controls.form.errors.biography_override = 'Description is too long.';
    const html = render(Edit, props);
    assert.match(html, /role="alert"/);
    assert.match(html, /aria-describedby="author-biography-help biography_override-error"/);
    assert.match(html, /aria-invalid="true"/);
});

test('removing upload previews the URL or original image without changing API values', () => {
    let tree = Edit(props);
    elements(tree, 'input').find((input) => input.props.type === 'checkbox').props.onChange({ target: { checked: true } });
    assert.match(render(Edit, props), /src="https:\/\/example.com\/manual.jpg"/);
    controls.form.data.image_url_override = '';
    assert.match(render(Edit, props), /src="https:\/\/example.com\/original.jpg"/);
    assert.equal(author.api_image_url, 'https://example.com/original.jpg');
    tree = Edit(props);
    const file = { name: 'new.png' };
    elements(tree, 'input').find((input) => input.props.type === 'file').props.onChange({ target: { files: [file] } });
    assert.equal(controls.form.data.image_upload, file);
});

test('public author page renders edit control only when server grants an edit URL', () => {
    const input = { author: { name: '<Writer>', biography: 'A biography', image_url: null, initials: 'W', literatures_count: 0 }, literatures: { data: [] }, profileSourceUrl: null, imageLicenseUrl: null };
    assert.doesNotMatch(render(AuthorShow, input), /Edit author/);
    const html = render(AuthorShow, { ...input, editUrl: props.updateUrl });
    assert.match(html, /Edit author/);
    assert.match(html, /href="\/admin\/authors\/test-writer\/metadata"/);
    assert.match(html, /&lt;Writer&gt;/);
});
