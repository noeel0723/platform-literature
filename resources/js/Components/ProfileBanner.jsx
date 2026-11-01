export default function ProfileBanner({ profile, editUrl }) {
    if (!profile.banner_url) return null;

    return (
        <div data-profile-banner className="relative h-56 overflow-hidden bg-brand-sky/15 sm:h-80 lg:h-96">
            <img src={profile.banner_url} alt={`${profile.name}'s profile banner`} className="size-full object-cover" style={{ objectPosition: `${profile.banner_position_x ?? 50}% ${profile.banner_position_y ?? 50}%` }} fetchPriority="high" decoding="async" />
            <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-linear-to-b from-transparent via-brand-cream/40 to-brand-cream" />
            {profile.is_owner && editUrl && <a href={editUrl} className="absolute right-5 top-4 rounded-md border border-white/30 bg-ink-950/75 px-3 py-2 text-xs font-semibold text-brand-cream backdrop-blur-sm transition hover:bg-ink-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral sm:right-8">Edit banner</a>}
        </div>
    );
}
