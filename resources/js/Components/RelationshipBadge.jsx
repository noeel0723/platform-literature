export default function RelationshipBadge({ following = false, followsYou = false, showFollowing = false, hidden = false }) {
    if (hidden) return null;

    const label = following && followsYou ? 'Friends' : followsYou ? 'Follows you' : showFollowing && following ? 'Following' : null;
    if (!label) return null;

    return <span className="inline-flex shrink-0 items-center rounded-full bg-brand-sky/25 px-2 py-0.5 text-[10px] font-semibold leading-4 text-ink-950">{label}</span>;
}
