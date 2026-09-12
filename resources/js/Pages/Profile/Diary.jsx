import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { StarRating } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

function DateParts({ value }) {
    const date = new Date(value);

    return (
        <>
            <div className="row-span-2 self-start text-center md:row-span-1 md:text-left">
                <time dateTime={value} data-local-date-part="month" className="block text-[0.68rem] font-bold uppercase tracking-[0.12em] text-ink-950/55">{new Intl.DateTimeFormat('en', { month: 'short' }).format(date)}</time>
                <time dateTime={value} data-local-date-part="year" className="mt-0.5 block text-[0.68rem] text-ink-950/45">{date.getFullYear()}</time>
            </div>
            <time dateTime={value} data-local-date-part="day" className="text-2xl text-ink-950/60">{String(date.getDate()).padStart(2, '0')}</time>
        </>
    );
}

export default function ProfileDiary({ profile, navigation, activities }) {
    return (
        <>
            <Head title="Personal Diary" />
            <ProfileSubNavigation navigation={navigation} />

            <ProfileContentContainer className="py-7 lg:py-9" aria-labelledby="activity-history-heading">
                <ProfilePageHeading eyebrow="Personal reading record" title="Activity history" count={`${activities.length} rated ${activities.length === 1 ? 'literature' : 'literatures'}`} id="activity-history-heading" />

                <div className="mt-4 overflow-x-auto" data-diary-activity-history>
                    <div className="hidden min-w-[760px] grid-cols-[72px_54px_minmax(230px,1fr)_70px_125px_65px_50px] border-y border-ink-950/15 px-2 py-2 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950/45 md:grid" role="row">
                        <span>Month</span><span>Day</span><span>Literature</span><span>Released</span><span>Rating</span><span>Review</span><span>Edit</span>
                    </div>

                    <ol className="min-w-0 md:min-w-[760px]">
                        {activities.length ? activities.map((activity) => (
                            <li key={activity.review_id} className="grid grid-cols-[54px_minmax(0,1fr)] border-b border-ink-950/10 px-2 py-3 md:grid-cols-[72px_54px_minmax(230px,1fr)_70px_125px_65px_50px] md:items-center md:py-2.5" data-diary-rating-row>
                                <DateParts value={activity.occurred_at} />
                                <Link href={activity.literature.url} className="group col-start-2 mt-1.5 flex min-w-0 items-center gap-2.5 md:col-start-auto md:mt-0">
                                    <LiteratureCover literature={activity.literature} alt={false} className="h-12 w-8 shrink-0 rounded-sm" />
                                    <span className="min-w-0"><span className="block truncate text-base font-bold text-ink-950 transition group-hover:text-brand-coral">{activity.literature.title}</span><span className="mt-0.5 block truncate text-[0.68rem] text-ink-950/50">{activity.literature.author}</span></span>
                                </Link>
                                <span className="hidden text-xs text-ink-950/60 md:block">{activity.literature.year ?? '—'}</span>
                                <div className="col-start-2 mt-2 flex items-center gap-1.5 md:col-start-auto md:mt-0"><StarRating rating={activity.rating} size="text-xs" /><span className="text-[0.68rem] font-bold text-ink-950/50">{Number(activity.rating).toFixed(1)}</span></div>
                                <span className="hidden text-xs font-semibold text-ink-950/55 md:block">{activity.review ? (activity.contains_spoiler ? 'Spoiler' : 'Written') : '—'}</span>
                                {activity.edit_url ? <Link href={activity.edit_url} className="hidden text-xs font-bold text-brand-coral hover:underline md:inline" aria-label={`Edit rating or review for ${activity.literature.title}`}>Edit</Link> : <span className="hidden text-xs text-ink-950/25 md:inline">—</span>}
                            </li>
                        )) : <li className="border-b border-ink-950/10 px-5 py-9 text-center text-sm text-ink-950/60">No rated literature yet. Give a title a rating to add it to your Diary.</li>}
                    </ol>
                </div>
            </ProfileContentContainer>
        </>
    );
}
