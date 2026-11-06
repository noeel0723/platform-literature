import { Link } from '@inertiajs/react';

import { LiteratureCover } from './ProfilePageUi';

export default function PublicListCard({ list }) {
    const previews = list.previews.slice(0, 5);

    return (
        <article className="group min-w-0">
            <Link href={list.url} aria-label={`View list: ${list.title}`} className="isolate flex aspect-[5/2] overflow-hidden rounded-md border border-ink-950/15 bg-white/40 transition-colors hover:border-brand-coral focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-brand-coral">
                {Array.from({ length: 5 }, (_, index) => (
                    <span key={previews[index]?.id ?? `empty-${index}`} className={`relative h-full w-[28%] min-w-0 shrink-0 ${index > 0 ? '-ml-[10%]' : ''}`} style={{ zIndex: 5 - index }}>
                        {previews[index]
                            ? <LiteratureCover literature={previews[index]} className="size-full rounded-sm" alt={false} />
                            : <span aria-hidden="true" className="block size-full rounded-sm border border-dashed border-ink-950/15 bg-brand-cream" />}
                    </span>
                ))}
            </Link>
            <h3 className="mt-3 font-serif text-lg font-bold leading-snug text-ink-950 [overflow-wrap:anywhere]">
                <Link href={list.url} className="line-clamp-2 transition-colors hover:text-brand-coral focus-visible:underline focus-visible:decoration-brand-coral focus-visible:underline-offset-4">{list.title}</Link>
            </h3>
            <p className="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-ink-950/60">
                <span className="grid size-5 shrink-0 place-items-center overflow-hidden rounded-full bg-brand-sky/25 text-[8px] font-bold text-ink-950" aria-hidden="true">
                    {list.owner.avatar_url ? <img src={list.owner.avatar_url} alt="" className="size-full object-cover" loading="lazy" /> : list.owner.initials}
                </span>
                <span>by</span>
                <Link href={list.owner.url} className="min-w-0 truncate font-semibold text-ink-950 hover:text-brand-coral focus-visible:underline">{list.owner.name}</Link>
            </p>
            {list.description && <p className="mt-2 line-clamp-2 text-xs leading-5 text-ink-950/60 [overflow-wrap:anywhere]">{list.description}</p>}
            <p className="mt-2 text-[10px] font-semibold uppercase tracking-wider text-ink-950/50">{list.items_count} literature · {list.is_ranked ? 'Ranked' : 'Unranked'}</p>
        </article>
    );
}
