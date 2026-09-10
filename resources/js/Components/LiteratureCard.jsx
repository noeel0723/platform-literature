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
        : 'gap-2 p-3';
    const typeBadge = compact
        ? 'px-1.5 py-1 text-[0.5rem] tracking-[0.1em]'
        : 'px-2 py-1 text-[0.62rem] tracking-[0.14em]';
    const yearBadge = compact
        ? 'px-1.5 py-1 text-[0.55rem]'
        : 'px-2 py-1 text-xs';

    return (
        <article
            className="group min-w-0"
            data-literature-card
            {...(compact ? { 'data-literature-card-size': 'compact' } : {})}
        >
            <a
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

                    <div className={`absolute inset-x-0 top-0 flex items-start justify-between ${badgeSpacing} ${literature.cover_url ? 'text-brand-cream' : ''}`}>
                        <span className={`bg-ink-950/80 font-semibold uppercase text-brand-cream backdrop-blur ${typeBadge}`}>
                            {literature.type_label}
                        </span>
                        <span className={`bg-ink-950/65 font-semibold text-brand-cream backdrop-blur ${yearBadge}`}>
                            {literature.year}
                        </span>
                    </div>

                    <div className={`absolute inset-x-0 bottom-0 ${compact ? 'p-2' : 'p-4'} ${literature.cover_url ? 'text-brand-cream' : ''}`}>
                        {!literature.cover_url && (
                            <span className={`block font-serif font-bold leading-none tracking-tight ${compact ? 'text-2xl' : 'text-4xl sm:text-5xl'}`}>
                                {literature.initials}
                            </span>
                        )}
                        <span className={`block border-t border-current/40 font-semibold uppercase ${compact ? 'mt-1.5 truncate pt-1.5 text-[0.5rem] tracking-[0.1em]' : 'mt-3 pt-3 text-xs tracking-[0.18em]'}`}>
                            {literature.source}
                        </span>
                    </div>
                </div>

                <div className={compact ? 'pt-2' : 'pt-3'}>
                    <h3 className={`truncate font-semibold text-ink-950 transition group-hover:text-brand-coral ${compact ? 'text-sm leading-5' : ''}`}>
                        {literature.title}
                    </h3>
                    <p className={`truncate text-ink-950/60 ${compact ? 'mt-0.5 text-xs' : 'mt-1 text-sm'}`}>
                        {literature.author}
                    </p>
                </div>
            </a>
        </article>
    );
}
