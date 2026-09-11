import { Link } from '@inertiajs/react';

const coverThemes = {
    coral: 'from-brand-coral via-brand-cream to-brand-sky text-ink-950',
    sky: 'from-brand-sky via-brand-cream to-ink-950 text-ink-950',
    cream: 'from-brand-cream via-brand-sky to-brand-cream text-ink-950',
    deep: 'from-ink-950 via-brand-cream to-brand-coral text-ink-950',
    mixed: 'from-brand-cream via-brand-coral to-brand-sky text-ink-950',
};

export default function LiteratureDetailHero({ literature }) {
    const coverTheme = coverThemes[literature.theme]
        ?? 'from-brand-cream via-brand-sky to-brand-coral text-ink-950';

    return (
        <>
            <div className={`relative aspect-[2/3] overflow-hidden border border-ink-950/20 bg-linear-to-br ${coverTheme} shadow-[0_14px_36px_rgba(47,58,85,0.12)]`}>
                {literature.cover_url ? (
                    <>
                        <img
                            src={literature.cover_url}
                            alt={`Cover of ${literature.title}`}
                            className="absolute inset-0 size-full object-cover"
                        />
                        <div className="absolute inset-0 bg-linear-to-t from-ink-950/80 via-transparent to-ink-950/25" />
                    </>
                ) : (
                    <div className="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]" />
                )}

                <div className={`absolute inset-x-0 top-0 flex justify-between p-4 text-xs font-bold uppercase tracking-wider ${literature.cover_url ? 'text-brand-cream' : ''}`}>
                    <span>{literature.type_label}</span>
                    <span>{literature.year}</span>
                </div>

                <div className={`absolute inset-x-0 bottom-0 p-6 ${literature.cover_url ? 'text-brand-cream' : ''}`}>
                    {!literature.cover_url && (
                        <span className="font-serif text-6xl font-bold leading-none">
                            {literature.initials}
                        </span>
                    )}
                    <p className="mt-4 border-t border-current/40 pt-4 text-xs font-bold uppercase tracking-[0.18em]">
                        {literature.source}
                    </p>
                </div>
            </div>

            <div className="self-start">
                <h1 className="text-4xl font-bold leading-[0.98] tracking-tight text-ink-950 sm:text-5xl lg:text-6xl">
                    {literature.title}
                </h1>

                <div className="mt-4 flex flex-wrap items-center gap-3 text-sm font-semibold uppercase tracking-wider text-ink-950/60">
                    <span className="bg-brand-coral px-2.5 py-1 text-brand-cream">
                        {literature.type_label}
                    </span>
                    <span>{literature.year}</span>
                    {literature.is_curated && (
                        <span className="border border-brand-coral/40 px-2.5 py-1 text-brand-coral">
                            Curated
                        </span>
                    )}
                </div>

                {literature.edition_title && (
                    <p className="mt-3 text-sm text-ink-950/55">
                        {literature.alternate_title_label}:{' '}
                        <span className="font-semibold text-ink-950/75">
                            {literature.edition_title}
                        </span>
                    </p>
                )}

                <p className="mt-4 text-lg text-ink-950/60">
                    By{' '}
                    {literature.author_links.length > 0 ? (
                        literature.author_links.map((author, index) => (
                            <span key={`${author.url}-${author.name}`}>
                                <Link
                                    href={author.url}
                                    className="font-semibold text-ink-950 underline decoration-brand-coral/40 underline-offset-4 transition hover:text-brand-coral"
                                >
                                    {author.name}
                                </Link>
                                {index < literature.author_links.length - 1 && (
                                    <span className="text-ink-950/35"> &amp; </span>
                                )}
                            </span>
                        ))
                    ) : (
                        <span className="font-semibold text-ink-950">Author unavailable</span>
                    )}
                </p>

                <p className="mt-7 max-w-2xl text-lg font-medium uppercase leading-7 tracking-[0.08em] text-ink-950/70">
                    {literature.tagline}
                </p>
                <p className="mt-5 max-w-2xl text-base leading-8 text-ink-950/75">
                    {literature.synopsis}
                </p>
            </div>
        </>
    );
}
