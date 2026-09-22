import { Head, Link, router } from '@inertiajs/react';

import { Pagination, ProfileContentContainer } from '../../Components/ProfilePageUi';
import ProfileSubNavigation from '../../Components/ProfileSubNavigation';

function ConnectionMetric({ type, value, compact = false }) {
    const isCompleted = type === 'completed';
    const label = isCompleted ? 'Completed' : 'Readlist';

    return (
        <span className={`inline-flex items-center gap-1 font-semibold text-ink-950/60 ${compact ? 'text-[11px]' : 'justify-center text-xs'}`} title={`${value} ${label.toLowerCase()}`} aria-label={`${value} ${label.toLowerCase()}`}>
            {isCompleted ? (
                <svg aria-hidden="true" className="size-3.5 text-brand-coral" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></svg>
            ) : (
                <svg aria-hidden="true" className="size-3.5 text-brand-blueberry" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 4h12v17l-6-3.5L6 21V4Z" /></svg>
            )}
            <span>{value}</span>
        </span>
    );
}

function BlockIcon() {
    return <svg aria-hidden="true" className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="9" /><path d="m5.6 5.6 12.8 12.8" /></svg>;
}

export default function Connections({ profile, navigation, connections, relationship, canManage, counts, routes }) {
    const tabs = [
        { key: 'following', label: 'Following', count: counts.following, url: routes.following },
        { key: 'followers', label: 'Followers', count: counts.followers, url: routes.followers },
        ...(routes.blocked ? [{ key: 'blocked', label: 'Blocked', count: counts.blocked, url: routes.blocked }] : []),
    ];
    const title = relationship === 'followers' ? 'Followers' : relationship === 'blocked' ? 'Blocked' : 'Following';
    const changeFollow = (connection) => {
        if (connection.viewer_follows) {
            router.delete(connection.unfollow_url, { preserveScroll: true });
            return;
        }

        router.post(connection.follow_url, {}, { preserveScroll: true });
    };
    const changeBlock = (connection) => {
        if (relationship === 'blocked') {
            router.delete(connection.unblock_url, { preserveScroll: true });
            return;
        }

        router.post(connection.block_url, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${title} - ${profile.name}`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="py-7 lg:py-9" aria-labelledby="connections-heading">
                <div className="mx-auto max-w-5xl">
                    <h1 id="connections-heading" className="sr-only">{title} of {profile.name}</h1>
                    <nav className="flex gap-2 overflow-x-auto pb-1" aria-label="Connection categories">
                        {tabs.map((tab) => {
                            const active = relationship === tab.key;

                            return (
                                <Link key={tab.key} href={tab.url} preserveScroll aria-current={active ? 'page' : undefined} className={`inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral ${active ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 bg-white/60 text-ink-950/65 hover:border-ink-950/30 hover:text-ink-950'}`}>
                                    {tab.label}
                                    <span className={`grid min-w-5 place-items-center rounded-full px-1 text-[10px] leading-5 ${active ? 'bg-brand-cream/20 text-brand-cream' : 'bg-brand-sky/25 text-ink-950/70'}`}>{tab.count}</span>
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="mt-4 overflow-hidden rounded-xl border border-ink-950/10 bg-white/50">
                        <div className="hidden grid-cols-[minmax(0,1fr)_72px_88px_180px] border-b border-ink-950/10 bg-white/35 px-4 py-3 text-[10px] font-bold uppercase tracking-[0.16em] text-ink-950/45 md:grid">
                            <span>Name</span><span className="text-center">Done</span><span className="text-center">Readlist</span><span className="text-right">Action</span>
                        </div>
                        {connections.data.length > 0 ? (
                            <div className="divide-y divide-ink-950/10">
                                {connections.data.map((connection) => (
                                    <article key={connection.username} className="grid grid-cols-1 items-center gap-2 px-4 py-3 transition-colors hover:bg-white/60 md:grid-cols-[minmax(0,1fr)_72px_88px_180px] md:gap-0">
                                        <Link href={connection.url} className="group flex min-w-0 items-center gap-3 focus-visible:rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-coral">
                                            <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 text-sm font-bold text-brand-cream">
                                                {connection.avatar_url ? <img src={connection.avatar_url} alt="" className="size-full object-cover" /> : connection.initials}
                                            </span>
                                            <span className="min-w-0">
                                                <span className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                    <span className="truncate text-sm font-bold text-ink-950 transition group-hover:text-brand-coral">{connection.name}</span>
                                                    {connection.follows_viewer && <span className="shrink-0 rounded-full bg-brand-sky/30 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-[0.1em] text-ink-950/75">Follows you</span>}
                                                </span>
                                                <span className="block truncate text-xs leading-5 text-ink-950/50">@{connection.username} · {connection.followers_count} followers · following {connection.following_count}</span>
                                                <span className="flex gap-3 md:hidden"><ConnectionMetric type="completed" value={connection.completed_count} compact /><ConnectionMetric type="readlist" value={connection.readlist_count} compact /></span>
                                            </span>
                                        </Link>
                                        <span className="hidden place-items-center md:grid"><ConnectionMetric type="completed" value={connection.completed_count} /></span>
                                        <span className="hidden place-items-center md:grid"><ConnectionMetric type="readlist" value={connection.readlist_count} /></span>
                                        {canManage ? (
                                            <div className="flex items-center gap-1.5 md:justify-end">
                                                {relationship === 'blocked' && connection.unblock_url ? (
                                                    <button type="button" onClick={() => changeBlock(connection)} className="rounded-md border border-ink-950/15 bg-white/70 px-2.5 py-1.5 text-xs font-semibold text-ink-950 transition hover:border-brand-coral hover:text-brand-coral focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">Unblock</button>
                                                ) : (
                                                    <>
                                                        {connection.follow_url && (
                                                            <button type="button" onClick={() => changeFollow(connection)} title={connection.viewer_follows ? 'Unfollow reader' : undefined} className="rounded-md border border-ink-950/15 bg-white/70 px-2.5 py-1.5 text-xs font-semibold text-ink-950 transition hover:border-brand-coral hover:text-brand-coral focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                                                                {connection.viewer_follows ? '✓ Following' : connection.follows_viewer ? 'Follow back' : 'Follow'}
                                                            </button>
                                                        )}
                                                        {connection.block_url && (
                                                            <button type="button" onClick={() => changeBlock(connection)} title="Block reader" aria-label={`Block ${connection.name}`} className="grid size-8 place-items-center rounded-md border border-ink-950/10 bg-white/70 text-ink-950/50 transition hover:border-brand-coral hover:text-brand-coral focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral"><BlockIcon /></button>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        ) : <span className="hidden md:block" />}
                                    </article>
                                ))}
                            </div>
                        ) : <p className="px-4 py-7 text-center text-sm text-ink-950/55">No readers in {title.toLowerCase()} yet.</p>}
                    </div>
                    <Pagination paginator={connections} label={`${title} pagination`} />
                </div>
            </ProfileContentContainer>
        </>
    );
}
