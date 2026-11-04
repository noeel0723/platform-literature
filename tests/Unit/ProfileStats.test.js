import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import react from '@vitejs/plugin-react';
import { createServer } from 'vite';

let server;
let controls;
let ProfileStats;
let charts;

before(async () => {
    const virtual = 'virtual:stats-controls';
    server = await createServer({
        configFile: false,
        plugins: [{
            name: 'profile-stats-test', enforce: 'pre',
            resolveId(id) { if (id === virtual) return `\0${virtual}`; },
            load(id) {
                if (id !== `\0${virtual}`) return;
                return `export { Link } from '@inertiajs/react'; export const Head = () => null;
                    export let mode = 'year'; export const calls = [];
                    export const reset = (value = 'year') => { mode = value; calls.length = 0; };
                    export const useState = () => [mode, (value) => { mode = value; }];
                    export const useMemo = (callback) => callback();
                    export const router = { get: (...args) => calls.push(args) };`;
            },
            transform(code, id) {
                if (id.endsWith('/Pages/Profile/Stats.jsx')) return code
                    .replace("from '@inertiajs/react'", `from '${virtual}'`)
                    .replace("from 'react'", `from '${virtual}'`);
            },
        }, react()],
        optimizeDeps: { noDiscovery: true, include: [] },
        server: { middlewareMode: true, hmr: false, watch: null }, appType: 'custom',
    });
    controls = await server.ssrLoadModule(virtual);
    charts = await server.ssrLoadModule('/resources/js/Components/ProfileStatsCharts.jsx');
    ({ default: ProfileStats } = await server.ssrLoadModule('/resources/js/Pages/Profile/Stats.jsx'));
});
after(async () => { await server?.close(); });

const props = {
    profile: { name: 'Reader', url: '/members/reader', initials: 'R', avatar_url: null },
    navigation: { links: [{ key: 'stats', label: 'Stats', url: '/members/reader/stats' }], current: 'stats' },
    stats: {
        year: 2026,
        summary: { completed: 7, readlist: 2, reviews: 3, average_rating: 4.25, completed_this_year: 5, active_days: 12 },
        completed_by_year: [{ year: 2024, count: 2 }, { year: 2026, count: 5 }],
        completed_by_month: [{ month: 'Jan', count: 1 }, { month: 'Feb', count: 4 }],
        activity_by_year: [{ year: 2024, days: 3 }, { year: 2026, days: 9 }],
        rating_distribution: [{ label: '4.0', count: 1 }, { label: '4.5', count: 1 }],
        by_type: [{ label: 'Novel', count: 5 }, { label: 'Manga', count: 2 }],
        top_genres: [{ label: 'Fantasy', count: 3 }], top_authors: [{ label: 'A. Writer', count: 2 }],
    },
};
const render = (Component, input) => renderToStaticMarkup(React.createElement(Component, input));
function elements(element, type) {
    if (!React.isValidElement(element)) return [];
    return [...(element.type === type ? [element] : []), ...React.Children.toArray(element.props.children).flatMap((child) => elements(child, type))];
}

test('stats show the readers real summary, year totals, rating distribution, and all available breakdowns', () => {
    controls.reset();
    const html = render(ProfileStats, props);
    assert.match(html, /A life in literature/);
    assert.match(html, /href="\/members\/reader"/);
    for (const label of ['Readlist', 'Reviews', 'Average rating', 'Completed 2026', 'Active days', 'Your ratings', 'Formats', 'Most read genres', 'Most read authors', 'Fantasy', 'A. Writer']) assert.ok(html.includes(label), label);
    assert.match(html, /2024: 2 completed/);
    assert.match(html, /2026: 5 completed/);
    assert.match(html, /4.25/);
});

test('activity controls switch to months and keep the existing Inertia year navigation', () => {
    controls.reset();
    const activity = elements(ProfileStats(props), charts.StatsSection)[0];
    const tabs = elements(activity.props.aside, charts.ChartTabs)[0];
    const buttons = elements(charts.ChartTabs(tabs.props), 'button');
    assert.equal(buttons.find((button) => button.props.children === 'By year').props['aria-pressed'], true);
    buttons.find((button) => button.props.children === 'By month').props.onClick();
    assert.equal(controls.mode, 'month');

    const monthlyActivity = elements(ProfileStats(props), charts.StatsSection)[0];
    const select = elements(monthlyActivity.props.aside, 'select')[0];
    assert.deepEqual(elements(select, 'option').map((option) => option.props.value), [2026, 2024]);
    select.props.onChange({ target: { value: '2024' } });
    assert.deepEqual(controls.calls[0], ['/members/reader/stats', { year: 2024 }, { preserveScroll: true, preserveState: true, replace: true, only: ['stats'] }]);
    assert.match(render(ProfileStats, props), /Jan: 1 completed/);
});

test('active days use actual day totals instead of completed totals', () => {
    controls.reset('days');
    const html = render(ProfileStats, props);
    assert.match(html, /2026: 9 active days/);
    assert.match(html, /12 active days across your reading history/);
    assert.doesNotMatch(html, /2026: 5 completed/);
});

test('chart values are available to keyboard and screen readers and zero data has a compact empty state', () => {
    const html = render(charts.VerticalBarChart, { label: 'Completed by year', items: [{ label: '2026', value: 3 }] });
    assert.match(html, /tab[Ii]ndex="0"/);
    assert.match(html, /aria-label="2026: 3 completed"/);
    const empty = render(charts.VerticalBarChart, { label: 'Completed by year', items: [{ label: '2026', value: 0 }] });
    assert.match(empty, /No completed literature for this period/);
    assert.doesNotMatch(empty, /role="img"/);
});

test('empty profiles display honest empty states and a missing average while long metadata is escaped', () => {
    controls.reset();
    const html = render(ProfileStats, { ...props, stats: { ...props.stats, summary: { ...props.stats.summary, average_rating: null }, completed_by_year: [], completed_by_month: [], activity_by_year: [], rating_distribution: [], by_type: [], top_genres: [], top_authors: [] } });
    assert.match(html, /No ratings yet/);
    assert.match(html, /No genre data yet/);
    assert.match(html, /No author data yet/);
    assert.match(html, /—/);
    const bar = render(charts.HorizontalBarList, { items: [{ label: '<script>Long author name</script>', count: 2 }] });
    assert.match(bar, /&lt;script&gt;Long author name/);
    assert.doesNotMatch(bar, /<script>/);
});
