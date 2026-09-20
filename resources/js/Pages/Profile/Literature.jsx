import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { StarRating } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

function GenreFilter({ genres, activeGenre }) {
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);

    useEffect(() => {
        const handlePointerDown = (event) => {
            if (!containerRef.current?.contains(event.target)) setIsOpen(false);
        };
        const handleKeyDown = (event) => {
            if (event.key !== 'Escape' || !isOpen) return;

            setIsOpen(false);
            triggerRef.current?.focus();
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen]);

    const navigateToGenre = (slug) => {
        const parameters = new URLSearchParams(window.location.search);

        if (slug) parameters.set('genre', slug);
        else parameters.delete('genre');

        parameters.delete('page');
        setIsOpen(false);
        router.get(
            `${window.location.pathname}${parameters.size ? `?${parameters.toString()}` : ''}`,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const focusMenuItem = (position) => {
        const items = [...(menuRef.current?.querySelectorAll('[role="menuitem"]') ?? [])];
        if (items.length === 0) return;

        items.at(position)?.focus();
    };

    const handleTriggerKeyDown = (event) => {
        if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;

        event.preventDefault();
        setIsOpen(true);
        requestAnimationFrame(() => focusMenuItem(event.key === 'ArrowDown' ? 0 : -1));
    };

    const handleMenuKeyDown = (event) => {
        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;

        event.preventDefault();
        const items = [...(menuRef.current?.querySelectorAll('[role="menuitem"]') ?? [])];
        const currentIndex = items.indexOf(document.activeElement);
        const nextIndex = event.key === 'Home'
            ? 0
            : event.key === 'End'
                ? items.length - 1
                : event.key === 'ArrowDown'
                    ? (currentIndex + 1) % items.length
                    : (currentIndex - 1 + items.length) % items.length;

        items[nextIndex]?.focus();
    };

    return (
        <span ref={containerRef} className="relative inline-block" data-genre-filter>
            <button
                ref={triggerRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={isOpen}
                aria-controls="profile-literature-genre-menu"
                onClick={() => setIsOpen((open) => !open)}
                onKeyDown={handleTriggerKeyDown}
                className="inline-flex min-h-7 items-center gap-1 border border-ink-950/15 bg-white/40 px-2.5 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-ink-950 transition hover:border-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral"
            >
                {activeGenre?.name ?? 'Genre'} <span aria-hidden="true">▾</span>
            </button>

            {isOpen && (
                <span
                    ref={menuRef}
                    id="profile-literature-genre-menu"
                    role="menu"
                    aria-label="Filter completed literature by genre"
                    onKeyDown={handleMenuKeyDown}
                    className="absolute right-0 top-full z-30 mt-1 block max-h-72 w-44 overflow-y-auto border border-ink-950/15 bg-brand-cream py-1 shadow-[0_8px_20px_rgba(47,58,85,0.14)]"
                >
                    <button
                        type="button"
                        role="menuitem"
                        onClick={() => navigateToGenre('')}
                        className={`block w-full border-b border-ink-950/10 px-3 py-2 text-left text-xs font-semibold transition hover:bg-brand-sky/30 focus:bg-brand-sky/30 focus:outline-none ${activeGenre ? 'text-ink-950/65' : 'text-brand-coral'}`}
                    >
                        Any genre
                    </button>
                    {genres.map((genre) => (
                        <button
                            key={genre.slug}
                            type="button"
                            role="menuitem"
                            onClick={() => navigateToGenre(genre.slug)}
                            className={`block w-full px-3 py-1.5 text-left text-xs transition hover:bg-brand-sky/30 focus:bg-brand-sky/30 focus:outline-none ${activeGenre?.slug === genre.slug ? 'font-bold text-brand-coral' : 'text-ink-950/70'}`}
                        >
                            {genre.name}
                        </button>
                    ))}
                </span>
            )}
        </span>
    );
}

export default function ProfileLiterature({ profile, navigation, completedLiterature, activeGenre, genres }) {
    return (
        <>
            <Head title={`${profile.name} Literature`} />
            <ProfileSubNavigation navigation={navigation} />

            <ProfileContentContainer className="py-7 lg:py-9" aria-labelledby="completed-literature-heading">
                <ProfilePageHeading count={<GenreFilter genres={genres} activeGenre={activeGenre} />} />

                {completedLiterature.data.length === 0 ? (
                    <div className="mt-5 border-y border-dashed border-ink-950/20 px-5 py-10 text-center">
                        <p className="text-lg font-bold text-ink-950">
                            {activeGenre ? `No completed ${activeGenre.name} literature yet.` : 'No completed literature yet.'}
                        </p>
                        <p className="mt-1.5 text-sm text-ink-950/55">Titles marked Completed will appear on this shelf.</p>
                    </div>
                ) : (
                    <>
                        <div className="mt-4 grid grid-cols-3 gap-x-2.5 gap-y-4 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-10" data-profile-literature-grid data-density="compact">
                            {completedLiterature.data.map((item) => (
                                <article key={item.id} className="group min-w-0" data-profile-literature-item>
                                    <Link href={item.literature.url} className="block">
                                        <LiteratureCover literature={item.literature} className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-coral" />
                                        <h2 className="mt-1 truncate text-[0.68rem] font-bold leading-4 text-ink-950 transition group-hover:text-brand-coral" title={item.literature.title}>{item.literature.title}</h2>
                                    </Link>
                                    <div className="mt-0.5 flex min-h-4 items-center justify-between gap-1 text-[0.625rem] leading-4 text-ink-950/45">
                                        {item.rating ? <StarRating rating={item.rating} size="text-[0.68rem]" /> : <span className="truncate">Completed</span>}
                                        {item.rating && item.literature.year && <span>{item.literature.year}</span>}
                                    </div>
                                </article>
                            ))}
                        </div>
                        <Pagination paginator={completedLiterature} label="Completed literature pagination" />
                    </>
                )}
            </ProfileContentContainer>
        </>
    );
}
