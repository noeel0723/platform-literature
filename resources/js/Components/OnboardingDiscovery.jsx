function DiscoveryLiterature({ literature }) {
    return (
        <a href={literature.url} target="_blank" rel="noopener noreferrer" className="flex min-w-0 items-start gap-3 rounded-md border border-ink-950/10 bg-white/50 p-3 hover:border-brand-coral/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
            {literature.cover_url
                ? <img src={literature.cover_url} alt="" loading="lazy" className="h-15 w-10 shrink-0 rounded-sm object-cover" />
                : <span aria-hidden="true" className="grid h-15 w-10 shrink-0 place-items-center rounded-sm bg-brand-sky/25 text-xs font-bold text-ink-950">{literature.initials}</span>}
            <span className="min-w-0">
                <span className="line-clamp-2 text-xs font-bold leading-5 text-ink-950">{literature.title}</span>
                <span className="mt-0.5 block truncate text-xs text-ink-950/65">{literature.author}</span>
                <span className="mt-1 block text-[11px] text-ink-950/50">{literature.type_label}{literature.year ? ` · ${literature.year}` : ''}</span>
                <span className="sr-only"> (opens in a new tab)</span>
            </span>
        </a>
    );
}

export default function OnboardingDiscovery({ discovery, search, onSearch, browseUrl, disabled = false }) {
    return (
        <div>
            <form onSubmit={onSearch}>
                <label htmlFor="onboarding-search" className="mb-2 block text-xs font-semibold text-ink-950">Find literature</label>
                <div className="flex min-w-0 overflow-hidden rounded-md border border-ink-950/20 bg-white/70 focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/20">
                    <input id="onboarding-search" name="q" type="search" value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} placeholder="Title or author…" autoComplete="off" maxLength={100} disabled={disabled || search.processing} className="h-10 min-w-0 flex-1 bg-transparent px-3 text-sm text-ink-950 outline-none placeholder:text-ink-950/40" />
                    <button type="submit" disabled={disabled || search.processing} className="shrink-0 bg-ink-950 px-4 text-xs font-bold text-brand-cream hover:bg-brand-coral disabled:opacity-50">{search.processing ? 'Searching…' : 'Search'}</button>
                </div>
                <p className="mt-2 text-xs leading-5 text-ink-950/50">Search titles and authors in Literahaven’s local library. Open a read in a new tab, then continue here.</p>
                {search.errors.q && <p role="alert" className="mt-2 text-xs text-red-700">{search.errors.q}</p>}
            </form>

            <div className="mt-5" aria-live="polite" aria-busy={search.processing}>
                {discovery.query ? (
                    <section aria-labelledby="onboarding-results-heading">
                        <h2 id="onboarding-results-heading" className="mb-3 text-xs font-bold text-ink-950">Search results for “{discovery.query}”</h2>
                        {discovery.results.length > 0
                            ? <div className="grid gap-2 sm:grid-cols-2">{discovery.results.map((literature) => <DiscoveryLiterature key={literature.id} literature={literature} />)}</div>
                            : <p className="text-xs leading-5 text-ink-950/60">No matching literature in the local library. Try another title or author.</p>}
                        <a href={browseUrl} target="_blank" rel="noopener noreferrer" className="mt-3 inline-block text-xs font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Browse local results<span className="sr-only"> (opens in a new tab)</span></a>
                    </section>
                ) : (
                    <div className="space-y-4">
                        <h2 className="text-xs font-bold text-ink-950">Popular reads in your favorite formats</h2>
                        {discovery.groups.map((group) => (
                            <section key={group.format} aria-label={`Popular ${group.format}`}>
                                <h3 className="mb-2 text-[11px] font-bold uppercase tracking-wider text-brand-coral">{group.format}</h3>
                                {group.literatures.length > 0
                                    ? <div className="grid gap-2 sm:grid-cols-2">{group.literatures.map((literature) => <DiscoveryLiterature key={literature.id} literature={literature} />)}</div>
                                    : <p className="text-xs leading-5 text-ink-950/60">No {group.format.toLowerCase()} in the local library yet. You can explore another format or continue.</p>}
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
