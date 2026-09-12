import { Head, Link, router } from '@inertiajs/react';
import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { plural, ReportForm, StarRating, TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import SiteContainer from '../../Components/SiteContainer';

function EmptyState({ children, className = '' }) {
    return <div className={`border-y border-dashed border-ink-950/20 px-4 py-7 text-sm text-ink-950/55 ${className}`}>{children}</div>;
}

function Cover({ literature, className = '', loading = 'lazy' }) {
    return (
        <span className={`grid overflow-hidden border border-ink-950/15 bg-brand-sky/25 ${className}`}>
            {literature.cover_url ? (
                <img src={literature.cover_url} alt={`Cover of ${literature.title}`} className="size-full object-cover" loading={loading} />
            ) : (
                <span className="grid size-full place-items-center px-2 text-center text-lg font-bold text-ink-950">{literature.initials}</span>
            )}
        </span>
    );
}

function SectionHeader({ eyebrow, title, aside, id }) {
    return (
        <div className="flex items-end justify-between border-b border-ink-950/15 pb-2">
            <div>
                {eyebrow && <p className="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-brand-coral">{eyebrow}</p>}
                {title && <h2 id={id} className={`${eyebrow ? 'mt-1' : ''} text-xl font-bold text-ink-950`}>{title}</h2>}
            </div>
            {aside}
        </div>
    );
}

function ProfileStats({ profile, routes }) {
    const items = [
        { key: 'literature', label: 'Literature', value: profile.stats.literature },
        { key: 'completed', label: 'Completed', value: profile.stats.completed },
        { key: 'following', label: 'Following', value: profile.stats.following, url: routes.following },
        { key: 'followers', label: 'Followers', value: profile.stats.followers, url: routes.followers },
    ];

    return (
        <dl data-profile-stats className="grid min-w-0 grid-cols-4 divide-x divide-ink-950/10 border-y border-ink-950/10 text-center lg:min-w-[360px] lg:border-y-0">
            {items.map((item) => {
                const content = <><dd className="text-xl font-bold leading-none text-ink-950 sm:text-2xl">{item.value}</dd><dt className="mt-1.5 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-ink-950/45 sm:text-[0.62rem]">{item.label}</dt></>;

                return (
                    <div key={item.key} data-profile-stat={item.key}>
                        {item.url ? <a href={item.url} className="block px-2 py-2.5 transition hover:bg-brand-sky/20 sm:px-3">{content}</a> : <div className="px-2 py-2.5 sm:px-3">{content}</div>}
                    </div>
                );
            })}
        </dl>
    );
}

function ProfileOverview({ profile, routes, reportReasons }) {
    const toggleFollow = () => profile.is_following
        ? router.delete(routes.unfollow, { preserveScroll: true })
        : router.post(routes.follow, {}, { preserveScroll: true });

    return (
        <section id="profile-overview" data-profile-header data-profile-compact-header className="catalog-grid border-b border-ink-950/10">
            <SiteContainer className="py-6 lg:py-7">
                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-7">
                    <div className="flex min-w-0 flex-wrap items-start gap-3.5 sm:items-center sm:gap-4">
                        <div className="grid size-18 shrink-0 place-items-center overflow-hidden rounded-full border-2 border-brand-cream bg-ink-950 text-lg font-bold text-brand-cream shadow-[0_5px_16px_rgba(16,47,98,0.1)] sm:size-20">
                            {profile.avatar_url ? <img src={profile.avatar_url} alt={`${profile.name}'s profile photo`} className="size-full object-cover" /> : profile.initials}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-[0.62rem] font-bold uppercase tracking-[0.18em] text-brand-coral">Reader profile</p>
                            <div className="mt-1 flex min-w-0 flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                <h1 className="max-w-full truncate text-2xl font-bold leading-none text-ink-950 sm:text-3xl">{profile.name}</h1>
                                {profile.is_owner && <a href={routes.edit} className="rounded-sm border border-ink-950/15 bg-brand-cream/65 px-2.5 py-1 text-[0.62rem] font-bold uppercase tracking-wider text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-white">Edit profile</a>}
                                <p className="w-full truncate text-sm font-semibold text-ink-950/50">@{profile.username}</p>
                            </div>
                            {profile.bio ? <p className="mt-2 line-clamp-2 max-w-xl text-xs leading-5 text-ink-950/65">{profile.bio}</p> : <p className="mt-2 text-xs italic leading-5 text-ink-950/45">This reader has not added a bio yet.</p>}
                            <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[0.68rem] text-ink-950/50">
                                {profile.location && <span>⌖ {profile.location}</span>}
                                <span>Member since {profile.member_since}</span>
                            </div>
                        </div>
                        {!profile.is_owner && (
                            <div className="flex w-full shrink-0 flex-wrap items-center gap-3 pl-24 sm:w-auto sm:pl-0">
                                {profile.viewer_authenticated ? <><button type="button" onClick={toggleFollow} className={`rounded-full px-4 py-2 text-xs font-bold transition ${profile.is_following ? 'border border-ink-950/15 bg-brand-cream/60 text-ink-950 hover:border-brand-coral' : 'bg-ink-950 text-brand-cream hover:bg-brand-coral'}`}>{profile.is_following ? 'Following' : 'Follow'}</button><ReportForm targetType="user" targetId={profile.id} reasons={reportReasons} action={routes.report} label="Report profile" /></> : <a href={routes.login} className="rounded-full bg-ink-950 px-4 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral">Log in to follow</a>}
                            </div>
                        )}
                    </div>
                    <ProfileStats profile={profile} routes={routes} />
                </div>
            </SiteContainer>
        </section>
    );
}

function FavoriteLiteratures({ items }) {
    return (
        <section>
            <SectionHeader eyebrow="Favorite literature" aside={<span className="text-sm text-ink-950/50">Up to four</span>} />
            {items.length ? <div data-favorite-literature-grid className="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">{items.map((literature, index) => <article key={literature.id} className="group min-w-0"><Link href={literature.url} className="block"><span className="relative block"><Cover literature={literature} loading="eager" className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-coral" /><span className="absolute left-2 top-2 grid size-6 place-items-center bg-ink-950 text-[0.62rem] font-bold text-brand-cream">{String(index + 1).padStart(2, '0')}</span></span><h3 className="mt-1.5 truncate text-sm font-bold text-ink-950 transition group-hover:text-brand-coral">{literature.title}</h3><p className="mt-0.5 truncate text-[0.68rem] text-ink-950/55">{literature.author}</p></Link></article>)}</div> : <EmptyState className="mt-4">No favorite literature has been selected.</EmptyState>}
        </section>
    );
}

function FavoriteAuthors({ items }) {
    return (
        <section className="mt-8">
            <SectionHeader eyebrow="Favorite authors" aside={<span className="text-sm text-ink-950/50">Up to four</span>} />
            {items.length ? <ol data-favorite-author-grid className="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">{items.map((author, index) => <li key={author.id} className="group min-w-0"><a href={author.url} className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral"><div className="relative aspect-[4/5] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/30 shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-coral">{author.image_url ? <img src={author.image_url} alt={`Portrait of ${author.name}`} className="size-full object-cover" /> : <div className="grid size-full place-items-center bg-linear-to-br from-brand-sky/45 to-brand-coral/35 text-4xl font-bold text-ink-950">{author.initials}</div>}<div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-ink-950 via-ink-950/80 to-transparent px-2.5 pb-2.5 pt-10 text-brand-cream"><p className="truncate text-sm font-bold">{author.name}</p><p className="mt-0.5 text-[0.58rem] font-bold uppercase tracking-wider text-brand-cream/65">Favorite author {String(index + 1).padStart(2, '0')}</p></div></div></a></li>)}</ol> : <EmptyState className="mt-4">No favorite authors have been selected.</EmptyState>}
        </section>
    );
}

function RecentCompletions({ items }) {
    return (
        <section id="recently-completed" className="mt-8 scroll-mt-24">
            <SectionHeader title="Recently completed" aside={<span className="text-sm text-ink-950/50">Latest four</span>} />
            {items.length ? <div className="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">{items.map((entry) => <Link key={entry.literature.id} href={entry.literature.url} className="group min-w-0" data-profile-completed-card><Cover literature={entry.literature} className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition group-hover:-translate-y-0.5 group-hover:border-brand-coral" /><span className="mt-1.5 block truncate text-xs font-bold text-ink-950 group-hover:text-brand-coral">{entry.literature.title}</span>{entry.rating ? <StarRating rating={entry.rating} size="text-xs" /> : <span className="mt-0.5 block text-[0.62rem] font-bold uppercase tracking-wider text-ink-950/40">Completed</span>}{entry.completed_at && <TimeLabel value={entry.completed_at} className="mt-0.5 block truncate text-[0.62rem] text-ink-950/40" />}</Link>)}</div> : <EmptyState className="mt-4">No completed literature yet.</EmptyState>}
        </section>
    );
}

function RecentReviews({ items, moreUrl }) {
    return (
        <section id="recent-reviews" className="mt-8 scroll-mt-24">
            <SectionHeader title="Recent reviews" aside={<a href={moreUrl} className="text-xs font-bold uppercase tracking-wider text-brand-coral hover:underline">More</a>} />
            {items.length ? items.map((review) => <article key={review.id} className="grid grid-cols-[48px_minmax(0,1fr)] gap-3 border-b border-ink-950/10 py-3 sm:grid-cols-[56px_minmax(0,1fr)]" data-profile-recent-review><Link href={`${review.literature.url}#review-${review.id}`}><Cover literature={review.literature} className="aspect-[2/3] rounded-sm" /></Link><div className="min-w-0"><h3 className="truncate text-base font-bold text-ink-950"><Link href={`${review.literature.url}#review-${review.id}`} className="hover:text-brand-coral">{review.literature.title}</Link>{review.literature.year && <span className="ml-1.5 text-xs font-normal text-ink-950/45">{review.literature.year}</span>}</h3><div className="mt-1 flex flex-wrap items-center gap-2"><StarRating rating={review.rating} size="text-xs" /><TimeLabel value={review.updated_at} className="text-[0.68rem] text-ink-950/45" /></div>{review.body ? <p className="mt-1.5 line-clamp-2 text-sm leading-5 text-ink-900">{review.contains_spoiler ? 'This review contains spoilers.' : review.body}</p> : <p className="mt-1.5 text-xs italic text-ink-950/45">Rating only.</p>}{review.likes_count > 0 && <p className="mt-1.5 text-[0.68rem] text-ink-950/40">{plural(review.likes_count, 'like')}</p>}</div></article>) : <EmptyState>No reviews yet.</EmptyState>}
        </section>
    );
}

function RatingDistribution({ distribution }) {
    return (
        <div className="mt-4">
            <div className="flex h-20 items-end gap-1" aria-label="Rating distribution from half a star to five stars">
                {distribution.map((item) => (
                    <span key={item.rating} className="group relative flex h-full flex-1 items-end justify-center">
                        <span
                            role="img"
                            tabIndex={0}
                            aria-label={`${item.count} literature rated ${item.rating} stars`}
                            className="block w-full cursor-help bg-brand-coral/75 transition duration-150 hover:bg-brand-coral focus:bg-brand-coral focus:outline-none focus-visible:ring-2 focus-visible:ring-ink-950 focus-visible:ring-offset-2 focus-visible:ring-offset-brand-cream"
                            style={{ height: `${item.height}%` }}
                        />
                        <span
                            role="tooltip"
                            className="pointer-events-none absolute left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-sm bg-ink-950 px-2.5 py-1.5 text-[0.65rem] font-bold text-brand-cream opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100"
                            style={{ bottom: `calc(${item.height}% + 0.5rem)` }}
                        >
                            {item.count} {item.count === 1 ? 'literature' : 'literatures'} · {item.rating} ★
                            <span className="absolute left-1/2 top-full -translate-x-1/2 border-x-4 border-t-4 border-x-transparent border-t-ink-950" aria-hidden="true" />
                        </span>
                    </span>
                ))}
            </div>
            <div className="mt-2 flex justify-between text-[0.65rem] font-bold text-ink-950/40"><span>½</span><span>5 ★</span></div>
        </div>
    );
}

function ProfileSidebar({ profile, readlist, reviews, activities, distribution, routes }) {
    return (
        <aside className="min-w-0 lg:sticky lg:top-24 lg:self-start lg:border-l lg:border-ink-950/10 lg:pl-5" aria-labelledby="profile-readlist-heading" data-profile-readlist-preview>
            <SectionHeader eyebrow="Saved shelf" title="Readlist" id="profile-readlist-heading" aside={<span className="text-sm text-ink-950/50">{profile.stats.readlist}</span>} />
            {readlist.length ? <div className="mt-4 grid grid-cols-4 gap-1.5 overflow-hidden">{readlist.map((item) => <Link key={item.id} href={item.url} title={item.title} className="group block"><Cover literature={item} className="aspect-[2/3] rounded-sm transition group-hover:border-brand-coral" /></Link>)}</div> : <EmptyState className="mt-4">No literature has been saved to this Readlist.</EmptyState>}
            <a href={routes.readlist} className="mt-3 flex items-center justify-between border border-ink-950/15 bg-ink-950 px-3 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral"><span>View full Readlist</span><span aria-hidden="true">→</span></a>
            {profile.is_owner && <a href={routes.diary} className="mt-2 flex items-center justify-between border border-ink-950/15 bg-brand-cream/70 px-3 py-2 text-xs font-bold text-ink-950 transition hover:border-brand-coral"><span>Open activity Diary</span><span aria-hidden="true">→</span></a>}

            <section className="mt-6" aria-labelledby="profile-diary-heading" data-profile-diary-preview><SectionHeader title="Diary" id="profile-diary-heading" aside={<span className="text-xs text-ink-950/45">{profile.stats.reviews}</span>} /><ol className="mt-2.5 grid gap-1.5">{reviews.length ? reviews.map((review) => <li key={review.id} className="flex min-w-0 items-center justify-between gap-2 text-xs"><Link href={review.literature.url} className="truncate text-ink-950/65 hover:text-brand-coral">{review.literature.title}</Link><span className="shrink-0 font-bold text-brand-coral">{Number(review.rating).toFixed(1)}</span></li>) : <li className="text-xs text-ink-950/45">No rated literature yet.</li>}</ol></section>
            <section className="mt-6" aria-labelledby="profile-ratings-heading" data-profile-ratings><SectionHeader title="Ratings" id="profile-ratings-heading" aside={<span className="text-xs text-ink-950/45">{profile.stats.reviews}</span>} /><RatingDistribution distribution={distribution} /></section>
            <section className="mt-6" aria-labelledby="profile-activity-heading" data-profile-activity-preview><SectionHeader title="Activity" id="profile-activity-heading" aside={profile.is_owner ? <Link href={routes.activity} className="text-xs font-bold uppercase tracking-wider text-brand-coral hover:underline">All</Link> : null} /><ol className="mt-3 border-l border-ink-950/20 pl-3.5">{activities.length ? activities.map((activity) => <li key={activity.id} className="relative pb-3 text-xs last:pb-0 before:absolute before:-left-[1.06rem] before:top-1.5 before:size-1.5 before:rounded-full before:bg-brand-coral"><p className="leading-5 text-ink-950/55">{activity.label} <Link href={activity.literature.url} className="font-semibold text-ink-950 hover:text-brand-coral">{activity.literature.title}</Link></p><TimeLabel value={activity.occurred_at} className="mt-0.5 block text-[0.65rem] text-ink-950/35" /></li>) : <li className="text-xs text-ink-950/45">No activity yet.</li>}</ol></section>
        </aside>
    );
}

export default function ProfileShow({ profile, navigation, favoriteLiteratures, favoriteAuthors, recentCompletions, recentReviews, readlistPreview, recentActivities, ratingDistribution, reportReasons, routes }) {
    return (
        <>
            <Head title={profile.name} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileOverview profile={profile} routes={routes} reportReasons={reportReasons} />
            <SiteContainer as="section" id="favorites" className="grid scroll-mt-24 gap-8 py-7 lg:grid-cols-[minmax(0,1fr)_220px] lg:gap-6 lg:py-9">
                <div className="min-w-0"><FavoriteLiteratures items={favoriteLiteratures} /><FavoriteAuthors items={favoriteAuthors} /><RecentCompletions items={recentCompletions} /><RecentReviews items={recentReviews} moreUrl={routes.reviews} /></div>
                <ProfileSidebar profile={profile} readlist={readlistPreview} reviews={recentReviews} activities={recentActivities} distribution={ratingDistribution} routes={routes} />
            </SiteContainer>
        </>
    );
}
