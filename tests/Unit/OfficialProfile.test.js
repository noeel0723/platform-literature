import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let ProfileOverview;
let OfficialListUpdates;
before(async () => {
    server = await createServer({ configFile: false, plugins: [react()], optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom' });
    ({ ProfileOverview } = await server.ssrLoadModule('/resources/js/Pages/Profile/Show.jsx'));
    ({ OfficialListUpdates } = await server.ssrLoadModule('/resources/js/Pages/Profile/Official.jsx'));
});
after(async () => { await server?.close(); });

const profile = { name: 'Literahaven', username: 'official', is_official: true, initials: 'LH', member_since: 'November 2026', stats: { lists: 2, followers: 3 }, is_owner: false, viewer_authenticated: false };
const routes = { login: '/login', lists: '/members/official/lists', followers: '/members/official/connections?relationship=followers', create_list: null };
const overview = (changes = {}, routeChanges = {}) => renderToStaticMarkup(React.createElement(ProfileOverview, { profile: { ...profile, ...changes }, routes: { ...routes, ...routeChanges }, reportReasons: {} }));

test('official profile identifies the account and shows only lists and followers statistics', () => {
    const html = overview();
    assert.match(html, /Literahaven official/);
    assert.match(html, /Official/);
    assert.match(html, />Lists</);
    assert.match(html, />Followers</);
    assert.match(html, /Curated literature lists and the latest updates from Literahaven/);
    assert.doesNotMatch(html, /Completed|This year|>Following<|This reader has not added/);
});

test('owner profile editing requires a real destination and blocked viewers get no follow control', () => {
    assert.match(overview({ is_owner: true }, { edit: '/profile/edit' }), /Edit profile/);
    assert.doesNotMatch(overview({ is_owner: true }, { edit: null }), /Edit profile/);
    assert.doesNotMatch(overview({ follow_blocked: true }), /Log in to follow/);
    assert.match(overview({}, { verification: '/email/verify' }), /Verify email to follow/);
});

test('official updates render actual list previews and update time with existing destinations', () => {
    const list = { id: 1, title: 'Essential Manga', description: 'A curated selection.', items_count: 1, is_private: false, is_ranked: true, updated_at: '2026-11-01T08:00:00Z', url: '/lists/essential', edit_url: null, previews: [{ id: 1, title: 'Naruto', cover_url: null, initials: 'NA' }] };
    const html = renderToStaticMarkup(React.createElement(OfficialListUpdates, { lists: { data: [list], last_page: 1 }, routes }));
    assert.match(html, /Latest list updates/);
    assert.match(html, /href="\/lists\/essential"/);
    assert.match(html, /Essential Manga/);
    assert.match(html, /Updated <time/);
    assert.match(html, /1 literature · Public · Ranked/);
    assert.doesNotMatch(html, /New list|✎/);
});

test('empty official updates only offer creation when it is authorized', () => {
    const render = (create_list) => renderToStaticMarkup(React.createElement(OfficialListUpdates, { lists: { data: [], last_page: 1 }, routes: { ...routes, create_list } }));
    assert.match(render(null), /No official lists yet/);
    assert.doesNotMatch(render(null), /Create a list|New list/);
    assert.match(render('/lists/create'), /Create a list/);
});
