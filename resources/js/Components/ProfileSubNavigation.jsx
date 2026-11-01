import { ProfileContentContainer } from './ProfilePageUi';

export default function ProfileSubNavigation({ navigation }) {
    return (
        <section className="catalog-grid border-b border-ink-950/10" data-profile-subnav-shell>
            <ProfileContentContainer as="div" className="py-5" data-profile-subnav-container>
                <nav className="flex h-16 min-w-0 items-center overflow-hidden rounded-xl border border-ink-950/10 bg-brand-cream/80" aria-label="Profile navigation" data-profile-subnav>
                    <div className="flex min-w-0 flex-1 gap-5 overflow-x-auto px-4 sm:justify-center-safe sm:gap-6 sm:px-5" data-profile-subnav-links>
                        {navigation.links.map((item) => {
                            const active = navigation.current === item.key;
                            return <a key={item.key} href={item.url} className={`relative flex h-16 shrink-0 items-center px-0.5 text-sm font-bold transition after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 ${active ? 'text-ink-950 after:bg-brand-coral' : 'text-ink-950/55 hover:text-ink-950 after:bg-transparent'}`} aria-current={active ? 'page' : undefined}>{item.label}</a>;
                        })}
                    </div>
                </nav>
            </ProfileContentContainer>
        </section>
    );
}
