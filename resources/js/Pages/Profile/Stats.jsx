import { Head } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

function Metric({ label, value }) {
    return (
        <div className="border-t-2 border-brand-coral bg-white/30 px-3 py-3">
            <strong className="block font-serif text-2xl text-ink-950">{value ?? '—'}</strong>
            <span className="mt-0.5 block text-[0.62rem] font-bold uppercase tracking-[0.14em] text-ink-950/50">{label}</span>
        </div>
    );
}

function Bars({ title, items, valueKey = 'count' }) {
    const max = Math.max(1, ...items.map((item) => item[valueKey]));

    return (
        <section>
            <h2 className="border-b border-ink-950/15 pb-2 text-xs font-bold uppercase tracking-[0.16em] text-ink-950/65">{title}</h2>
            {items.length === 0 ? <p className="py-5 text-sm text-ink-950/45">Not enough reading data yet.</p> : (
                <div className="mt-3 space-y-2.5">
                    {items.map((item) => (
                        <div key={item.label ?? item.year ?? item.month} className="grid grid-cols-[5rem_1fr_2rem] items-center gap-2 text-xs">
                            <span className="truncate text-ink-950/65">{item.label ?? item.year ?? item.month}</span>
                            <span className="h-2 bg-ink-950/8"><span className="block h-full bg-brand-coral" style={{ width: `${(item[valueKey] / max) * 100}%` }} /></span>
                            <strong className="text-right text-ink-950">{item[valueKey]}</strong>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export default function ProfileStats({ profile, navigation, stats }) {
    const summary = stats.summary;

    return (
        <>
            <Head title={`${profile.name} Stats`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="py-7 lg:py-9">
                <ProfilePageHeading eyebrow="Reading overview" title="Stats" count={`${stats.year} snapshot`} />

                <div className="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <Metric label="Completed" value={summary.completed} />
                    <Metric label="Readlist" value={summary.readlist} />
                    <Metric label="Reviews" value={summary.reviews} />
                    <Metric label="Average rating" value={summary.average_rating === null ? '—' : Number(summary.average_rating).toFixed(2)} />
                    <Metric label={`Completed ${stats.year}`} value={summary.completed_this_year} />
                    <Metric label="Active days" value={summary.active_days} />
                </div>

                <div className="mt-8 grid gap-8 md:grid-cols-2">
                    <Bars title="Rating distribution" items={stats.rating_distribution} />
                    <Bars title="Literature by type" items={stats.by_type} />
                    <Bars title="Top genres" items={stats.top_genres} />
                    <Bars title="Top authors" items={stats.top_authors} />
                    <Bars title={`Completed by month · ${stats.year}`} items={stats.completed_by_month} />
                    <Bars title="Reading activity by year" items={stats.activity_by_year} valueKey="days" />
                </div>
            </ProfileContentContainer>
        </>
    );
}
