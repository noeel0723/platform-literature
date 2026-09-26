import SiteContainer from './SiteContainer';

const features = [
    {
        title: 'Track every read',
        description: 'Keep completed stories and your next reads together.',
        icon: <><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v17H6.5A2.5 2.5 0 0 0 4 22z" /><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v17h5.5A2.5 2.5 0 0 1 20 22z" /></>,
    },
    {
        title: 'Find your next story',
        description: 'Explore novels, manga, manhwa, and western comics.',
        icon: <><circle cx="12" cy="12" r="9" /><path d="m15.5 8.5-2.3 4.7-4.7 2.3 2.3-4.7z" /></>,
    },
    {
        title: 'Rate and review',
        description: 'Share what you loved and what stayed with you.',
        icon: <path d="m12 2.5 2.9 6 6.6 1-4.8 4.7 1.1 6.6-5.8-3.1-5.8 3.1 1.1-6.6-4.8-4.7 6.6-1z" />,
    },
    {
        title: 'Keep a reading diary',
        description: 'Remember the stories that shaped your reading days.',
        icon: <><rect x="4" y="5" width="16" height="16" rx="2" /><path d="M8 3v4M16 3v4M4 10h16M8 14h3M8 17h6" /></>,
    },
    {
        title: 'Create your own lists',
        description: 'Collect favorites and organize stories your way.',
        icon: <><path d="M9 6h11M9 12h11M9 18h11" /><path d="M4 6h.01M4 12h.01M4 18h.01" strokeLinecap="round" strokeWidth="3" /></>,
    },
    {
        title: 'Read with others',
        description: 'Follow readers and join conversations about books you love.',
        icon: <><circle cx="9" cy="8" r="3" /><path d="M3.5 20v-2a5.5 5.5 0 0 1 11 0v2zM16 5.2a3 3 0 0 1 0 5.6M17 14a5 5 0 0 1 3.5 4.8V20" /></>,
    },
];

export default function GuestLandingFeatures() {
    return (
        <section aria-labelledby="guest-features-heading">
            <SiteContainer className="pb-12 pt-2 sm:pb-14">
                <div className="mb-5 border-b border-ink-950/15 pb-3">
                    <p className="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-brand-coral">Made for readers</p>
                    <h2 id="guest-features-heading" className="mt-1 font-serif text-xl font-bold tracking-tight text-ink-950 sm:text-2xl">
                        Your reading life, in one place
                    </h2>
                </div>

                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <li key={feature.title} className="flex min-h-28 items-start gap-3 rounded-lg border border-ink-950/10 bg-white/55 p-4 transition-colors hover:border-brand-sky hover:bg-white/75">
                            <span className="grid size-9 shrink-0 place-items-center rounded-md bg-brand-sky/25 text-ink-950" aria-hidden="true">
                                <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
                                    {feature.icon}
                                </svg>
                            </span>
                            <div className="min-w-0">
                                <h3 className="text-sm font-bold text-ink-950">{feature.title}</h3>
                                <p className="mt-1 text-xs leading-5 text-ink-950/65">{feature.description}</p>
                            </div>
                        </li>
                    ))}
                </ul>
            </SiteContainer>
        </section>
    );
}
