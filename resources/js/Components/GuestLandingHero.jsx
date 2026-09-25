import { openLoginPanel } from '../Support/loginPanel';
import SiteContainer from './SiteContainer';

export default function GuestLandingHero({ literature, onRegisterOpen, registerTriggerRef }) {
    const backdropUrl = literature?.backdrop_url;

    return (
        <section className="relative min-h-[420px] overflow-hidden bg-neutral-950 text-brand-plate sm:min-h-[450px] lg:min-h-[480px]">
            {backdropUrl ? (
                <img
                    src={backdropUrl}
                    alt={`Artwork for ${literature.title}`}
                    className="absolute inset-0 h-full w-full object-cover object-center"
                    loading="eager"
                    fetchPriority="high"
                />
            ) : (
                <div
                    className="absolute inset-0 bg-neutral-900 opacity-90 [background-image:linear-gradient(rgba(248,243,240,0.06)_1px,transparent_1px),linear-gradient(90deg,rgba(248,243,240,0.06)_1px,transparent_1px)] [background-size:36px_36px]"
                    aria-hidden="true"
                />
            )}

            <div className="pointer-events-none absolute inset-0 bg-black/15" aria-hidden="true" />
            <div className="pointer-events-none absolute inset-0 bg-linear-to-r from-black/75 via-black/40 to-black/10" aria-hidden="true" />
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-28 bg-linear-to-b from-transparent via-brand-cream/30 to-brand-cream sm:h-32 lg:h-36" aria-hidden="true" />

            <SiteContainer className="relative z-10 flex min-h-[420px] items-center py-12 sm:min-h-[450px] sm:py-14 lg:min-h-[480px] lg:py-16">
                <div className="max-w-xl lg:max-w-2xl">
                    <h1 className="font-serif text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-brand-plate/90 sm:text-5xl lg:text-6xl">
                        <span className="block sm:whitespace-nowrap">Track every story</span>
                        <span className="block">you read.</span>
                    </h1>
                    <p className="mt-6 max-w-lg text-sm leading-7 text-brand-plate/65 sm:text-base">
                        Keep a diary of the novels, manga, manhwa, and comics you love. Rate them, review them, discover new literature, and see what other readers are exploring.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-x-5 gap-y-3">
                        <button
                            ref={registerTriggerRef}
                            type="button"
                            onClick={onRegisterOpen}
                            className="inline-flex min-h-11 items-center justify-center rounded-sm bg-brand-coral px-5 py-3 text-xs font-bold uppercase tracking-[0.1em] text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-brand-coral/90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-plate"
                        >
                            Join us! Sign up here!
                        </button>
                        <span className="text-sm text-brand-plate/60">
                            Already a member?{' '}
                            <button
                                type="button"
                                onClick={openLoginPanel}
                                className="font-bold text-brand-plate/85 underline decoration-brand-coral decoration-2 underline-offset-4 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral"
                            >
                                Log in
                            </button>
                        </span>
                    </div>
                </div>
            </SiteContainer>
        </section>
    );
}
