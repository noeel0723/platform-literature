import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { ProfileContentContainer } from '../../Components/ProfilePageUi';
import { ChartTabs, HorizontalBarList, StatsSection, VerticalBarChart } from '../../Components/ProfileStatsCharts';

const ACTIVITY_TABS = [
    { key: 'year', label: 'By year' },
    { key: 'month', label: 'By month' },
    { key: 'days', label: 'Active days' },
];
const number = (value) => Number(value ?? 0).toLocaleString('en');

function Metric({ label, value }) {
    return (
        <div className="flex min-w-0 flex-col px-2 py-3 text-center sm:py-4">
            <dt className="order-2 mt-2 text-[9px] font-semibold uppercase leading-4 tracking-[0.12em] text-ink-950/55 sm:text-[10px]">{label}</dt>
            <dd className="order-1 font-serif text-2xl font-bold leading-none tabular-nums tracking-tight text-ink-950 sm:text-3xl">{value}</dd>
        </div>
    );
}

export default function ProfileStats({ profile, navigation, stats }) {
    const [activityMode, setActivityMode] = useState('year');
    const summary = stats.summary;
    const statsUrl = navigation.links.find((item) => item.key === 'stats')?.url;
    const yearOptions = useMemo(() => [...new Set([
        stats.year,
        ...stats.completed_by_year.map((item) => item.year),
    ])].sort((left, right) => right - left), [stats.completed_by_year, stats.year]);
    const activityItems = activityMode === 'month'
        ? stats.completed_by_month.map((item) => ({ label: item.month, value: item.count }))
        : activityMode === 'days'
            ? stats.activity_by_year.map((item) => ({ label: String(item.year), value: item.days }))
            : stats.completed_by_year.map((item) => ({ label: String(item.year), value: item.count }));
    const activityTotal = activityItems.reduce((total, item) => total + item.value, 0);
    const ratingTotal = stats.rating_distribution.reduce((total, item) => total + item.count, 0);

    const changeYear = (event) => {
        if (!statsUrl) return;
        router.get(statsUrl, { year: Number(event.target.value) }, {
            preserveScroll: true, preserveState: true, replace: true, only: ['stats'],
        });
    };

    return (
        <>
            <Head title={`${profile.name} Stats`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="pb-9 pt-5 sm:pb-12 sm:pt-6" aria-labelledby="stats-heading">
                <div className="mx-auto max-w-4xl">
                    <header className="relative isolate overflow-hidden border-b border-ink-950/15 py-8 text-center sm:py-10">
                        <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 opacity-60 [mask-image:linear-gradient(to_right,transparent,black_25%,black_75%,transparent)]" style={{ backgroundImage: 'linear-gradient(rgb(16 47 98 / 0.06) 1px, transparent 1px), linear-gradient(90deg, rgb(16 47 98 / 0.06) 1px, transparent 1px)', backgroundSize: '48px 48px' }} />
                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-coral">Reading statistics</p>
                        <h1 id="stats-heading" className="mt-3 font-serif text-3xl font-bold tracking-tight text-ink-950 sm:text-5xl">A life in literature</h1>
                        <div className="mt-4 flex min-w-0 flex-wrap items-center justify-center gap-2 text-xs text-ink-950/60 sm:text-sm">
                            <Link href={profile.url} className="inline-flex min-w-0 max-w-full items-center gap-2 font-semibold text-ink-950 hover:text-brand-coral focus-visible:underline">
                                <span className="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full border border-ink-950/10 bg-brand-sky/25 text-[10px]">{profile.avatar_url ? <img src={profile.avatar_url} alt="" className="size-full object-cover" /> : profile.initials}</span>
                                <span className="truncate">{profile.name}</span>
                            </Link>
                            <span>All-time stats</span>
                        </div>
                    </header>

                    <dl className="grid grid-cols-2 gap-y-1 border-b border-ink-950/15 py-4 sm:grid-cols-3 lg:grid-cols-6">
                        <Metric label="Completed" value={number(summary.completed)} />
                        <Metric label="Readlist" value={number(summary.readlist)} />
                        <Metric label="Reviews" value={number(summary.reviews)} />
                        <Metric label="Average rating" value={summary.average_rating === null ? '—' : Number(summary.average_rating).toFixed(2)} />
                        <Metric label={`Completed ${stats.year}`} value={number(summary.completed_this_year)} />
                        <Metric label="Active days" value={number(summary.active_days)} />
                    </dl>

                    <StatsSection id="reading-activity-heading" title="Reading activity" className="mt-8" aside={
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <ChartTabs items={ACTIVITY_TABS} active={activityMode} onChange={setActivityMode} label="Reading activity period" />
                            {activityMode === 'month' && <select aria-label="Select statistics year" value={stats.year} onChange={changeYear} className="h-7 rounded-sm border border-ink-950/15 bg-white/50 px-2 text-[11px] font-semibold text-ink-950 outline-none focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20">
                                {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                            </select>}
                        </div>
                    }>
                        <p className="mb-4 text-xs text-ink-950/55">{number(activityTotal)} {activityMode === 'days' ? 'active days across your reading history' : `completed ${activityMode === 'month' ? `in ${stats.year}` : 'across your reading history'}`}</p>
                        <VerticalBarChart items={activityItems} label={activityMode === 'days' ? 'Active reading days by year' : activityMode === 'month' ? `Completed literature by month in ${stats.year}` : 'Completed literature by year'} unit={activityMode === 'days' ? 'active days' : 'completed'} tone={activityMode === 'days' ? 'bg-ink-950/70' : 'bg-brand-coral'} emptyTitle={activityMode === 'days' ? 'No reading activity yet.' : 'No completed literature for this period.'} />
                    </StatsSection>

                    <StatsSection id="rating-distribution-heading" title="Your ratings" className="mt-8" aside={<span className="text-xs text-ink-950/55">{number(ratingTotal)} {ratingTotal === 1 ? 'rating' : 'ratings'} <span aria-hidden="true">·</span> 5-star scale</span>}>
                        <VerticalBarChart items={stats.rating_distribution.map((item) => ({ label: item.label, value: item.count }))} label="Rating distribution from half a star to five stars" unit="ratings" tone="bg-ink-950/65" compact emptyTitle="No ratings yet." />
                    </StatsSection>

                    <StatsSection id="reading-interests-heading" title="Reading interests" className="mt-8" aside={<span className="text-xs text-ink-950/55">From completed literature</span>}>
                        <div className="grid gap-7 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                            <section aria-labelledby="stats-formats-heading" className="min-w-0">
                                <h3 id="stats-formats-heading" className="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-950/55">Formats</h3>
                                <HorizontalBarList items={stats.by_type} tone="bg-brand-coral/20" />
                            </section>
                            <section aria-labelledby="stats-genres-heading" className="min-w-0">
                                <h3 id="stats-genres-heading" className="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-950/55">Most read genres</h3>
                                <HorizontalBarList items={stats.top_genres} tone="bg-brand-sky/35" emptyTitle="No genre data yet." />
                            </section>
                            <section aria-labelledby="stats-authors-heading" className="min-w-0 sm:col-span-2 lg:col-span-1">
                                <h3 id="stats-authors-heading" className="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-950/55">Most read authors</h3>
                                <HorizontalBarList items={stats.top_authors} tone="bg-ink-950/10" emptyTitle="No author data yet." />
                            </section>
                        </div>
                    </StatsSection>
                    <p className="mt-7 border-t border-ink-950/10 pt-4 text-center text-[11px] text-ink-950/45">Your reading history, one story at a time.</p>
                </div>
            </ProfileContentContainer>
        </>
    );
}
