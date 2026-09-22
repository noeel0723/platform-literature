import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { ProfileContentContainer } from '../../Components/ProfilePageUi';

const BREAKDOWN_TABS = [
    { key: 'rating', label: 'Rating' },
    { key: 'genre', label: 'Genre' },
    { key: 'authors', label: 'Authors' },
    { key: 'type', label: 'Type' },
];

function Metric({ label, value }) {
    return (
        <div className="group relative overflow-hidden rounded-lg border border-ink-950/10 bg-white/55 px-4 py-4 shadow-[0_3px_12px_rgba(16,47,98,0.04)] transition duration-200 before:absolute before:inset-x-0 before:top-0 before:h-0.5 before:origin-left before:scale-x-0 before:bg-brand-coral before:transition-transform hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(16,47,98,0.08)] hover:before:scale-x-100">
            <strong className="block font-serif text-3xl leading-none text-ink-950">{value ?? '—'}</strong>
            <span className="mt-3 block text-[0.62rem] font-bold uppercase tracking-[0.15em] text-ink-950/50">{label}</span>
        </div>
    );
}

function ChartTabs({ items, active, onChange, label }) {
    return (
        <div className="flex max-w-full gap-1 overflow-x-auto pb-1" role="group" aria-label={label}>
            {items.map((item) => {
                const isActive = item.key === active;

                return (
                    <button
                        key={item.key}
                        type="button"
                        aria-pressed={isActive}
                        onClick={() => onChange(item.key)}
                        className={`shrink-0 rounded-md border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral ${isActive ? 'border-ink-950/10 bg-white text-ink-950 shadow-sm' : 'border-transparent bg-transparent text-ink-950/50 hover:bg-white/45 hover:text-ink-950'}`}
                    >
                        {item.label}
                    </button>
                );
            })}
        </div>
    );
}

function HorizontalBarList({ items, valueKey = 'count', showZeroRows = false }) {
    const values = items.map((item) => Number(item[valueKey] ?? 0));
    const max = Math.max(1, ...values);
    const hasData = values.some((value) => value > 0);

    if (items.length === 0 || (!hasData && !showZeroRows)) {
        return <p className="rounded-md border border-dashed border-ink-950/15 bg-brand-cream/45 px-4 py-6 text-center text-sm text-ink-950/45">Not enough reading data yet.</p>;
    }

    return (
        <div className="space-y-1.5">
            {items.map((item) => {
                const label = item.label ?? item.month ?? item.year;
                const value = Number(item[valueKey] ?? 0);

                return (
                    <div key={label} className="group grid grid-cols-[5.5rem_minmax(0,1fr)_2.25rem] items-center gap-2 rounded px-1.5 py-1 text-xs transition hover:bg-brand-sky/10 sm:grid-cols-[7rem_minmax(0,1fr)_2.5rem]">
                        <span className="truncate text-right font-medium text-ink-950/65" title={String(label)}>{label}</span>
                        <span className="h-2 overflow-hidden rounded-full bg-ink-950/10" aria-hidden="true">
                            <span
                                className="block h-full origin-left rounded-full bg-brand-coral transition duration-200 group-hover:opacity-85 group-hover:brightness-95"
                                style={{ width: `${(value / max) * 100}%` }}
                            />
                        </span>
                        <strong className="text-right tabular-nums text-ink-950 transition group-hover:text-brand-coral">{value}</strong>
                    </div>
                );
            })}
        </div>
    );
}

function StatsCard({ eyebrow, total, controls, children }) {
    return (
        <section className="rounded-xl border border-ink-950/10 bg-white/45 p-4 shadow-[0_4px_16px_rgba(16,47,98,0.035)] sm:p-5">
            <header className="flex flex-col gap-3 border-b border-ink-950/10 pb-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-brand-stem">{eyebrow}</h2>
                    <p className="mt-1 text-xs text-ink-950/40">{total} total</p>
                </div>
                {controls}
            </header>
            <div className="mt-4">{children}</div>
        </section>
    );
}

export default function ProfileStats({ profile, navigation, stats }) {
    const [breakdown, setBreakdown] = useState('rating');
    const [activityMode, setActivityMode] = useState('month');
    const summary = stats.summary;
    const breakdownData = {
        rating: stats.rating_distribution,
        genre: stats.top_genres,
        authors: stats.top_authors,
        type: stats.by_type,
    };
    const breakdownItems = breakdownData[breakdown] ?? [];
    const breakdownTotal = breakdownItems.reduce((total, item) => total + Number(item.count ?? 0), 0);
    const activityItems = activityMode === 'month' ? stats.completed_by_month : stats.completed_by_year;
    const activityTotal = activityItems.reduce((total, item) => total + Number(item.count ?? 0), 0);
    const statsUrl = navigation.links.find((item) => item.key === 'stats')?.url;
    const yearOptions = useMemo(() => [...new Set([
        stats.year,
        ...stats.completed_by_year.map((item) => item.year),
    ])].sort((left, right) => right - left), [stats.completed_by_year, stats.year]);

    const changeYear = (event) => {
        if (!statsUrl) return;

        router.get(statsUrl, { year: Number(event.target.value) }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['stats'],
        });
    };

    return (
        <>
            <Head title={`${profile.name} Stats`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="max-w-6xl py-7 lg:py-9">
                <div className="mx-auto max-w-6xl">
                    <header className="flex items-center gap-3">
                        <span className="h-7 w-1.5 rounded-full bg-brand-coral" aria-hidden="true" />
                        <h1 className="text-xl font-bold tracking-tight text-ink-950 sm:text-2xl">My Reading Stats</h1>
                    </header>

                    <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <Metric label="Completed" value={summary.completed} />
                        <Metric label="Readlist" value={summary.readlist} />
                        <Metric label="Reviews" value={summary.reviews} />
                        <Metric label="Average rating" value={summary.average_rating === null ? '—' : Number(summary.average_rating).toFixed(2)} />
                        <Metric label={`Completed ${stats.year}`} value={summary.completed_this_year} />
                        <Metric label="Active days" value={summary.active_days} />
                    </div>

                    <div className="mt-7 grid gap-5 lg:grid-cols-2">
                        <StatsCard
                            eyebrow="Breakdown"
                            total={breakdownTotal}
                            controls={<ChartTabs items={BREAKDOWN_TABS} active={breakdown} onChange={setBreakdown} label="Reading breakdown" />}
                        >
                            <HorizontalBarList items={breakdownItems} showZeroRows={breakdown === 'rating'} />
                        </StatsCard>

                        <StatsCard
                            eyebrow="Reading activity"
                            total={activityTotal}
                            controls={(
                                <div className="flex max-w-full items-center gap-2 overflow-x-auto pb-1">
                                    <ChartTabs
                                        items={[
                                            { key: 'month', label: `By month · ${stats.year}` },
                                            { key: 'year', label: 'By year' },
                                        ]}
                                        active={activityMode}
                                        onChange={setActivityMode}
                                        label="Reading activity period"
                                    />
                                    {activityMode === 'month' && (
                                        <select
                                            aria-label="Select statistics year"
                                            value={stats.year}
                                            onChange={changeYear}
                                            className="h-8 rounded-md border border-ink-950/10 bg-white px-2 text-xs font-semibold text-ink-950 outline-none transition focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/30"
                                        >
                                            {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                                        </select>
                                    )}
                                </div>
                            )}
                        >
                            <HorizontalBarList items={activityItems} />
                        </StatsCard>
                    </div>
                </div>
            </ProfileContentContainer>
        </>
    );
}
