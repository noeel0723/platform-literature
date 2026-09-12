import { Head, Link } from '@inertiajs/react';

import { TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import SiteContainer from '../../Components/SiteContainer';

function Cover({ literature }) {
    return literature.cover_url ? (
        <img
            src={literature.cover_url}
            alt={`Cover of ${literature.title}`}
            className="aspect-[2/3] w-full object-cover transition duration-300 group-hover:scale-[1.025]"
            loading="lazy"
        />
    ) : (
        <span className="grid aspect-[2/3] place-items-center px-3 text-center text-3xl font-bold text-ink-950">
            {literature.initials}
        </span>
    );
}

function ReaderAvatar({ reader, className = 'size-7' }) {
    return (
        <span className={`grid shrink-0 place-items-center overflow-hidden rounded-full border border-brand-cream/60 bg-brand-sky font-bold text-ink-950 ${className}`}>
            {reader.avatar_url ? <img src={reader.avatar_url} alt="" className="size-full object-cover" /> : reader.initials}
        </span>
    );
}

function EmptyActivity({ viewer, routes }) {
    return (
        <div className="mt-4 border-y border-dashed border-ink-950/20 px-5 py-9 text-center">
            <p className="text-lg font-bold text-ink-950">No new activity from friends yet.</p>
            <p className="mx-auto mt-1.5 max-w-xl text-sm leading-6 text-ink-950/60">
                {viewer
                    ? 'Follow readers from their profile. Their completed reads, ratings, and reviews will appear here.'
                    : 'Log in to see completed reads, ratings, and reviews from readers you follow.'}
            </p>
            <Link
                href={viewer ? routes.catalog : routes.login}
                className="mt-4 inline-flex bg-ink-950 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral"
            >
                {viewer ? 'Explore literature' : 'Log in'}
            </Link>
        </div>
    );
}

export default function HomeIndex({ activities, popularLiteratures, recommendations, viewer, routes }) {
    return (
        <>
            <Head title="Home" />

            <section>
                <SiteContainer className="py-7 sm:py-9">
                    <header className="border-b border-ink-950/15 pb-5">
                        <h1 className="text-center text-lg font-medium tracking-tight text-ink-950 sm:text-xl">
                            {viewer ? <>Welcome back, <span className="font-bold">{viewer.name}</span>. Here is what your friends have been reading...</> : 'Welcome to Literahaven.'}
                        </h1>
                        {!viewer && (
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-ink-950/60">
                                Sign in and follow other readers to build your personal activity feed.
                            </p>
                        )}
                    </header>

                    {viewer && (
                        <section className="mt-6" aria-labelledby="recommended-heading">
                            <div className="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-2">
                                <h2 id="recommended-heading" className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Recommended For You</h2>
                                <span className="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/45">Explainable picks</span>
                            </div>

                            {recommendations.length === 0 ? (
                                <div className="mt-4 border-y border-dashed border-ink-950/20 px-5 py-8 text-center text-sm text-ink-950/60">
                                    Recommendations will appear as the catalog grows.
                                </div>
                            ) : (
                                <div className="mt-4 grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-4 lg:grid-cols-8">
                                    {recommendations.map((literature) => (
                                        <article key={literature.id} className="min-w-0" data-recommendation>
                                            <Link href={literature.url} className="group block overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_3px_10px_rgba(47,58,85,0.06)]">
                                                <Cover literature={literature} />
                                            </Link>
                                            <Link href={literature.url} className="mt-1.5 block truncate text-xs font-bold text-ink-950 transition hover:text-brand-coral">{literature.title}</Link>
                                            <p className="mt-0.5 line-clamp-2 text-[0.64rem] leading-4 text-ink-950/55">{literature.reason}</p>
                                        </article>
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    <section className="mt-6" aria-labelledby="friends-activity-heading">
                        <div className="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-2">
                            <h2 id="friends-activity-heading" className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Activity feed</h2>
                            <span className="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/45">Latest 6</span>
                        </div>

                        {activities.length === 0 ? <EmptyActivity viewer={viewer} routes={routes} /> : (
                            <div className="mt-4 grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                {activities.map((activity) => (
                                    <article key={activity.id} className="min-w-0" data-friend-activity>
                                        <Link href={activity.literature.url} className="group relative block overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_3px_10px_rgba(47,58,85,0.06)]">
                                            <Cover literature={activity.literature} />
                                            <span className="absolute left-1.5 top-1.5 bg-ink-950/90 px-1.5 py-1 text-[0.58rem] font-bold uppercase tracking-wider text-brand-cream">{activity.action}</span>
                                            <span className="absolute inset-x-0 bottom-0 flex items-center gap-1.5 bg-linear-to-t from-ink-950 via-ink-950/90 to-transparent px-2 pb-2 pt-8 text-brand-cream">
                                                <ReaderAvatar reader={activity.reader} className="size-6" />
                                                <span className="truncate text-[0.68rem] font-bold">{activity.reader.name}</span>
                                            </span>
                                        </Link>
                                        <Link href={activity.literature.url} className="mt-1.5 block truncate text-sm font-bold text-ink-950 transition hover:text-brand-coral">{activity.literature.title}</Link>
                                        <div className="mt-0.5 flex min-w-0 items-center justify-between gap-2 text-[0.68rem] text-ink-950/55">
                                            {activity.rating !== null ? <span className="shrink-0 font-bold text-brand-coral">★ {Number(activity.rating).toFixed(1)}</span> : <span className="truncate">{activity.action}</span>}
                                            <TimeLabel value={activity.occurred_at} className="truncate text-right" />
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="mt-9" aria-labelledby="popular-friends-heading">
                        <div className="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-2">
                            <h2 id="popular-friends-heading" className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Shared discoveries</h2>
                            <span className="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/45">Most read</span>
                        </div>

                        {popularLiteratures.length === 0 ? (
                            <div className="mt-4 border-y border-dashed border-ink-950/20 px-5 py-8 text-center text-sm text-ink-950/60">
                                Literature your friends are reading or have completed will appear here.
                            </div>
                        ) : (
                            <div className="mt-4 grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                {popularLiteratures.map((literature) => (
                                    <article key={literature.id} className="min-w-0" data-popular-with-friends>
                                        <Link href={literature.url} className="group relative block overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_3px_10px_rgba(47,58,85,0.06)]">
                                            <Cover literature={literature} />
                                            <span className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-linear-to-t from-ink-950 via-ink-950/90 to-transparent px-2 pb-2 pt-8 text-brand-cream">
                                                <span className="flex -space-x-2" aria-hidden="true">
                                                    {literature.friends.map((reader, index) => <ReaderAvatar key={`${reader.name}-${index}`} reader={reader} className="size-7 border-2 border-ink-950 text-[0.65rem]" />)}
                                                </span>
                                                <span className="text-xs font-bold">{literature.friends_count}</span>
                                            </span>
                                        </Link>
                                        <Link href={literature.url} className="mt-1.5 block truncate text-sm font-bold text-ink-950 transition hover:text-brand-coral">{literature.title}</Link>
                                        <p className="mt-0.5 text-[0.68rem] text-ink-950/55">Read by {literature.friends_count} {literature.friends_count === 1 ? 'friend' : 'friends'}</p>
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                </SiteContainer>
            </section>
        </>
    );
}
