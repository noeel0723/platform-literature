import { Link } from '@inertiajs/react';

import { openLoginPanel } from '../Support/loginPanel';
import SiteContainer from './SiteContainer';

function HeroArtwork({ literature }) {
    if (!literature) {
        return (
            <div
                className="relative aspect-[16/10] overflow-hidden rounded-sm border border-brand-plate/15 bg-linear-to-br from-brand-sky/25 via-brand-stem to-ink-950 shadow-[0_22px_60px_rgba(16,47,98,0.24)]"
                aria-hidden="true"
            >
                <div className="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(248,243,240,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(248,243,240,0.12)_1px,transparent_1px)] [background-size:32px_32px]" />
                <div className="absolute inset-0 bg-linear-to-r from-brand-stem/45 via-transparent to-ink-950/55" />
                <div className="absolute inset-x-7 bottom-7 border-t border-brand-plate/30 pt-4 text-xs font-semibold uppercase tracking-[0.2em] text-brand-plate/70">
                    Stories live here
                </div>
            </div>
        );
    }

    return (
        <Link
            href={literature.url}
            className="group relative block aspect-[16/10] overflow-hidden rounded-sm border border-brand-plate/15 bg-ink-950 shadow-[0_22px_60px_rgba(16,47,98,0.28)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral"
        >
            <img
                src={literature.backdrop_url}
                alt={`Artwork for ${literature.title}`}
                className="absolute inset-0 size-full object-cover object-center transition duration-500 group-hover:scale-[1.025]"
                loading="eager"
                fetchPriority="high"
            />
            <div className="absolute inset-0 bg-linear-to-r from-brand-stem/45 via-transparent to-ink-950/25" />
            <div className="absolute inset-x-0 bottom-0 bg-linear-to-t from-ink-950/90 via-ink-950/45 to-transparent px-5 pb-4 pt-14 text-brand-plate">
                <p className="text-[0.62rem] font-bold uppercase tracking-[0.18em] text-brand-coral">Featured from</p>
                <p className="mt-1 truncate text-sm font-semibold">{literature.title}</p>
                <p className="mt-0.5 text-[0.68rem] uppercase tracking-[0.12em] text-brand-plate/65">{literature.type_label}</p>
            </div>
        </Link>
    );
}

export default function GuestLandingHero({ literature, routes }) {
    return (
        <section className="relative overflow-hidden border-b border-brand-plate/10 bg-brand-stem text-brand-plate">
            <div className="pointer-events-none absolute inset-0 bg-linear-to-br from-ink-950/22 via-transparent to-brand-sky/10" aria-hidden="true" />
            <SiteContainer className="relative grid min-h-[430px] items-center gap-10 py-12 sm:py-14 lg:min-h-[480px] lg:grid-cols-[minmax(0,0.9fr)_minmax(360px,1.1fr)] lg:gap-12 lg:py-16">
                <div className="max-w-xl">
                    <p className="text-xs font-bold uppercase tracking-[0.24em] text-brand-coral">The reading haven</p>
                    <h1 className="mt-5 font-serif text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-brand-plate sm:text-5xl lg:text-[2.75rem]">
                        <span className="block lg:whitespace-nowrap">Track every story</span>
                        <span className="block">you read.</span>
                    </h1>
                    <p className="mt-6 max-w-lg text-sm leading-7 text-brand-plate/72 sm:text-base">
                        Keep a diary of the novels, manga, manhwa, and comics you love. Rate them, review them, discover new literature, and see what other readers are exploring.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-x-5 gap-y-3">
                        <Link
                            href={routes.register}
                            className="inline-flex min-h-11 items-center justify-center rounded-sm bg-brand-coral px-5 py-3 text-xs font-bold uppercase tracking-[0.1em] text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-brand-coral/90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-plate"
                        >
                            Get started — it&apos;s free
                        </Link>
                        <span className="text-sm text-brand-plate/65">
                            Already a member?{' '}
                            <button
                                type="button"
                                onClick={openLoginPanel}
                                className="font-bold text-brand-plate underline decoration-brand-coral decoration-2 underline-offset-4 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral"
                            >
                                Log in
                            </button>
                        </span>
                    </div>
                </div>

                <HeroArtwork literature={literature} />
            </SiteContainer>
        </section>
    );
}
