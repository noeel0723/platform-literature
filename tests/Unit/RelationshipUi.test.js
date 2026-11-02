import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let RelationshipBadge;
let ProfileOverview;
let Connections;
let Activity;
before(async () => {
    const moduleId = '\0relationship-ui-inertia';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'relationship-ui-inertia', enforce: 'pre',
            resolveId(id) { if (id === 'virtual:relationship-ui-inertia') return moduleId; },
            load(id) {
                if (id === moduleId) return `import React from 'react'; export const Head = () => null;
                    export const Link = ({ href, children }) => React.createElement('a', { href }, children);
                    export const router = { post() {}, delete() {} };`;
            },
            transform(code, id) {
                if (id.endsWith('/Pages/Profile/Connections.jsx') || id.endsWith('/Pages/Activity/Index.jsx')) return code.replace("from '@inertiajs/react'", "from 'virtual:relationship-ui-inertia'");
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    ({ default: RelationshipBadge } = await server.ssrLoadModule('/resources/js/Components/RelationshipBadge.jsx'));
    ({ ProfileOverview } = await server.ssrLoadModule('/resources/js/Pages/Profile/Show.jsx'));
    ({ default: Connections } = await server.ssrLoadModule('/resources/js/Pages/Profile/Connections.jsx'));
    ({ default: Activity } = await server.ssrLoadModule('/resources/js/Pages/Activity/Index.jsx'));
});
after(async () => { await server?.close(); });
const render = (Component, props) => renderToStaticMarkup(React.createElement(Component, props));

test('Friends requires mutual following and one-way relationships retain their own labels', () => {
    assert.match(render(RelationshipBadge, { following: true, followsYou: true }), />Friends</);
    assert.match(render(RelationshipBadge, { following: true, showFollowing: true }), />Following</);
    assert.match(render(RelationshipBadge, { followsYou: true }), />Follows you</);
    assert.equal(render(RelationshipBadge, {}), '');
    assert.equal(render(RelationshipBadge, { following: true, followsYou: true, hidden: true }), '');
});

test('profile overview only marks an actual friend and never marks the current users own profile', () => {
    const profile = { name: 'Reader', username: 'reader', initials: 'R', is_owner: false, is_following: true, is_friend: true, viewer_authenticated: false, stats: { completed: 0, completed_this_year: 0, followers: 1, following: 1 } };
    const props = { profile, routes: { login: '/login' }, reportReasons: {} };
    assert.match(render(ProfileOverview, props), />Friends</);
    assert.doesNotMatch(render(ProfileOverview, { ...props, profile: { ...profile, is_friend: false } }), />Friends</);
    assert.doesNotMatch(render(ProfileOverview, { ...props, profile: { ...profile, is_owner: true } }), />Friends</);
});

test('Connections labels mutual rows as Friends while one-way and blocked rows stay distinct', () => {
    const connection = { name: 'Mutual Reader', username: 'mutual', url: '/members/mutual', initials: 'MR', viewer_follows: true, follows_viewer: true, followers_count: 1, following_count: 1, completed_count: 0, readlist_count: 0 };
    const props = { profile: { name: 'Owner' }, navigation: { links: [] }, connections: { data: [connection], last_page: 1 }, relationship: 'followers', canManage: false, counts: { following: 1, followers: 1 }, routes: {} };
    assert.match(render(Connections, props), />Friends</);
    assert.match(render(Connections, { ...props, connections: { ...props.connections, data: [{ ...connection, viewer_follows: false }] } }), />Follows you</);
    assert.doesNotMatch(render(Connections, { ...props, relationship: 'blocked' }), />Friends<|>Follows you</);
    assert.doesNotMatch(render(Connections, { ...props, connections: { ...props.connections, data: [{ ...connection, is_blocked: true }] } }), />Friends<|>Follows you</);
});

test('Activity keeps the existing stream and scope tabs without the right filter card', () => {
    const activity = { id: 1, type: 'added_to_readlist', is_expanded: false, action: 'added to Readlist', occurred_at: '2026-11-02T12:00:00Z', user: { name: 'Reader', initial: 'R', url: '/members/reader' }, literature: { title: 'Earth', url: '/literatures/earth' } };
    const html = render(Activity, { navigation: { links: [] }, activities: { data: [activity], links: [] }, scope: 'you', scopes: [{ key: 'you', short_label: 'You', url: '/activity?scope=you' }], heading: 'Reader activity' });
    assert.match(html, /Reader activity/);
    assert.match(html, /Earth/);
    assert.match(html, /data-activity-stream/);
    assert.match(html, /Activity scopes/);
    assert.doesNotMatch(html, /<aside|Activity filters|Choose your stream|activity-filter-heading/);
});
