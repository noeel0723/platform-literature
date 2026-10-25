import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import EmptyState from '../../Components/EmptyState';
import { LiteratureCover, Pagination, ProfileContentContainer } from '../../Components/ProfilePageUi';

export default function ProfileLists({ profile, navigation, lists, isOwner, createUrl }) {
    return (
        <>
            <Head title={`${profile.name} Lists`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="py-7 lg:py-9">
                <div className="mx-auto max-w-5xl">
                    <div className="flex items-center justify-between gap-4">
                        <h1 className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">My lists</h1>
                        {isOwner && (
                            <Link href={createUrl} className="inline-flex min-h-9 items-center justify-center rounded-md bg-ink-950 px-4 text-xs font-bold text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                                New list
                            </Link>
                        )}
                    </div>

                    {lists.data.length === 0 ? (
                        <EmptyState
                            className="mt-5"
                            title="No custom lists yet."
                            description={isOwner ? 'Create your first list to organize literature your way.' : 'This reader has not shared any public lists yet.'}
                            action={isOwner ? { href: createUrl, label: 'New list' } : null}
                        />
                    ) : (
                        <div className="mt-5 divide-y divide-ink-950/10 overflow-hidden rounded-xl border border-ink-950/10 bg-white/50">
                            {lists.data.map((list) => (
                                <article key={list.id} className="group grid gap-3 px-4 py-4 transition-colors hover:bg-brand-sky/10 sm:px-5 md:grid-cols-[220px_minmax(0,1fr)_auto] md:items-center md:gap-5">
                                    <Link href={list.url} className="grid w-full max-w-[220px] grid-cols-5 gap-1.5" aria-label={`View ${list.title}`}>
                                        {list.previews.map((literature) => (
                                            <LiteratureCover key={literature.id} literature={literature} className="aspect-[2/3] rounded-sm" />
                                        ))}
                                        {Array.from({ length: Math.max(0, 5 - list.previews.length) }).map((_, index) => (
                                            <span key={`empty-${index}`} className="aspect-[2/3] rounded-sm border border-dashed border-ink-950/15 bg-brand-cream/40" aria-hidden="true" />
                                        ))}
                                    </Link>
                                    <div className="min-w-0">
                                        <Link href={list.url} className="font-serif text-lg font-bold text-ink-950 transition-colors group-hover:text-brand-coral focus-visible:underline sm:text-xl">{list.title}</Link>
                                        <p className="mt-0.5 line-clamp-2 text-sm leading-5 text-ink-950/55">{list.description || 'No description.'}</p>
                                        <p className="mt-2 text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/50">
                                            {list.items_count} literature · {list.is_private ? 'Private' : 'Public'} · {list.is_ranked ? 'Ranked' : 'Unranked'}
                                        </p>
                                    </div>
                                    {list.edit_url && (
                                        <Link href={list.edit_url} className="inline-flex w-fit items-center gap-1.5 self-start text-[0.65rem] font-bold uppercase tracking-[0.12em] text-ink-950/55 transition hover:text-brand-coral md:self-center">
                                            <span aria-hidden="true">✎</span> Edit
                                        </Link>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}

                    <Pagination paginator={lists} label="Custom lists pagination" />
                </div>
            </ProfileContentContainer>
        </>
    );
}
