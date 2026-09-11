import { Head, Link } from '@inertiajs/react';

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

export default function Connections({ profile, navigation, connections, relationship, routes }) {
    const title = relationship === 'followers' ? 'Followers' : 'Following';
    const description = relationship === 'followers'
        ? `Readers following ${profile.name}.`
        : `Readers followed by ${profile.name}.`;

    return (
        <>
            <Head title={`${title} - ${profile.name}`} />
            <ProfileSubNavigation navigation={navigation} />
            <section className="catalog-grid border-b border-ink-950/10">
                <div className="mx-auto max-w-6xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
                    <Link href={routes.profile} className="text-sm font-bold text-ink-950/65 transition hover:text-brand-coral">← Back to {profile.name}&apos;s profile</Link>
                    <p className="mt-8 text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader network</p>
                    <h1 className="mt-3 text-4xl font-bold text-ink-950 sm:text-5xl">{title}</h1>
                    <p className="mt-3 leading-7 text-ink-950/60">{description}</p>
                </div>
            </section>
            <section className="mx-auto max-w-6xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
                {connections.data.length > 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {connections.data.map((connection) => (
                            <Link key={connection.username} href={connection.url} className="group flex items-center gap-4 border border-ink-950/10 bg-white/35 p-5 transition hover:border-brand-coral">
                                <span className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 text-xl font-bold text-brand-cream">
                                    {connection.avatar_url ? <img src={connection.avatar_url} alt="" className="size-full object-cover" /> : connection.initials}
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-xl font-bold text-ink-950 transition group-hover:text-brand-coral">{connection.name}</span>
                                    <span className="block truncate text-sm font-semibold text-ink-950/50">@{connection.username}</span>
                                    <span className="mt-2 block text-xs uppercase tracking-wider text-ink-950/45">{connection.followers_count} followers · {connection.following_count} following</span>
                                </span>
                            </Link>
                        ))}
                    </div>
                ) : <div className="border border-dashed border-ink-950/20 p-8 text-ink-950/55">No readers are listed here yet.</div>}
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
