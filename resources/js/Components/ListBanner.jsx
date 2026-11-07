import { Link } from '@inertiajs/react';
import SiteContainer from './SiteContainer';

export default function ListBanner({ literature }) {
    if (!literature) return null;
    const image = literature.backdrop_url || literature.cover_url;

    return (
        <div data-list-banner className={`relative isolate overflow-hidden bg-ink-950 ${literature.backdrop_url ? 'h-72 sm:h-88 lg:h-[440px]' : 'h-64 sm:h-72 lg:h-80'}`}>
            {image && <img src={image} alt="" fetchPriority="high" loading="eager" className={`absolute inset-0 -z-10 size-full object-cover object-center ${literature.backdrop_url ? '' : 'scale-105 blur-sm opacity-60'}`} />}
            <div className="absolute inset-0 -z-10 bg-gradient-to-b from-black/55 via-black/10 to-brand-cream" />
            <SiteContainer className="absolute inset-x-0 bottom-20 max-w-6xl text-right">
                <Link href={literature.url} className="inline-block max-w-full rounded px-2 py-1 text-xs text-ink-950/70 hover:text-ink-950 focus-visible:outline-2 focus-visible:outline-brand-coral">
                    {literature.title}{literature.year ? ` (${literature.year})` : ''}
                </Link>
            </SiteContainer>
        </div>
    );
}
