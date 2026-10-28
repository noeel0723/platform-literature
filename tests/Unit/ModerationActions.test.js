import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let bindings;
let ModerationActions;
before(async () => {
    const moduleId = 'virtual:moderation-form-test';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'moderation-form-test', enforce: 'pre',
            resolveId(id) { if (id === moduleId) return `\0${moduleId}`; },
            load(id) {
                if (id !== `\0${moduleId}`) return;
                return `let form;
                    export const setForm = (value) => { form = value; };
                    export const useForm = () => form;
                    export const Head = () => null;
                    export const Link = () => null;
                    export const useState = (value) => [value, () => {}];`;
            },
            transform(code, id) {
                if (!id.endsWith('/resources/js/Pages/Admin/Moderation/Index.jsx')) return;
                return code.replace("from '@inertiajs/react'", `from '${moduleId}'`)
                    .replace("from 'react'", `from '${moduleId}'`) + '\nexport { ModerationActions };';
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    bindings = await server.ssrLoadModule(moduleId);
    ({ ModerationActions } = await server.ssrLoadModule('/resources/js/Pages/Admin/Moderation/Index.jsx'));
});
after(async () => { await server?.close(); });

function buttons(element) {
    if (!React.isValidElement(element)) return [];
    if (element.type === 'button') return [element];
    return React.Children.toArray(element.props.children).flatMap(buttons);
}

for (const [label, action] of [['Hide content', 'hide'], ['Deactivate account', 'deactivate'], ['Dismiss', 'dismiss']]) {
    test(`${label} submits the intended moderation action and note`, () => {
        const requests = [];
        let transform;
        let resets = 0;
        const form = {
            data: { action: '', resolution_note: 'Reviewed by moderator' }, errors: {}, processing: false,
            transform(callback) { transform = callback; },
            patch(url, options) { requests.push({ url, data: transform(this.data), options }); },
            reset() { resets++; },
        };
        bindings.setForm(form);
        const tree = ModerationActions({ report: { id: 1, status: 'pending', available_actions: ['hide', 'deactivate', 'dismiss'], update_url: '/admin/reports/1' } });

        buttons(tree).find((button) => button.props.children.trim() === label).props.onClick();

        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, '/admin/reports/1');
        assert.deepEqual(requests[0].data, { action, resolution_note: 'Reviewed by moderator' });
        requests[0].options.onSuccess();
        assert.equal(resets, 1);
    });
}
