import SiteContainer from './SiteContainer';

export default function LiteratureDiscoveryContext({ catalog = false, results = false }) {
    return (
        <SiteContainer as="header" className="py-4 lg:py-5">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 className="text-lg font-bold text-ink-950">{catalog ? 'Catalog' : 'Literature'}</h1>
                <span className="rounded-sm bg-brand-sky/20 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-ink-950/65">
                    {catalog ? 'External discovery' : 'On Literahaven'}
                </span>
            </div>
            <p className="mt-1 max-w-3xl text-xs leading-5 text-ink-950/65">
                {catalog
                    ? results
                        ? 'More matches from the collection. These results are already available on Literahaven; return to Catalog to search external sources.'
                        : 'Looking for something new? Search external sources and bring matching titles into Literahaven. Catalog searches can take a little longer.'
                    : 'Explore literature already available on Literahaven, with community ratings, reviews, and this week’s popular reads. Search and filters use our local collection.'}
            </p>
        </SiteContainer>
    );
}
