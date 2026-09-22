import { Link } from '@inertiajs/react';

import LiteratureCard from './LiteratureCard';
import SiteContainer from './SiteContainer';

export default function PopularThisWeek({ literatures, browseUrl }) {
    if (literatures.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="popular-this-week-heading">
            <SiteContainer className="py-10 sm:py-12">
                <div className="flex items-end justify-between gap-5 border-b border-ink-950/15 pb-3">
                    <div>
                        <p className="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-brand-coral">Popular this week</p>
                        <h2 id="popular-this-week-heading" className="mt-1 font-serif text-xl font-bold tracking-tight text-ink-950 sm:text-2xl">
                            What readers are loving right now
                        </h2>
                    </div>
                    <Link href={browseUrl} className="shrink-0 text-xs font-bold uppercase tracking-[0.12em] text-ink-950 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral">
                        Browse all <span aria-hidden="true">→</span>
                    </Link>
                </div>

                <ol className="mt-5 grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                    {literatures.map((literature, index) => (
                        <li key={literature.id} className="relative min-w-0">
                            <span className="absolute left-1.5 top-1.5 z-10 grid size-6 place-items-center rounded-full bg-ink-950 text-[0.65rem] font-bold text-brand-cream shadow-sm" aria-label={`Rank ${index + 1}`}>
                                {index + 1}
                            </span>
                            <LiteratureCard literature={literature} compact />
                        </li>
                    ))}
                </ol>
            </SiteContainer>
        </section>
    );
}
