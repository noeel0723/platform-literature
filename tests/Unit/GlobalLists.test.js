import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server, Lists, PublicListCard;

before(async () => {
    const virtual = 'virtual:global-lists-inertia';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'global-lists-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id === `\0${virtual}`) return `export { Link } from '@inertiajs/react'; export const Head = () => null;`;
            },
            transform(code, id) {
                if (id.endsWith('/Pages/Lists/Index.jsx')) return code.replace("from '@inertiajs/react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    ({ default: Lists } = await server.ssrLoadModule('/resources/js/Pages/Lists/Index.jsx'));
    ({ default: PublicListCard } = await server.ssrLoadModule('/resources/js/Components/PublicListCard.jsx'));
});
after(async () => { await server?.close(); });

const owner = { name: 'A Reader', initials: 'AR', url: '/members/a-reader', avatar_url: null };
const list = {
    id: 1, title: 'Favorite stories', description: 'A collection of stories.', url: '/lists/favorite-stories',
    items_count: 7, is_ranked: true, owner,
    previews: Array.from({ length: 7 }, (_, index) => ({ id: index + 1, title: `Story ${index + 1}`, cover_url: `/cover-${index + 1}.jpg`, initials: 'ST' })),
};
const page = (data = []) => ({ data, total: data.length, current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null });
const props = { officialLists: page([{ ...list, id: 2, title: 'Official picks' }]), readerLists: page([list]), routes: { create: '/lists/create/new', verification: null } };
const render = (Component, input) => renderToStaticMarkup(React.createElement(Component, input));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('global lists show Official Lists before Readers Lists with real list and owner links', () => {
    const html = render(Lists, props);
    assert.ok(html.indexOf('id="official-lists"') < html.indexOf('id="readers-lists"'));
    assert.match(html, /Official Lists/);
    assert.match(html, /Readers’ Lists/);
    assert.match(html, /href="\/lists\/favorite-stories"/);
    assert.match(html, /href="\/members\/a-reader"/);
    assert.match(html, /7 literature · Ranked/);
    assert.match(html, /href="\/lists\/create\/new"/);
    assert.doesNotMatch(html, /Edit list|Delete list|likes/);
});

test('cover strips are limited to five previews and empty lists have five decorative placeholders', () => {
    const html = render(PublicListCard, { list });
    assert.equal((html.match(/<img /g) ?? []).length, 5);
    assert.doesNotMatch(html, /cover-6|cover-7/);
    assert.match(html, /aria-label="View list: Favorite stories"/);
    const empty = render(PublicListCard, { list: { ...list, previews: [], items_count: 0, is_ranked: false } });
    assert.equal((empty.match(/border-dashed/g) ?? []).length, 5);
    assert.match(empty, /0 literature · Unranked/);
    assert.doesNotMatch(empty, /<img /);
});

test('each empty section remains visible with concise context and no fabricated lists', () => {
    const html = render(Lists, { ...props, officialLists: page(), readerLists: page() });
    assert.match(html, /No official lists yet/);
    assert.match(html, /No readers’ lists yet/);
    assert.match(html, /Public lists curated by the Literahaven team/);
    assert.equal((html.match(/<article/g) ?? []).length, 0);
});

test('guests open the existing login panel while unverified accounts are directed to verification', () => {
    const previousWindow = globalThis.window;
    const events = [];
    globalThis.window = { location: new URL('http://literahaven.test/lists'), dispatchEvent: (event) => events.push(event.type) };
    try {
        const tree = Lists({ ...props, routes: { create: null, verification: null } });
        elements(tree, 'button')[0].props.onClick();
        assert.deepEqual(events, ['literahaven:open-login']);
        const html = render(Lists, { ...props, routes: { create: null, verification: '/email/verify' } });
        assert.match(html, /href="\/email\/verify"/);
        assert.match(html, /Verify email to create a list/);
        assert.doesNotMatch(html, /href="\/lists\/create\/new"|Log in to create/);
    } finally {
        if (previousWindow === undefined) delete globalThis.window; else globalThis.window = previousWindow;
    }
});

test('pagination keeps each sections server-provided links and accessible navigation labels', () => {
    const html = render(Lists, {
        ...props,
        officialLists: { ...page(), total: 8, last_page: 2, next_page_url: '/lists?official_page=2#official-lists' },
        readerLists: { ...page([list]), total: 10, last_page: 2, next_page_url: '/lists?readers_page=2#readers-lists' },
    });
    assert.match(html, /aria-label="Official Lists pagination"/);
    assert.match(html, /aria-label="Readers’ Lists pagination"/);
    assert.match(html, /href="\/lists\?official_page=2#official-lists"/);
    assert.match(html, /href="\/lists\?readers_page=2#readers-lists"/);
});

test('titles, descriptions, and reader names are escaped and missing covers retain their fallback', () => {
    const html = render(PublicListCard, { list: { ...list, title: '<script>Title</script>', description: '<img onerror="bad()">', owner: { ...owner, name: '<b>Reader</b>' }, previews: [{ id: 8, title: 'No cover', cover_url: null, initials: 'NC' }] } });
    assert.match(html, /&lt;script&gt;Title/);
    assert.match(html, /&lt;img onerror=/);
    assert.match(html, /&lt;b&gt;Reader/);
    assert.match(html, />NC<\/span>/);
    assert.doesNotMatch(html, /<script>|<img onerror=/);
});
