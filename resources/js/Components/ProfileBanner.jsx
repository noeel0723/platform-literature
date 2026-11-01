export default function ProfileBanner({ profile }) {
    if (!profile.banner_url) return null;

    return (
        <div data-profile-banner className="relative h-56 overflow-hidden bg-brand-sky/15 sm:h-80 lg:h-96">
            <img src={profile.banner_url} alt={`${profile.name}'s profile banner`} className="size-full object-cover" style={{ objectPosition: `${profile.banner_position_x ?? 50}% ${profile.banner_position_y ?? 50}%` }} fetchPriority="high" decoding="async" />
            <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-28 bg-linear-to-b from-black/60 to-transparent" />
            <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-linear-to-b from-transparent via-brand-cream/40 to-brand-cream" />
        </div>
    );
}
