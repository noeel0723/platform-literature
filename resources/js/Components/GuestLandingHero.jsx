import { Link } from '@inertiajs/react';
import { useState } from 'react';

import { guestHeroBackdrop, guestHeroFocalStyle } from '../Support/guestHero';
import { openLoginPanel } from '../Support/loginPanel';
import SiteContainer from './SiteContainer';

export default function GuestLandingHero({ literature, onRegisterOpen, registerTriggerRef, verificationUrl }) {
    const [failedBackdrop, setFailedBackdrop] = useState(null);
    const selectedBackdrop = guestHeroBackdrop(literature);
    const backdropUrl = guestHeroBackdrop(literature, failedBackdrop);

    return (
        <section data-guest-hero data-backdrop-state={backdropUrl ? 'image' : 'fallback'} style={guestHeroFocalStyle(literature?.focal_position)} className="relative isolate min-h-[420px] overflow-hidden bg-ink-950 text-brand-plate sm:min-h-[450px] lg:min-h-[480px]">
            <div
                className="absolute inset-0 bg-ink-950 [background-image:radial-gradient(ellipse_at_75%_20%,rgba(143,200,255,0.22),transparent_55%),linear-gradient(135deg,rgba(255,107,88,0.12),transparent_60%)]"
                aria-hidden="true"
            />
            {backdropUrl && (
                <img
                    key={backdropUrl}
                    src={backdropUrl}
                    alt={`Artwork for ${literature.title}`}
                    className="absolute inset-0 size-full object-cover [object-position:var(--hero-focal-mobile)] sm:[object-position:var(--hero-focal-tablet)] lg:[object-position:var(--hero-focal-desktop)]"
                    loading="eager"
                    fetchPriority="high"
                    decoding="async"
                    onError={() => setFailedBackdrop(backdropUrl)}
                />
            )}

            <div className="pointer-events-none absolute inset-0 bg-black/20" aria-hidden="true" />
            <div className="pointer-events-none absolute inset-0 bg-linear-to-r from-black/85 via-black/75 to-black/65 sm:from-black/80 sm:via-black/55 sm:to-black/20" aria-hidden="true" />
            <div className="pointer-events-none absolute inset-x-0 top-0 h-32 bg-linear-to-b from-black/60 to-transparent" aria-hidden="true" />
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-linear-to-b from-transparent via-brand-cream/30 to-brand-cream sm:h-24 lg:h-28" aria-hidden="true" />

            <SiteContainer className="relative z-10 flex min-h-[420px] items-center pb-24 pt-28 sm:min-h-[450px] lg:min-h-[480px] lg:pt-24">
                <div className="max-w-xl lg:max-w-2xl">
                    <h1 className="font-serif text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-brand-plate drop-shadow-sm sm:text-5xl lg:text-6xl">
                        <span className="block sm:whitespace-nowrap">Track every story</span>
                        <span className="block">you read.</span>
                    </h1>
                    <p className="mt-6 max-w-lg text-sm leading-7 text-brand-plate/90 sm:text-base">
                        Keep a diary of the novels, manga, manhwa, and comics you love. Rate them, review them, discover new literature, and see what other readers are exploring.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-x-5 gap-y-3">
                        <button
                            ref={registerTriggerRef}
                            type="button"
                            onClick={onRegisterOpen}
                            className="inline-flex min-h-11 items-center justify-center rounded-sm bg-brand-coral px-5 py-3 text-xs font-bold uppercase tracking-[0.1em] text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-brand-coral/90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-plate"
                        >
                            {verificationUrl ? 'Verify your email' : 'Join us! Sign up here!'}
                        </button>
                        {!verificationUrl && <span className="text-sm text-brand-plate/85">
                            Already a member?{' '}
                            <button
                                type="button"
                                onClick={openLoginPanel}
                                className="font-bold text-brand-plate underline decoration-brand-coral decoration-2 underline-offset-4 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral"
                            >
                                Log in
                            </button>
                        </span>}
                    </div>
                </div>
            </SiteContainer>

            {selectedBackdrop && literature?.url && (
                <Link
                    href={literature.url}
                    title={literature.title}
                    className="absolute bottom-4 right-5 z-20 max-w-[70%] truncate rounded-sm bg-brand-cream/85 px-2 py-1 text-xs tracking-wide text-ink-950/85 transition-colors hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral sm:bottom-auto sm:right-4 sm:top-1/2 sm:max-h-[60%] sm:max-w-none sm:-translate-y-1/2 sm:rotate-180 sm:bg-black/55 sm:px-1.5 sm:py-2 sm:text-sm sm:text-brand-plate sm:hover:text-brand-plate sm:[writing-mode:vertical-rl]"
                >
                    {literature.title}{literature.year != null && ` (${literature.year})`}
                </Link>
            )}
        </section>
    );
}
