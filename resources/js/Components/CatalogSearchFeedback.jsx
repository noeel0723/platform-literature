export default function CatalogSearchFeedback({ pending, query, slow, error, onCancel }) {
    if (!pending && !error) return null;

    return (
        <div className="mx-auto mb-6 max-w-lg rounded-xl border border-ink-950/10 bg-white/60 px-5 py-7 text-center sm:px-8">
            {pending ? (
                <>
                    <div role="status" aria-live="polite" aria-atomic="true">
                        <span aria-hidden="true" className="mx-auto mb-4 block size-7 rounded-full border-2 border-ink-950/10 border-t-brand-coral motion-safe:animate-spin" />
                        <h2 className="text-base font-bold text-ink-950">{query ? 'Searching catalog sources' : 'Loading catalog'}</h2>
                        <p className="mt-2 text-sm leading-6 text-ink-950/65 [overflow-wrap:anywhere]">
                            {query ? <>Finding literature for <strong className="font-semibold text-ink-950">“{query}”</strong> across external sources and our collection.</> : 'Finding titles already available in the collection.'}
                        </p>
                        <p className="mt-2 text-xs leading-5 text-ink-950/60">
                            {slow
                                ? 'Still waiting for catalog sources. Some searches take longer; your results will appear here when ready.'
                                : query ? 'External sources may take a little longer to respond. Please keep this page open.' : 'Your results will appear here shortly.'}
                        </p>
                    </div>
                    <button type="button" onClick={onCancel} className="mt-4 min-h-9 rounded px-3 text-xs font-semibold text-ink-950/65 underline underline-offset-4 hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">Cancel search</button>
                </>
            ) : <p role="alert" className="text-sm leading-6 text-ink-950">{error}</p>}
        </div>
    );
}
