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
                            <article key={list.id} className="grid gap-4 border-b border-ink-950/10 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_15rem] sm:items-center">
                                <div className="min-w-0">
                                    <Link href={list.url} className="font-serif text-xl font-bold text-ink-950 transition hover:text-brand-coral">{list.title}</Link>
                                    <p className="mt-1 line-clamp-2 text-sm leading-6 text-ink-950/55">{list.description || 'No description.'}</p>
                                    <p className="mt-2 text-[0.62rem] font-bold uppercase tracking-[0.14em] text-ink-950/45">{list.items_count} items{list.is_private ? ' · Private' : ''}</p>
                                </div>
                                <Link href={list.url} className="grid grid-cols-4 gap-1">
                                    {list.previews.map((literature) => (
                                        <LiteratureCover key={literature.id} literature={literature} className="aspect-[2/3]" />
                                    ))}
                                </Link>
                            </article>
                        ))}
                    </div>
                )}

                <Pagination paginator={lists} label="Custom lists pagination" />
            </ProfileContentContainer>
        </>
    );
}
