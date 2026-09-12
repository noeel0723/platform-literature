import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { StarRating, TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import { ProfileContentContainer } from '../../Components/ProfilePageUi';

function ActivityItem({ activity }) {
    const isDiscussion = ['discussion', 'comment'].includes(activity.type);

    return (
        <article className="border-b border-ink-950/10 py-3" data-activity-item data-activity-type={activity.type}>
            <div className="flex items-start gap-2.5">
                <Link href={activity.user.url} className="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-[10px] font-bold text-brand-cream">
                    {activity.user.avatar_url ? <img src={activity.user.avatar_url} alt="" className="size-full object-cover" /> : activity.user.initial}
                </Link>
                <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <p className="min-w-0 text-xs leading-5 text-ink-950/60 sm:text-[13px]"><Link href={activity.user.url} className="font-bold text-ink-950 hover:text-brand-coral">{activity.user.name}</Link> {activity.action} {!activity.is_expanded && <Link href={activity.literature.url} className="font-bold text-ink-950 hover:text-brand-coral">{activity.literature.title}</Link>}</p>
                        <TimeLabel value={activity.occurred_at} className="shrink-0 pt-0.5 text-[11px] leading-4 text-ink-950/35" />
                    </div>
                    {activity.is_expanded && (
                        <div className="mt-2 grid grid-cols-[44px_minmax(0,1fr)] gap-2.5 sm:grid-cols-[50px_minmax(0,1fr)]">
                            <Link href={activity.literature.url} className="aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25">
                                {activity.literature.cover_url ? <img src={activity.literature.cover_url} alt={`Cover of ${activity.literature.title}`} className="size-full object-cover" loading="lazy" /> : <span className="grid size-full place-items-center font-serif text-xl font-bold text-ink-950">{activity.literature.initials}</span>}
                            </Link>
                            <div className="min-w-0 self-center">
                                <h2 className="truncate text-base font-bold leading-tight text-ink-950 sm:text-lg"><Link href={activity.literature.url} className="hover:text-brand-coral">{activity.literature.title}</Link></h2>
                                {activity.rating !== null && <div className="mt-1 flex items-center gap-1.5"><StarRating rating={activity.rating} size="text-xs" /><span className="text-[0.68rem] font-bold text-ink-950/45">{activity.rating.toFixed(1)}</span></div>}
                                {activity.excerpt && <p className="mt-1 line-clamp-2 text-xs leading-5 text-ink-950/60">{activity.contains_spoiler ? 'This activity contains spoilers.' : activity.excerpt}</p>}
                            </div>
                        </div>
                    )}
                    {!activity.is_expanded && isDiscussion && (
                        <div className="mt-1.5 border-l-2 border-brand-coral py-1 pl-2.5">
                            <p className="truncate text-xs font-bold text-ink-950">{activity.discussion_title}</p>
                            {activity.excerpt && <p className="mt-0.5 line-clamp-1 text-xs leading-5 text-ink-950/55">{activity.contains_spoiler ? 'This activity contains spoilers.' : activity.excerpt}</p>}
                        </div>
                    )}
                </div>
            </div>
        </article>
    );
}

export default function Index({ navigation, activities, scope, scopes, heading = 'Latest Activity' }) {
    return (
        <>
            <Head title="Activity" />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="grid gap-7 py-7 lg:grid-cols-[minmax(0,1fr)_200px] lg:gap-6 lg:py-9">
                <div className="min-w-0" aria-labelledby="activity-heading">
                    <div className="flex flex-wrap items-end justify-between gap-3 border-b border-ink-950/20 pb-2.5">
                        <div><p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Social reading stream</p><h1 id="activity-heading" className="mt-0.5 text-2xl font-bold text-ink-950">{heading}</h1></div>
                        <nav className="flex gap-3 text-[11px] font-bold uppercase tracking-wider sm:gap-4" aria-label="Activity scopes">
                            {scopes.map((item) => <Link key={item.key} href={item.url} className={`border-b-2 py-0.5 ${scope === item.key ? 'border-brand-coral text-ink-950' : 'border-transparent text-ink-950/45 hover:text-ink-950'}`} aria-current={scope === item.key ? 'page' : undefined}>{item.short_label}</Link>)}
                        </nav>
                    </div>
                    <div data-activity-stream data-activity-density="compact">
                        {activities.data.length > 0 ? activities.data.map((activity) => <ActivityItem key={activity.id} activity={activity} />) : <div className="border-b border-ink-950/10 py-9 text-center"><p className="text-lg font-bold text-ink-950">No activity in this view yet.</p><p className="mt-1.5 text-sm text-ink-950/55">Read, rate, review, discuss, or follow another reader to build your stream.</p></div>}
                    </div>
                    {activities.links.length > 3 && <nav className="mt-6 flex flex-wrap gap-2" aria-label="Activity pagination">{activities.links.map((link, index) => link.url ? <Link key={`${link.label}-${index}`} href={link.url} preserveScroll className={`border px-3 py-2 text-sm font-semibold ${link.active ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/15 text-ink-950 hover:border-brand-coral'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={`${link.label}-${index}`} className="border border-ink-950/10 px-3 py-2 text-sm text-ink-950/30" dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav>}
                </div>
                <aside className="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-4">
                    <div className="sticky top-24">
                        <section className="border-t border-ink-950/20 pt-4" aria-labelledby="activity-filter-heading">
                            <div className="flex items-center justify-between gap-3"><div><p className="text-[0.62rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Activity filters</p><h2 id="activity-filter-heading" className="mt-0.5 text-base font-bold text-ink-950">Choose your stream</h2></div><span className="grid size-6 place-items-center rounded-full border border-ink-950/10 text-[10px] text-ink-950/45" aria-hidden="true">☰</span></div>
                            <div className="mt-2.5 grid gap-1 text-[0.7rem]">{scopes.map((item) => <Link key={item.key} href={item.url} className={`flex justify-between border-b px-1 py-2 font-bold transition ${scope === item.key ? 'border-brand-coral text-ink-950' : 'border-ink-950/10 text-ink-950/50 hover:border-brand-coral hover:text-ink-950'}`}><span>{item.label}</span><span>{item.short_label}</span></Link>)}</div>
                        </section>
                        <p className="mt-2.5 text-[0.68rem] leading-4 text-ink-950/45">Includes Readlist additions, reading milestones, ratings, reviews, discussions, and comments. Moderated content is excluded.</p>
                    </div>
                </aside>
            </ProfileContentContainer>
        </>
    );
}
