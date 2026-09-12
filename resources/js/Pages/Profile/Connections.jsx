import { Head, Link, router } from '@inertiajs/react';

import { Pagination, ProfilePageHeading } from '../../Components/ProfilePageUi';
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

export default function Connections({ profile, navigation, connections, relationship, canManage, counts, routes }) {
    const tabs = [
        { key: 'following', label: 'Following', count: counts.following, url: routes.following },
        { key: 'followers', label: 'Followers', count: counts.followers, url: routes.followers },
        ...(routes.blocked ? [{ key: 'blocked', label: 'Blocked', count: counts.blocked, url: routes.blocked }] : []),
    ];
    const title = relationship === 'followers' ? 'Followers' : relationship === 'blocked' ? 'Blocked' : 'Following';
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
            <section className="mx-auto max-w-5xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10" aria-labelledby="connections-heading">
                <ProfilePageHeading
                    eyebrow="Reader network"
                    title="Connections"
                    count={`${connections.total} ${title.toLowerCase()}`}
                    id="connections-heading"
                />
                <nav className="mt-5 flex items-end gap-6 overflow-x-auto border-b border-ink-950/15" aria-label="Connection categories">
                    {tabs.map((tab) => (
                        <Link key={tab.key} href={tab.url} preserveScroll className={`flex shrink-0 items-center gap-2 border-b-2 px-0.5 pb-3 text-sm font-bold uppercase tracking-[0.12em] transition ${relationship === tab.key ? 'border-brand-coral text-ink-950' : 'border-transparent text-ink-950/50 hover:text-ink-950'}`} aria-current={relationship === tab.key ? 'page' : undefined}>
                            <span>{tab.label}</span>
                            <span className="rounded-full bg-brand-sky/25 px-2 py-0.5 text-[0.65rem]">{tab.count}</span>
                        </Link>
                    ))}
                </nav>
                <div className="mt-4 grid grid-cols-[minmax(0,1fr)_auto] border-y border-ink-950/10 px-3 py-2 text-[0.62rem] font-bold uppercase tracking-[0.12em] text-ink-950/40 sm:grid-cols-[minmax(0,1fr)_80px_80px_80px]">
                    <span>Name</span><span className="hidden text-center sm:block">Completed</span><span className="hidden text-center sm:block">Readlist</span><span className="text-right">Action</span>
                </div>
                {connections.data.length > 0 ? (
                    <div className="divide-y divide-ink-950/10">
                        {connections.data.map((connection) => (
                            <article key={connection.username} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-3 py-3 sm:grid-cols-[minmax(0,1fr)_80px_80px_80px]">
                                <Link href={connection.url} className="group flex min-w-0 items-center gap-3">
                                    <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 text-sm font-bold text-brand-cream">
                                        {connection.avatar_url ? <img src={connection.avatar_url} alt="" className="size-full object-cover" /> : connection.initials}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-base font-bold leading-5 text-ink-950 transition group-hover:text-brand-coral">{connection.name}</span>
                                        <span className="block truncate text-xs leading-5 text-ink-950/50">@{connection.username} · {connection.followers_count} followers · following {connection.following_count}</span>
                                        <span className="flex gap-3 sm:hidden"><ConnectionMetric type="completed" value={connection.completed_count} compact /><ConnectionMetric type="readlist" value={connection.readlist_count} compact /></span>
                                    </span>
                                </Link>
                                <span className="hidden place-items-center sm:grid"><ConnectionMetric type="completed" value={connection.completed_count} /></span>
                                <span className="hidden place-items-center sm:grid"><ConnectionMetric type="readlist" value={connection.readlist_count} /></span>
                                {canManage ? <button type="button" onClick={() => changeBlock(connection)} className={`justify-self-end rounded-sm px-2.5 py-1.5 text-[11px] font-bold transition ${relationship === 'blocked' ? 'border border-ink-950/15 text-ink-950 hover:border-brand-coral hover:text-brand-coral' : 'bg-ink-950 text-brand-cream hover:bg-brand-coral'}`}>{relationship === 'blocked' ? 'Unblock' : 'Block'}</button> : <span className="justify-self-end text-[10px] font-bold uppercase tracking-wider text-ink-950/35">View</span>}
                            </article>
                        ))}
                    </div>
                ) : <div className="mt-5 border border-dashed border-ink-950/20 p-8 text-ink-950/55">No readers are listed in {title.toLowerCase()} yet.</div>}
                <Pagination paginator={connections} label={`${title} pagination`} />
            </section>
        </>
    );
}
