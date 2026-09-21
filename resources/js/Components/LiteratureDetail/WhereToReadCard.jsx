export default function WhereToReadCard({ links = [] }) {
    if (links.length === 0) return null;

    return (
        <aside className="mt-4 w-full max-w-[190px] border border-ink-950/15 bg-white/35" aria-labelledby="where-to-read-heading">
            <h2 id="where-to-read-heading" className="border-b border-ink-950/15 px-3 py-2.5 text-[0.65rem] font-bold uppercase tracking-[0.16em] text-ink-950/60">
                Where to read
            </h2>
            <div className="divide-y divide-ink-950/10">
                {links.map((link) => (
                    <a
                        key={link.id}
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex min-h-10 items-center justify-between gap-2 px-3 py-2 text-xs transition hover:bg-brand-sky/25 focus-visible:bg-brand-sky/25"
                    >
                        <span className="min-w-0 truncate font-semibold text-ink-950" title={link.provider}>{link.provider}</span>
                        <span className="shrink-0 border border-brand-coral/40 px-1.5 py-0.5 text-[0.55rem] font-bold uppercase tracking-[0.1em] text-brand-coral">
                            {link.link_type_label}
                        </span>
                    </a>
                ))}
            </div>
        </aside>
    );
}
