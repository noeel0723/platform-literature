import { useEffect, useRef, useState } from 'react';
import SiteContainer from './SiteContainer';

function BrandMark({ href }) {
    return (
        <a href={href} className="group flex items-center gap-3" aria-label="Literahaven - Home">
            <span className="grid grid-cols-2 gap-0.5" aria-hidden="true">
                <span className="size-4 rounded-full bg-brand-plate" />
                <span className="size-4 rounded-full bg-brand-sky" />
                <span className="size-4 rounded-full bg-brand-coral" />
                <span className="size-4 rounded-full bg-brand-sun" />
            </span>
            <span>
                <span className="block text-xl font-bold leading-none tracking-tight text-brand-plate">Literahaven</span>
                <span className="mt-1 block text-[0.6rem] font-semibold uppercase leading-none tracking-[0.24em] text-brand-plate/70">Social Discovery</span>
            </span>
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

    useEffect(() => {
        const close = (event) => {
            if (!menuRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('pointerdown', close);
        return () => document.removeEventListener('pointerdown', close);
    }, []);

    return (
        <div ref={menuRef} className="account-menu relative" data-account-menu data-open={open} onKeyDown={(event) => {
            if (event.key === 'Escape') setOpen(false);
        }}>
            <button type="button" className="flex h-10 items-center gap-2 rounded-full px-2 text-left text-brand-plate transition hover:bg-brand-plate/12 focus-visible:bg-brand-plate/12" data-account-menu-button aria-expanded={open} aria-controls="account-menu-panel" onClick={() => setOpen((value) => !value)}>
                <span className="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full border border-brand-plate/45 bg-brand-blueberry text-[0.65rem] font-bold text-brand-plate">
                    {user.avatar_url ? <img src={user.avatar_url} alt="" className="size-full object-cover" /> : user.initials}
                </span>
                <span className="max-w-32 truncate text-xs font-bold uppercase tracking-[0.12em]">{user.username}</span>
                <svg aria-hidden="true" className={`size-3.5 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            {open && (
                <div id="account-menu-panel" className="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-brand-blueberry/12 bg-brand-plate p-2 text-sm text-ink-950 shadow-2xl shadow-brand-blueberry/20" data-account-menu-panel>
                    <div className="border-b border-brand-blueberry/10 px-3 py-2.5"><p className="truncate font-bold text-ink-950">{user.name}</p><p className="mt-0.5 truncate text-xs text-ink-950/55">@{user.username}</p></div>
                    <nav className="py-1" aria-label="Account navigation">
                        {user.navigation.map((item) => <a key={item.label} href={item.url} className="account-menu-link">{item.label}</a>)}
                    </nav>
                    <div className="border-t border-brand-blueberry/10 pt-1">
                        <a href={user.edit_url} className="account-menu-link">Edit profile</a>
                        {user.moderation_url && <a href={user.moderation_url} className="account-menu-link">Moderation</a>}
                        <form action={user.logout_url} method="POST"><input type="hidden" name="_token" value={csrfToken} /><button className="account-menu-link w-full text-left">Log out</button></form>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AppHeader({ routes, user, csrf_token: csrfToken, search_query: searchQuery }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const openQuickLog = () => document.getElementById('quick-log-search-dialog')?.showModal();

    return (
        <header data-app-header className="sticky top-0 z-40 border-b border-brand-blueberry/15 bg-brand-stem/95 text-brand-plate backdrop-blur-xl">
            <SiteContainer>
                <div className="relative flex h-18 min-w-0 items-center gap-3 lg:gap-5">
                    <div className="shrink-0"><BrandMark href={routes.home} /></div>
                    <div className="ml-auto flex min-w-0 items-center gap-2 md:gap-3 lg:gap-4">
                        {user && <div className="hidden md:block"><AccountMenu user={user} csrfToken={csrfToken} /></div>}
                        <nav className="hidden items-center gap-5 text-xs font-semibold uppercase tracking-[0.14em] text-brand-plate/85 lg:flex" aria-label="Main navigation"><a href={routes.home} className="whitespace-nowrap transition hover:text-white">Home</a><a href={routes.catalog} className="whitespace-nowrap transition hover:text-white">Catalog</a></nav>
                        <Search action={routes.search} initialQuery={searchQuery} />
                        {user && <button type="button" data-quick-log-open className="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-sm bg-brand-coral px-3.5 text-xs font-extrabold uppercase tracking-[0.1em] text-white shadow-sm transition hover:bg-brand-plate hover:text-brand-stem sm:px-4" aria-label="Log a rating or review" onClick={openQuickLog}><span className="text-lg leading-none" aria-hidden="true">+</span><span>Log</span></button>}
                        {!user && <><a href={routes.login} className="shrink-0 whitespace-nowrap text-xs font-bold uppercase tracking-wider text-brand-plate/85 hover:text-white">Log in</a><a href={routes.register} className="hidden shrink-0 whitespace-nowrap rounded-full bg-brand-plate px-4 py-2 text-xs font-bold uppercase tracking-wider text-brand-stem transition hover:bg-white sm:block">Join</a></>}
                        <button type="button" className="grid size-10 shrink-0 place-items-center rounded-full border border-brand-plate/35 bg-transparent text-brand-plate transition hover:bg-brand-plate hover:text-brand-stem lg:hidden" aria-expanded={mobileOpen} aria-controls="mobile-menu" onClick={() => setMobileOpen((value) => !value)}><span className="sr-only">Open navigation</span><svg aria-hidden="true" className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                    </div>
                </div>
            </SiteContainer>
            {mobileOpen && <nav id="mobile-menu" className="border-t border-brand-plate/15 bg-brand-stem py-4 lg:hidden" aria-label="Mobile navigation"><SiteContainer className="grid gap-1 text-sm font-semibold uppercase tracking-[0.14em] text-brand-plate"><a href={routes.home} className="px-3 py-3 transition hover:bg-brand-plate/10">Home</a><a href={routes.catalog} className="px-3 py-3 transition hover:bg-brand-plate/10">Catalog</a>{user ? <>{user.navigation.map((item) => <a key={item.label} href={item.url} className="px-3 py-3 transition hover:bg-brand-plate/10">{item.label}</a>)}<a href={user.edit_url} className="px-3 py-3 transition hover:bg-brand-plate/10">Edit profile</a>{user.moderation_url && <a href={user.moderation_url} className="px-3 py-3 transition hover:bg-brand-plate/10">Moderation</a>}<form action={user.logout_url} method="POST"><input type="hidden" name="_token" value={csrfToken} /><button className="w-full px-3 py-3 text-left transition hover:bg-brand-plate/10">Log out</button></form></> : <><a href={routes.login} className="px-3 py-3 transition hover:bg-brand-plate/10">Log in</a><a href={routes.register} className="px-3 py-3 transition hover:bg-brand-plate/10">Join</a></>}</SiteContainer></nav>}
        </header>
    );
}
