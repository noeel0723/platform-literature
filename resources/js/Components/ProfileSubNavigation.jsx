export default function ProfileSubNavigation({ navigation }) {
    return (
        <section className="catalog-grid border-b border-ink-950/10" data-profile-subnav-shell>
            <div className="mx-auto max-w-7xl px-5 py-5 sm:px-8 lg:px-10" data-profile-subnav-container>
                <nav className="flex min-h-16 flex-col border border-ink-950/10 bg-brand-cream/80 sm:h-16 sm:flex-row sm:items-center" aria-label="Profile navigation" data-profile-subnav>
                    <a href={navigation.user.url} className="flex h-16 shrink-0 items-center gap-3 border-b border-ink-950/10 px-4 text-ink-950 sm:w-56 sm:border-b-0 sm:border-r">
                        <span className="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-sm font-bold text-brand-cream">
                            {navigation.user.avatar_url ? <img src={navigation.user.avatar_url} alt="" className="size-full object-cover" /> : navigation.user.initials}
                        </span>
                        <span className="min-w-0"><span className="block truncate text-sm font-bold">{navigation.user.name}</span><span className="block truncate text-xs font-medium text-ink-950/45">@{navigation.user.username}</span></span>
                    </a>
                    <div className="flex min-w-0 flex-1 gap-5 overflow-x-auto px-4 sm:justify-center sm:gap-6 sm:px-5" data-profile-subnav-links>
                        {navigation.links.map((item) => {
                            const active = navigation.current === item.key;
                            return <a key={item.key} href={item.url} className={`relative flex h-16 shrink-0 items-center px-0.5 text-sm font-bold transition after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 ${active ? 'text-ink-950 after:bg-brand-coral' : 'text-ink-950/55 hover:text-ink-950 after:bg-transparent'}`} aria-current={active ? 'page' : undefined}>{item.label}</a>;
                        })}
                    </div>
                </nav>
            </div>
        </section>
    );
}
