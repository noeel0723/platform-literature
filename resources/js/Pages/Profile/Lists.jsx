import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { LiteratureCover, Pagination, ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

export default function ProfileLists({ profile, navigation, lists, isOwner, createUrl }) {
    return (
        <>
            <Head title={`${profile.name} Lists`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="py-7 lg:py-9">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="min-w-0 flex-1">
                        <ProfilePageHeading eyebrow="Curated collections" title="Lists" count={`${lists.total} lists`} />
                    </div>
                    {isOwner && (
                        <Link href={createUrl} className="inline-flex min-h-9 items-center justify-center bg-ink-950 px-4 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral">
                            New list
                        </Link>
                    )}
                </div>

                {lists.data.length === 0 ? (
                    <div className="mt-5 border-y border-dashed border-ink-950/20 px-5 py-10 text-center text-sm text-ink-950/55">No custom lists yet.</div>
                ) : (
                    <div className="mt-5 border-y border-ink-950/15">
                        {lists.data.map((list) => (
                            <article key={list.id} className="grid gap-4 border-b border-ink-950/10 py-3.5 last:border-b-0 sm:grid-cols-[15rem_minmax(0,1fr)_auto] sm:items-center">
                                <Link href={list.url} className="grid grid-cols-5 gap-1" aria-label={`View ${list.title}`}>
                                    {list.previews.map((literature) => (
                                        <LiteratureCover key={literature.id} literature={literature} className="aspect-[2/3]" />
                                    ))}
                                    {Array.from({ length: Math.max(0, 5 - list.previews.length) }).map((_, index) => (
                                        <span key={`empty-${index}`} className="aspect-[2/3] border border-dashed border-ink-950/10 bg-white/20" aria-hidden="true" />
                                    ))}
                                </Link>
                                <div className="min-w-0">
                                    <Link href={list.url} className="font-serif text-lg font-bold text-ink-950 transition hover:text-brand-coral">{list.title}</Link>
                                    <p className="mt-0.5 line-clamp-2 text-sm leading-5 text-ink-950/55">{list.description || 'No description.'}</p>
                                    <p className="mt-1.5 text-[0.62rem] font-bold uppercase tracking-[0.14em] text-ink-950/45">
                                        {list.items_count} literature · {list.is_private ? 'Private' : 'Public'}{list.is_ranked ? ' · Ranked' : ''}
                                    </p>
                                </div>
                                {list.edit_url && (
                                    <Link href={list.edit_url} className="inline-flex items-center gap-1 text-[0.62rem] font-bold uppercase tracking-[0.12em] text-ink-950/45 hover:text-brand-coral">
                                        <span aria-hidden="true">✎</span> Edit
                                    </Link>
                                )}
                            </article>
                        ))}
                    </div>
                )}

                <Pagination paginator={lists} label="Custom lists pagination" />
            </ProfileContentContainer>
        </>
    );
}
