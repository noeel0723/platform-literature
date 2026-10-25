import { Link } from '@inertiajs/react';

import LiteratureCardPreview from './LiteratureCardPreview';

const posterThemes = {
    coral: 'from-brand-coral via-brand-cream to-brand-sky text-ink-950',
    sky: 'from-brand-sky via-brand-cream to-ink-950 text-ink-950',
    cream: 'from-brand-cream via-brand-sky to-brand-cream text-ink-950',
    deep: 'from-ink-950 via-brand-cream to-brand-coral text-ink-950',
    mixed: 'from-brand-cream via-brand-coral to-brand-sky text-ink-950',
};

export default function LiteratureCard({ literature, compact = false }) {
    const posterTheme = posterThemes[literature.theme]
        ?? 'from-brand-cream via-brand-sky to-brand-coral text-ink-950';
    const badgeSpacing = compact
        ? 'gap-1 p-1.5'
        : 'gap-1.5 p-2 sm:gap-2 sm:p-3';
    const typeBadge = compact
        ? 'px-1 py-0.5 text-[11px] leading-4 tracking-[0.02em]'
        : 'px-1.5 py-1 text-[11px] leading-4 tracking-[0.06em] sm:px-2 sm:text-xs';
    const yearBadge = compact
        ? 'px-1 py-0.5 text-[11px] leading-4'
        : 'px-1.5 py-1 text-[11px] leading-4 sm:px-2 sm:text-xs';

    return (
        <article
            className="group min-w-0"
            data-literature-card
            {...(compact ? { 'data-literature-card-size': 'compact' } : {})}
        >
            <LiteratureCardPreview literature={literature} compact={compact}>
                <Link
                    href={literature.url}
                    className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral focus-visible:ring-offset-4 focus-visible:ring-offset-brand-cream"
                >
                    <div className={`poster-shine relative aspect-[2/3] overflow-hidden border border-ink-950/15 bg-linear-to-br ${posterTheme} shadow-[0_10px_28px_rgba(47,58,85,0.10)] transition duration-300 group-hover:-translate-y-1 group-hover:border-brand-coral`}>
                        {literature.cover_url ? (
                            <>
                                <img
                                    src={literature.cover_url}
                                    alt={`Cover of ${literature.title}`}
                                    className="absolute inset-0 size-full object-cover"
                                    loading="lazy"
                                />
                                <div className="absolute inset-0 bg-linear-to-t from-ink-950/80 via-transparent to-ink-950/25" />
                            </>
                        ) : (
                            <div className="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]" />
                        )}

                        <div className={`absolute inset-x-0 top-0 flex flex-wrap items-start justify-between ${badgeSpacing} ${literature.cover_url ? 'text-brand-cream' : ''}`}>
                            <span title={literature.type_label} className={`max-w-full break-words bg-ink-950/85 font-semibold uppercase text-brand-cream backdrop-blur ${typeBadge}`}>
                                {literature.type_label}
                            </span>
                            <span className={`max-w-full break-words bg-ink-950/85 font-semibold tabular-nums text-brand-cream backdrop-blur ${yearBadge}`}>
                                {literature.year}
                            </span>
                        </div>

                        <div className={`absolute inset-x-0 bottom-0 ${compact ? 'p-2' : 'p-4'} ${literature.cover_url ? 'text-brand-cream' : ''}`}>
                            {!literature.cover_url && (
                                <span className={`block font-serif font-bold leading-none tracking-tight ${compact ? 'text-2xl' : 'text-4xl sm:text-5xl'}`}>
                                    {literature.initials}
                                </span>
                            )}
                            <span title={literature.source} className={`line-clamp-2 break-words border-t border-current/40 font-semibold uppercase ${compact ? 'mt-1.5 pt-1.5 text-[11px] leading-4 tracking-[0.04em]' : 'mt-3 pt-3 text-xs leading-5 tracking-[0.08em] sm:text-[13px]'}`}>
                                {literature.source}
                            </span>
                        </div>
                    </div>
                </Link>
            </LiteratureCardPreview>
            <Link href={literature.url} className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral focus-visible:ring-offset-4 focus-visible:ring-offset-brand-cream">
                <div className={compact ? 'pt-2' : 'pt-3'}>
                    <h3 title={literature.title} className={`line-clamp-2 font-semibold text-ink-950 [overflow-wrap:anywhere] transition group-hover:text-brand-coral ${compact ? 'text-sm leading-5' : 'text-base leading-6'}`}>
                        {literature.title}
                    </h3>
                    <p title={literature.author} className={`truncate leading-5 text-ink-950/75 ${compact ? 'mt-0.5 text-xs sm:text-[13px]' : 'mt-1 text-[13px] sm:text-sm'}`}>
                        {literature.author}
                    </p>
                </div>
            </Link>
        </article>
    );
}
