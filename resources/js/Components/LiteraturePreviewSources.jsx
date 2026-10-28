export default function LiteraturePreviewSources({ sources }) {
    if (!sources) return null;

    return (
        <section aria-label="Data sources" className="border-t border-ink-950/10 px-4 py-3">
            <h3 className="text-[10px] font-semibold uppercase tracking-wider text-brand-coral">Data sources</h3>
            <dl className="mt-2 grid gap-2 text-xs leading-5 [overflow-wrap:anywhere]">
                <div>
                    <dt className="text-ink-950/60">Literature</dt>
                    <dd className="font-medium">{sources.catalog?.length ? sources.catalog.join(' · ') : 'Not available'}</dd>
                </div>
                <div>
                    <dt className="text-ink-950/60">Synopsis</dt>
                    <dd className="font-medium">{sources.synopsis || 'Not available'}</dd>
                </div>
            </dl>
        </section>
    );
}
