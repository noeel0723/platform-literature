import { Head, Link, router } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';

function PaginationLink({ href, relation, children }) {
    const classes = 'inline-flex min-h-10 items-center border px-5 text-sm font-semibold';

    return href ? (
        <Link href={href} rel={relation} preserveScroll className={`${classes} border-ink-950/20 bg-white/45 text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-brand-cream`}>
            {children}
        </Link>
    ) : (
        <span aria-disabled="true" className={`${classes} border-ink-950/10 text-ink-950/30`}>{children}</span>
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
            <section className="catalog-grid border-b border-ink-950/10">
                <div className="mx-auto max-w-6xl px-5 py-9 sm:px-8 lg:px-10 lg:py-12">
                    <Link href={routes.profile} className="text-sm font-bold text-ink-950/65 transition hover:text-brand-coral">← Back to {profile.name}&apos;s profile</Link>
                    <p className="mt-7 text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader network</p>
                </div>
            </section>
            <section className="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <nav className="flex items-end gap-6 overflow-x-auto border-b border-ink-950/15" aria-label="Connection categories">
                    {tabs.map((tab) => (
                        <Link key={tab.key} href={tab.url} preserveScroll className={`flex shrink-0 items-center gap-2 border-b-2 px-0.5 pb-3 text-sm font-bold uppercase tracking-[0.12em] transition ${relationship === tab.key ? 'border-brand-coral text-ink-950' : 'border-transparent text-ink-950/50 hover:text-ink-950'}`} aria-current={relationship === tab.key ? 'page' : undefined}>
                            <span>{tab.label}</span>
                            <span className="rounded-full bg-brand-sky/25 px-2 py-0.5 text-[0.65rem]">{tab.count}</span>
                        </Link>
                    ))}
                </nav>
                <div className="mt-5 grid grid-cols-[minmax(0,1fr)_auto] border-y border-ink-950/10 px-4 py-2 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-ink-950/40 sm:grid-cols-[minmax(0,1fr)_90px_90px_92px]">
                    <span>Name</span><span className="hidden text-center sm:block">Followers</span><span className="hidden text-center sm:block">Following</span><span className="text-right">Action</span>
                </div>
                {connections.data.length > 0 ? (
                    <div className="divide-y divide-ink-950/10">
                        {connections.data.map((connection) => (
                            <article key={connection.username} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-4 py-4 sm:grid-cols-[minmax(0,1fr)_90px_90px_92px]">
                                <Link href={connection.url} className="group flex min-w-0 items-center gap-4">
                                    <span className="grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 text-lg font-bold text-brand-cream">
                                        {connection.avatar_url ? <img src={connection.avatar_url} alt="" className="size-full object-cover" /> : connection.initials}
                                    </span>
                                    <span className="min-w-0"><span className="block truncate text-lg font-bold text-ink-950 transition group-hover:text-brand-coral">{connection.name}</span><span className="block truncate text-sm text-ink-950/50">@{connection.username}</span></span>
                                </Link>
                                <span className="hidden text-center text-sm font-semibold text-ink-950/55 sm:block">{connection.followers_count}</span>
                                <span className="hidden text-center text-sm font-semibold text-ink-950/55 sm:block">{connection.following_count}</span>
                                {canManage ? <button type="button" onClick={() => changeBlock(connection)} className={`justify-self-end rounded-full px-3 py-2 text-xs font-bold transition ${relationship === 'blocked' ? 'border border-ink-950/15 text-ink-950 hover:border-brand-coral hover:text-brand-coral' : 'bg-ink-950 text-brand-cream hover:bg-brand-coral'}`}>{relationship === 'blocked' ? 'Unblock' : 'Block'}</button> : <span className="justify-self-end text-xs font-bold uppercase tracking-wider text-ink-950/35">View</span>}
                            </article>
                        ))}
                    </div>
                ) : <div className="mt-5 border border-dashed border-ink-950/20 p-8 text-ink-950/55">No readers are listed in {title.toLowerCase()} yet.</div>}
                {connections.last_page > 1 && (
                    <nav className="mt-10 flex items-center justify-between border-t border-ink-950/10 pt-6" aria-label={`${title} pagination`}>
                        <PaginationLink href={connections.prev_page_url} relation="prev">Previous</PaginationLink>
                        <span className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">Page {connections.current_page} of {connections.last_page}</span>
                        <PaginationLink href={connections.next_page_url} relation="next">Next</PaginationLink>
                    </nav>
                )}
            </section>
        </>
    );
}
