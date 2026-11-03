import { Link } from '@inertiajs/react';

import { openLoginPanel } from '../Support/loginPanel';
import useFeedbackAction from '../Support/useFeedbackAction';
import { LiteratureCover } from './ProfilePageUi';
import RelationshipBadge from './RelationshipBadge';

export default function ReaderCard({ reader, routes, variant = 'card' }) {
    const action = useFeedbackAction();
    const label = reader.viewer_follows ? `Unfollow ${reader.name}` : reader.follows_viewer ? `Follow back ${reader.name}` : `Follow ${reader.name}`;
    const actionClass = `${variant === 'row' ? 'shrink-0' : 'absolute -right-1 bottom-0'} grid size-9 place-items-center rounded-full border border-ink-950/15 bg-brand-cream text-ink-950 transition-colors hover:border-brand-coral hover:bg-brand-coral hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50`;
    const followControl = !reader.is_self && (reader.follow_url ? (
        <button type="button" className={actionClass} disabled={action.processing} aria-busy={action.processing} aria-label={label} title={label} onClick={() => action.visit(reader.viewer_follows ? 'delete' : 'post', reader.viewer_follows ? reader.unfollow_url : reader.follow_url)}>
            <span aria-hidden="true" className="text-xl leading-none">{action.processing ? '…' : reader.viewer_follows ? '✓' : '+'}</span>
        </button>
    ) : (
        <a href={routes.verification || routes.login} className={actionClass} aria-label={`${routes.verification ? 'Verify your email' : 'Log in'} to follow ${reader.name}`} title={label} onClick={routes.verification ? undefined : (event) => { event.preventDefault(); openLoginPanel(); }}><span aria-hidden="true" className="text-xl leading-none">+</span></a>
    ));

    if (variant === 'row') {
        return (
            <article className="grid min-w-0 grid-cols-[minmax(0,1fr)_2.25rem] items-center gap-x-3 gap-y-2 py-4 sm:grid-cols-[minmax(0,1fr)_15rem_2.25rem] sm:gap-x-5">
                <div className="flex min-w-0 items-center gap-3">
                    <Link href={reader.url} aria-label={`View ${reader.name}'s profile`} className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full border border-ink-950/10 bg-brand-sky/25 text-sm font-bold text-ink-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                        {reader.avatar_url ? <img src={reader.avatar_url} alt="" className="size-full object-cover" loading="lazy" /> : reader.initials}
                    </Link>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1"><Link href={reader.url} className="max-w-full truncate text-sm font-bold text-ink-950 hover:text-brand-coral focus-visible:underline">{reader.name}</Link><RelationshipBadge following={reader.viewer_follows} followsYou={reader.follows_viewer} hidden={reader.is_self} showFollowing /></div>
                        <p className="mt-0.5 truncate text-xs text-ink-950/60">@{reader.username} <span aria-hidden="true">·</span> {reader.reviews_count} {reader.reviews_count === 1 ? 'review' : 'reviews'}</p>
                    </div>
                </div>
                <dl className="col-start-1 row-start-2 ml-14 flex flex-wrap gap-x-4 gap-y-1 sm:col-start-2 sm:row-start-1 sm:ml-0 sm:grid sm:grid-cols-3 sm:gap-0">
                    {[
                        { label: 'Completed', value: reader.completed_count, path: <><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></> },
                        { label: 'Public lists', value: reader.lists_count, path: <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" /> },
                        { label: 'Followers', value: reader.followers_count, path: <><circle cx="9" cy="8" r="3" /><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v3" /></> },
                    ].map((metric) => <div key={metric.label} className="inline-flex items-center gap-1.5 text-xs text-ink-950/65 sm:justify-center"><dt className="inline-flex items-center"><span className="sr-only">{metric.label}</span><svg aria-hidden="true" className="size-3.5 text-brand-coral" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">{metric.path}</svg></dt><dd>{metric.value}</dd></div>)}
                </dl>
                <div className="col-start-2 row-start-1 flex justify-end sm:col-start-3">{followControl || <span className="sr-only">Your profile</span>}</div>
            </article>
        );
    }

    return (
        <article className="flex min-w-0 flex-col items-center text-center">
            <div className="relative">
                <Link href={reader.url} aria-label={`View ${reader.name}'s profile`} className="grid size-24 place-items-center overflow-hidden rounded-full border border-ink-950/10 bg-brand-sky/25 text-2xl font-bold text-ink-950 transition hover:border-brand-coral focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral sm:size-28 lg:size-30">
                    {reader.avatar_url ? <img src={reader.avatar_url} alt="" className="size-full object-cover" loading="lazy" /> : reader.initials}
                </Link>
                {followControl}
            </div>
            <Link href={reader.url} className="mt-3 max-w-full truncate text-sm font-bold text-ink-950 hover:text-brand-coral focus-visible:underline">{reader.name}</Link>
            {reader.is_official && <span className="mt-1 rounded-sm bg-brand-coral/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-ink-950">Official</span>}
            <p className="mt-0.5 w-full truncate text-xs text-ink-950/60">@{reader.username}</p>
            <p className="mt-2 text-[11px] leading-5 text-ink-950/65">{reader.followers_count} {reader.followers_count === 1 ? 'follower' : 'followers'} <span aria-hidden="true">·</span> {reader.is_official ? `${reader.lists_count} ${reader.lists_count === 1 ? 'list' : 'lists'}` : `${reader.reviews_count} ${reader.reviews_count === 1 ? 'review' : 'reviews'}`}</p>
            <div className="mt-2 grid min-h-5 place-items-center text-[10px] font-semibold text-ink-950/65">
                {reader.is_self ? 'Your profile' : <RelationshipBadge following={reader.viewer_follows} followsYou={reader.follows_viewer} showFollowing />}
            </div>
            {!reader.is_official && <div className="mt-2 grid w-full max-w-44 grid-cols-4 gap-1" aria-label={`Favorite literature of ${reader.name}`}>
                {reader.favorites.map((literature) => (
                    <Link key={literature.id} href={literature.url} title={literature.title} aria-label={literature.title} className="min-w-0 rounded-sm transition hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                        <LiteratureCover literature={literature} className="aspect-[2/3] rounded-sm" alt={false} />
                    </Link>
                ))}
                {Array.from({ length: Math.max(0, 4 - reader.favorites.length) }, (_, index) => <span key={index} aria-hidden="true" className="aspect-[2/3] rounded-sm border border-dashed border-ink-950/10 bg-white/20" />)}
                {reader.favorites.length === 0 && <span className="sr-only">No favorite literature yet.</span>}
            </div>}
        </article>
    );
}
