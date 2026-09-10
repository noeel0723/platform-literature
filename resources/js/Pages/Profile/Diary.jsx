import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { StarRating } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, ProfilePageHeading } from '../../Components/ProfilePageUi';

function DateParts({ value }) {
    const date = new Date(value);

    return (
        <>
            <div className="row-span-2 self-start text-center md:row-span-1 md:text-left">
                <time dateTime={value} data-local-date-part="month" className="block text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">{new Intl.DateTimeFormat('en', { month: 'short' }).format(date)}</time>
                <time dateTime={value} data-local-date-part="year" className="mt-1 block text-xs text-ink-950/45">{date.getFullYear()}</time>
            </div>
            <time dateTime={value} data-local-date-part="day" className="text-3xl text-ink-950/60">{String(date.getDate()).padStart(2, '0')}</time>
        </>
    );
}

export default function ProfileDiary({ profile, navigation, activities }) {
    return (
        <>
            <Head title="Personal Diary" />
            <ProfileSubNavigation navigation={navigation} />

            <section className="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10" aria-labelledby="activity-history-heading">
                <ProfilePageHeading eyebrow="Personal reading record" title="Activity history" count={`${activities.length} rated ${activities.length === 1 ? 'literature' : 'literatures'}`} id="activity-history-heading" />

                <div className="mt-5 overflow-x-auto border border-ink-950/10 bg-white/25" data-diary-activity-history>
                    <div className="hidden min-w-[850px] grid-cols-[100px_90px_minmax(280px,1fr)_110px_190px_90px_70px] border-b border-ink-950/15 bg-brand-cream/65 px-4 py-3 text-xs font-bold uppercase tracking-wider text-ink-950/50 md:grid" role="row">
                        <span>Month</span><span>Day</span><span>Literature</span><span>Released</span><span>Rating</span><span>Review</span><span>Edit</span>
                    </div>

                    <ol className="min-w-0 md:min-w-[850px]">
                        {activities.length ? activities.map((activity) => (
                            <li key={activity.review_id} className="grid grid-cols-[68px_minmax(0,1fr)] border-b border-ink-950/10 px-4 py-4 last:border-b-0 md:grid-cols-[100px_90px_minmax(280px,1fr)_110px_190px_90px_70px] md:items-center md:py-3" data-diary-rating-row>
                                <DateParts value={activity.occurred_at} />
                                <Link href={activity.literature.url} className="group col-start-2 mt-2 flex min-w-0 items-center gap-3 md:col-start-auto md:mt-0">
                                    <LiteratureCover literature={activity.literature} alt={false} className="h-16 w-11 shrink-0" />
                                    <span className="min-w-0"><span className="block truncate text-xl font-bold text-ink-950 transition group-hover:text-brand-coral">{activity.literature.title}</span><span className="mt-1 block truncate text-xs text-ink-950/50">{activity.literature.author}</span></span>
                                </Link>
                                <span className="hidden text-sm text-ink-950/60 md:block">{activity.literature.year ?? '—'}</span>
                                <div className="col-start-2 mt-3 flex items-center gap-2 md:col-start-auto md:mt-0"><StarRating rating={activity.rating} size="text-sm" /><span className="text-xs font-bold text-ink-950/50">{Number(activity.rating).toFixed(1)}</span></div>
                                <span className="hidden text-sm font-semibold text-ink-950/55 md:block">{activity.review ? (activity.contains_spoiler ? 'Spoiler' : 'Written') : '—'}</span>
                                <Link href={activity.edit_url} className="hidden text-sm font-bold text-brand-coral hover:underline md:inline" aria-label={`Edit rating or review for ${activity.literature.title}`}>Edit</Link>
                            </li>
                        )) : <li className="p-8 text-center text-ink-950/60">No rated literature yet. Give a title a rating to add it to your Diary.</li>}
                    </ol>
                </div>
            </section>
        </>
    );
}
