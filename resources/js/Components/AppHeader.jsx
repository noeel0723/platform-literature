import { useEffect, useRef, useState } from 'react';

import { OPEN_LOGIN_PANEL_EVENT } from '../Support/loginPanel';
import LoginPanel from './LoginPanel';
import QuickLogDialog from './QuickLogDialog';
import SiteContainer from './SiteContainer';

function BrandMark({ href }) {
    return (
        <a href={href} className="group flex items-center gap-2.5" aria-label="Literahaven - Home">
            <span className="grid grid-cols-2 gap-0.5" aria-hidden="true">
                <span className="size-4 rounded-full bg-brand-plate" />
                <span className="size-4 rounded-full bg-brand-sky" />
                <span className="size-4 rounded-full bg-brand-coral" />
                <span className="size-4 rounded-full bg-brand-sun" />
            </span>
            <span className="block text-2xl font-black leading-none tracking-[-0.04em] text-brand-plate sm:text-3xl">Literahaven</span>
        </a>
    );
}

function Search({ action, initialQuery }) {
    const [expanded, setExpanded] = useState(false);
    const inputRef = useRef(null);
    const formRef = useRef(null);

    useEffect(() => {
        if (expanded) inputRef.current?.focus();
    }, [expanded]);

    useEffect(() => {
        const close = (event) => {
            if (expanded && !formRef.current?.contains(event.target)) setExpanded(false);
        };
        document.addEventListener('pointerdown', close);
        return () => document.removeEventListener('pointerdown', close);
    }, [expanded]);

    return (
        <form ref={formRef} action={action} method="GET" role="search" className="header-search" data-header-search data-expanded={expanded} onSubmit={(event) => {
            if (!expanded) {
                event.preventDefault();
                setExpanded(true);
            } else if (!inputRef.current?.value.trim()) {
                event.preventDefault();
                inputRef.current?.focus();
            }
        }} onKeyDown={(event) => {
            if (event.key === 'Escape') {
                setExpanded(false);
                event.currentTarget.querySelector('button')?.focus();
            }
        }}>
            <label htmlFor="global-search" className="sr-only">Search literature, authors, or readers</label>
            <input ref={inputRef} id="global-search" name="q" defaultValue={initialQuery} placeholder="Search literature, authors, or readers..." className="header-search-input" tabIndex={expanded ? 0 : -1} aria-hidden={!expanded} />
            <button type="submit" className="header-search-button" data-header-search-button aria-label={expanded ? 'Search' : 'Open search'} aria-expanded={expanded}>
                <svg aria-hidden="true" className="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
            </button>
        </form>
    );
}

function AccountMenu({ user, csrfToken }) {
    const [open, setOpen] = useState(false);
    const menuRef = useRef(null);
    const buttonRef = useRef(null);
    const closeTimerRef = useRef(null);

    const cancelScheduledClose = () => {
        if (closeTimerRef.current !== null) {
            window.clearTimeout(closeTimerRef.current);
            closeTimerRef.current = null;
        }
    };

    const openMenu = () => {
        cancelScheduledClose();
        setOpen(true);
    };

    const scheduleClose = () => {
        cancelScheduledClose();
        closeTimerRef.current = window.setTimeout(() => {
            if (!menuRef.current?.contains(document.activeElement)) setOpen(false);
        }, 200);
    };

    useEffect(() => {
        const close = (event) => {
            if (!menuRef.current?.contains(event.target)) {
                cancelScheduledClose();
                setOpen(false);
            }
        };
        document.addEventListener('pointerdown', close);
        return () => {
            document.removeEventListener('pointerdown', close);
            cancelScheduledClose();
        };
    }, []);

    return (
        <div
            ref={menuRef}
            className="account-menu relative"
            data-account-menu
            data-open={open}
            onPointerEnter={(event) => { if (event.pointerType === 'mouse') openMenu(); }}
            onPointerLeave={(event) => { if (event.pointerType === 'mouse') scheduleClose(); }}
            onFocusCapture={cancelScheduledClose}
            onBlurCapture={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) scheduleClose(); }}
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    cancelScheduledClose();
                    setOpen(false);
                    buttonRef.current?.focus();
                }
            }}
        >
            <button ref={buttonRef} type="button" className="flex h-10 items-center gap-2 rounded-sm px-2 text-left text-brand-plate transition hover:bg-brand-plate/12 focus-visible:bg-brand-plate/12" data-account-menu-button aria-haspopup="true" aria-expanded={open} aria-controls="account-menu-panel" onClick={() => { cancelScheduledClose(); setOpen((value) => !value); }}>
                <span className="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full border border-brand-plate/45 bg-brand-blueberry text-[0.65rem] font-bold text-brand-plate">
                    {user.avatar_url ? <img src={user.avatar_url} alt="" className="size-full object-cover" /> : user.initials}
                </span>
                <span className="max-w-32 truncate text-xs font-bold uppercase tracking-[0.12em]">{user.username}</span>
                <svg aria-hidden="true" className={`size-3.5 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            {open && (
                <div id="account-menu-panel" className="absolute left-0 top-full z-50 mt-1 w-52 overflow-hidden rounded-sm border border-brand-blueberry/15 bg-brand-plate py-1 text-[0.8rem] text-ink-950 shadow-lg shadow-brand-blueberry/15" data-account-menu-panel>
                    <nav aria-label="Account navigation">
                        {user.navigation.map((item) => <a key={item.label} href={item.url} className="account-menu-link rounded-none px-3 py-1.5">{item.label}</a>)}
                    </nav>
                    <div className="mt-1 border-t border-brand-blueberry/12 pt-1">
                        <a href={user.edit_url} className="account-menu-link rounded-none px-3 py-1.5">Edit Profile</a>
                        {user.moderation_url && <a href={user.moderation_url} className="account-menu-link rounded-none px-3 py-1.5">Moderation</a>}
                        <form action={user.logout_url} method="POST"><input type="hidden" name="_token" value={csrfToken} /><button className="account-menu-link w-full rounded-none px-3 py-1.5 text-left">Log Out</button></form>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AppHeader({ routes, user, csrf_token: csrfToken, search_query: searchQuery, is_guest_landing: isGuestLanding = false }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const [loginOpen, setLoginOpen] = useState(false);
    const [quickLogOpen, setQuickLogOpen] = useState(false);
    const [currentPath, setCurrentPath] = useState(window.location.pathname);
    const openQuickLog = () => setQuickLogOpen(true);
    const matchesRoute = (url) => {
        const path = new URL(url, window.location.origin).pathname.replace(/\/$/, '') || '/';
        return currentPath === path || (path !== '/' && currentPath.startsWith(`${path}/`));
    };
    const activeNavigation = matchesRoute(routes.home) ? 'home'
        : matchesRoute(routes.literature) ? 'literature'
            : matchesRoute(routes.catalog) ? 'catalog' : null;
    const mainLinkClass = (key) => `whitespace-nowrap border-b-2 py-1 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral ${activeNavigation === key ? 'border-brand-coral text-white' : 'border-transparent hover:border-brand-plate/50 hover:text-white'}`;
    const mobileLinkClass = (key) => `border-l-2 px-3 py-3 transition ${activeNavigation === key ? 'border-brand-coral bg-brand-plate/15 text-white' : 'border-transparent hover:bg-brand-plate/10'}`;
    const toggleLogin = () => {
        setMobileOpen(false);
        setLoginOpen((current) => !current);
    };

    useEffect(() => {
        const syncPath = () => setCurrentPath(window.location.pathname);
        document.addEventListener('inertia:navigate', syncPath);
        window.addEventListener('popstate', syncPath);

        return () => {
            document.removeEventListener('inertia:navigate', syncPath);
            window.removeEventListener('popstate', syncPath);
        };
    }, []);

    useEffect(() => {
        const openLoginPanel = () => {
            setMobileOpen(false);
            setLoginOpen(true);
        };

        window.addEventListener(OPEN_LOGIN_PANEL_EVENT, openLoginPanel);

        return () => window.removeEventListener(OPEN_LOGIN_PANEL_EVENT, openLoginPanel);
    }, []);

    const headerClassName = isGuestLanding
        ? 'absolute inset-x-0 top-0 z-40 bg-transparent text-brand-plate'
        : 'sticky top-0 z-40 border-b border-brand-blueberry/15 bg-brand-stem/95 text-brand-plate backdrop-blur-xl';

    return (
        <header data-app-header className={headerClassName}>
            <SiteContainer>
                <div className={`relative flex min-w-0 items-center gap-3 lg:gap-5 ${!user && loginOpen ? 'flex-wrap lg:min-h-20 lg:flex-nowrap' : 'h-18'}`}>
                    <div className={`shrink-0 ${!user && loginOpen ? 'flex h-18 items-center lg:h-auto' : ''}`}><BrandMark href={routes.home} /></div>
                    {!user && loginOpen ? (
                        <div className="order-last w-full pb-2 lg:order-none lg:ml-auto lg:min-w-0 lg:flex-1 lg:pb-0">
                            <LoginPanel routes={routes} csrfToken={csrfToken} onClose={() => setLoginOpen(false)} inline />
                        </div>
                    ) : (
                        <div className="ml-auto flex min-w-0 items-center gap-2 md:gap-3 lg:gap-4">
                            {user && <div className="hidden md:block"><AccountMenu user={user} csrfToken={csrfToken} /></div>}
                            <nav className="hidden items-center gap-5 text-xs font-semibold uppercase tracking-[0.14em] text-brand-plate/85 lg:flex" aria-label="Main navigation">
                                {!user && (
                                    <a href={routes.login} onClick={(event) => { event.preventDefault(); toggleLogin(); }} aria-expanded={loginOpen} className="whitespace-nowrap transition hover:text-white">
                                        Log in
                                    </a>
                                )}
                                <a href={routes.home} className={mainLinkClass('home')} aria-current={activeNavigation === 'home' ? 'page' : undefined}>Home</a>
                                <a href={routes.literature} className={mainLinkClass('literature')} aria-current={activeNavigation === 'literature' ? 'page' : undefined}>Literature</a>
                                <a href={routes.catalog} className={mainLinkClass('catalog')} aria-current={activeNavigation === 'catalog' ? 'page' : undefined}>Catalog</a>
                            </nav>
                            <Search action={routes.search} initialQuery={searchQuery} />
                            {user && <button type="button" className="inline-flex h-8 shrink-0 items-center gap-1 rounded-sm bg-[#00c030] px-3 text-[0.7rem] font-extrabold uppercase tracking-[0.08em] text-white shadow-sm transition-colors hover:bg-[#00a628] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#00c030]" aria-label="Log a rating or review" onClick={openQuickLog}><span className="text-base leading-none" aria-hidden="true">+</span><span>Log</span></button>}
                            {!user && <a href={routes.login} onClick={(event) => { event.preventDefault(); toggleLogin(); }} aria-expanded={loginOpen} className="shrink-0 whitespace-nowrap text-xs font-bold uppercase tracking-wider text-brand-plate/85 hover:text-white lg:hidden">Log in</a>}
                            <button type="button" className="grid size-10 shrink-0 place-items-center rounded-full border border-brand-plate/35 bg-transparent text-brand-plate transition hover:bg-brand-plate hover:text-brand-stem lg:hidden" aria-expanded={mobileOpen} aria-controls="mobile-menu" onClick={() => setMobileOpen((value) => !value)}><span className="sr-only">Open navigation</span><svg aria-hidden="true" className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                        </div>
                    )}
                </div>
            </SiteContainer>
            {mobileOpen && (
                <nav id="mobile-menu" className="border-t border-brand-plate/15 bg-brand-stem py-4 lg:hidden" aria-label="Mobile navigation">
                    <SiteContainer className="grid gap-1 text-sm font-semibold uppercase tracking-[0.14em] text-brand-plate">
                        <a href={routes.home} className={mobileLinkClass('home')} aria-current={activeNavigation === 'home' ? 'page' : undefined}>Home</a>
                        <a href={routes.literature} className={mobileLinkClass('literature')} aria-current={activeNavigation === 'literature' ? 'page' : undefined}>Literature</a>
                        <a href={routes.catalog} className={mobileLinkClass('catalog')} aria-current={activeNavigation === 'catalog' ? 'page' : undefined}>Catalog</a>
                        {user ? (
                            <>
                                {user.navigation.filter((item) => item.label !== 'Home').map((item) => <a key={item.label} href={item.url} className="px-3 py-3 transition hover:bg-brand-plate/10">{item.label}</a>)}
                                <a href={user.edit_url} className="px-3 py-3 transition hover:bg-brand-plate/10">Edit profile</a>
                                {user.moderation_url && <a href={user.moderation_url} className="px-3 py-3 transition hover:bg-brand-plate/10">Moderation</a>}
                                <form action={user.logout_url} method="POST"><input type="hidden" name="_token" value={csrfToken} /><button className="w-full px-3 py-3 text-left transition hover:bg-brand-plate/10">Log out</button></form>
                            </>
                        ) : null}
                    </SiteContainer>
                </nav>
            )}
            {user && <QuickLogDialog open={quickLogOpen} onClose={() => setQuickLogOpen(false)} searchUrl={routes.quick_log_search} />}
        </header>
    );
}
