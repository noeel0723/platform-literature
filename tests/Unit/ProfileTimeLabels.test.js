import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let TimeLabel;
let ProfileShow;

before(async () => {
    const virtual = 'virtual:profile-time-inertia';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'profile-time-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id === `\0${virtual}`) return `export { Link, router } from '@inertiajs/react'; export const Head = () => null;`;
            },
            transform(code, id) {
                if (id.endsWith('/Pages/Profile/Show.jsx')) return code.replace("from '@inertiajs/react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    ({ TimeLabel } = await server.ssrLoadModule('/resources/js/Components/LiteratureDetail/DetailUi.jsx'));
    ({ default: ProfileShow } = await server.ssrLoadModule('/resources/js/Pages/Profile/Show.jsx'));
});
after(async () => { await server?.close(); });

const renderTime = (props) => renderToStaticMarkup(React.createElement(TimeLabel, props));

test('completion calendar dates never turn midnight into hours ago or shift to the previous day', (t) => {
    t.mock.method(Date, 'now', () => Date.parse('2026-11-03T11:00:00Z'));
    const previousTimezone = process.env.TZ;
    try {
        for (const timezone of ['UTC', 'Asia/Makassar', 'America/Los_Angeles', 'Pacific/Kiritimati']) {
            process.env.TZ = timezone;
            const html = renderTime({ value: '2026-11-03T00:00:00+00:00', dateOnly: true });
            assert.match(html, /datetime="2026-11-03"/i);
            assert.match(html, />Nov 3, 2026</);
            assert.doesNotMatch(html, /ago|Nov 2/);
        }
    } finally {
        if (previousTimezone === undefined) delete process.env.TZ; else process.env.TZ = previousTimezone;
    }
});

test('precise review timestamps keep their actual elapsed time', (t) => {
    t.mock.method(Date, 'now', () => Date.parse('2026-11-03T10:25:45Z'));
    assert.match(renderTime({ value: '2026-11-03T08:25:45+00:00' }), />2 hours ago</);
    assert.match(renderTime({ value: '2026-11-03T10:25:30+00:00' }), />Just now</);
});

test('backdated completion dates retain the selected calendar day', () => {
    assert.match(renderTime({ value: '2026-08-23T00:00:00+00:00', dateOnly: true }), />Aug 23, 2026</);
});

test('profile distinguishes completion dates from review times for the same literature', (t) => {
    t.mock.method(Date, 'now', () => Date.parse('2026-11-03T10:25:45Z'));
    const literature = { id: 1, title: 'Crime and Punishment', initials: 'CP', url: '/literatures/crime-and-punishment', year: 1866 };
    const html = renderToStaticMarkup(React.createElement(ProfileShow, {
        profile: { name: 'Reader', username: 'reader', initials: 'R', is_owner: true, stats: { completed: 1, completed_this_year: 1, followers: 0, following: 0, readlist: 0, reviews: 1 } },
        navigation: { links: [] }, favoriteLiteratures: [], favoriteAuthors: [],
        recentCompletions: [{ literature, rating: 5, completed_at: '2026-11-03T00:00:00+00:00' }],
        recentReviews: [{ id: 205, literature, rating: 5, body: 'A review.', updated_at: '2026-11-03T08:25:45+00:00' }],
        readlistPreview: [], recentActivities: [], ratingDistribution: [], reportReasons: {},
        routes: { readlist: '/members/reader/readlist', diary: '/diary', activity: '/activity', reviews: '/members/reader/reviews' },
    }));
    assert.match(html, /Completed <time[^>]*datetime="2026-11-03"[^>]*>Nov 3, 2026<\/time>/i);
    assert.match(html, /Reviewed <time[^>]*datetime="2026-11-03T08:25:45\+00:00"[^>]*>2 hours ago<\/time>/i);
    assert.doesNotMatch(html, /10 hours ago/);
});
