import { Link } from '@inertiajs/react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { openLoginPanel } from '../Support/loginPanel';
import { RatingInput } from './LiteratureDetail/DetailUi';
import QuickLogDialog from './QuickLogDialog';

const OPEN_PREVIEW_EVENT = 'literahaven:open-cover-preview';
const actionClass = 'flex min-h-9 w-full items-center justify-between gap-3 px-4 py-2 text-left text-xs font-semibold transition hover:bg-brand-sky/20 focus-visible:bg-brand-sky/20 focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50';

async function fetchPreview(url, signal) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal,
    });

    if (!response.ok) throw new Error('Preview is unavailable. Please try again.');
    return response.json();
}

function ActionIcon({ name }) {
    return (
        <svg aria-hidden="true" className="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            {name === 'completed' ? <><circle cx="12" cy="12" r="9" /><path d="m7.5 12 3 3 6-6" /></>
                : name === 'readlist' ? <path d="M6 3h12v18l-6-4-6 4V3Z" />
                    : <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>}
        </svg>
    );
}

export default function LiteratureCardPreview({ literature, compact = false, children }) {
    const menuId = useId();
    const coverRef = useRef(null);
    const menuRef = useRef(null);
    const triggerRef = useRef(null);
    const openTimer = useRef(null);
    const closeTimer = useRef(null);
    const focusRequested = useRef(false);
    const [open, setOpen] = useState(false);
    const [preview, setPreview] = useState(null);
    const [position, setPosition] = useState(null);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [listsOpen, setListsOpen] = useState(false);
    const [quickLog, setQuickLog] = useState(null);
    const readingStatus = preview?.viewer.reading_status;

    const clearTimers = () => {
        window.clearTimeout(openTimer.current);
        window.clearTimeout(closeTimer.current);
    };
    const close = (restoreFocus = false) => {
        clearTimers();
        setOpen(false);
        if (restoreFocus) triggerRef.current?.focus();
    };
    const show = () => {
        clearTimers();
        setOpen(true);
    };
    const scheduleOpen = () => {
        clearTimers();
        openTimer.current = window.setTimeout(() => setOpen(true), 180);
    };
    const scheduleClose = () => {
        clearTimers();
        closeTimer.current = window.setTimeout(() => {
            if (!menuRef.current?.contains(document.activeElement)) setOpen(false);
        }, 180);
    };
    const onBlur = (event) => {
        if (!coverRef.current?.contains(event.relatedTarget) && !menuRef.current?.contains(event.relatedTarget)) scheduleClose();
    };

    useEffect(() => {
        const closeOther = (event) => {
            if (event.detail !== menuId) {
                clearTimers();
                setOpen(false);
            }
        };
        window.addEventListener(OPEN_PREVIEW_EVENT, closeOther);
        return () => {
            clearTimers();
            window.removeEventListener(OPEN_PREVIEW_EVENT, closeOther);
        };
    }, [menuId]);

    useEffect(() => {
        if (!open) return;
        window.dispatchEvent(new CustomEvent(OPEN_PREVIEW_EVENT, { detail: menuId }));
        setListsOpen(false);
        const request = new AbortController();
        setLoading(true);
        setError('');
        fetchPreview(literature.preview_url, request.signal)
            .then((data) => { if (!request.signal.aborted) setPreview(data); })
            .catch((failure) => { if (!request.signal.aborted) setError(failure.message); })
            .finally(() => { if (!request.signal.aborted) setLoading(false); });
        return () => request.abort();
    }, [open, literature.preview_url, menuId]);

    useEffect(() => {
        if (!open) return;
        const outside = (event) => {
            if (!coverRef.current?.contains(event.target) && !menuRef.current?.contains(event.target)) close();
        };
        const escape = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
            }
        };
        document.addEventListener('pointerdown', outside);
        document.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('pointerdown', outside);
            document.removeEventListener('keydown', escape);
        };
    }, [open]);

    useLayoutEffect(() => {
        if (!open) return;
        const updatePosition = () => {
            if (!coverRef.current || !menuRef.current) return;
            const cover = coverRef.current.getBoundingClientRect();
            const width = Math.min(256, window.innerWidth - 24);
            const height = menuRef.current.getBoundingClientRect().height;
            let left = cover.right - 12;
            if (left + width > window.innerWidth - 12) left = cover.left - width + 12;
            left = Math.max(12, Math.min(left, window.innerWidth - width - 12));
            const top = Math.max(12, Math.min(cover.bottom - height, window.innerHeight - height - 12));
            setPosition({ left, top, width });
        };
        updatePosition();
        if (focusRequested.current) {
            menuRef.current?.focus();
            focusRequested.current = false;
        }
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);
        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [open, preview, loading, listsOpen, error]);

    const requireLogin = () => {
        if (preview?.viewer.authenticated) return false;
        close();
        if (preview?.routes.verification) window.location.assign(preview.routes.verification);
        else openLoginPanel();
        return true;
    };
    const review = (rating = null) => {
        if (!preview || loading) return show();
        if (requireLogin()) return;
        setQuickLog({ literature: preview.literature, rating });
        close();
    };
    const mutate = async (url, method, values = {}) => {
        if (processing) return;
        setProcessing(true);
        setError('');
        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...values, _token: preview.csrf_token }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'The action could not be saved.');
            setPreview(await fetchPreview(literature.preview_url));
        } catch (failure) {
            setError(failure.message || 'The action could not be saved. Please try again.');
        } finally {
            setProcessing(false);
        }
    };
    const toggleReading = (status) => {
        if (!preview || loading) return show();
        if (requireLogin()) return;
        if (readingStatus === status) mutate(preview.routes.reading_destroy, 'DELETE');
        else mutate(preview.routes.reading_update, 'PUT', { status });
    };
    const toggleList = (list) => {
        if (list.contains) mutate(list.destroy_url, 'DELETE');
        else mutate(list.store_url, 'POST', { literature_id: preview.literature.id });
    };

    if (!literature.preview_url) return children;

    return (
        <>
            <div ref={coverRef} className="literature-card-cover relative" data-preview-open={open} onPointerEnter={(event) => { if (event.pointerType === 'mouse') scheduleOpen(); }} onPointerLeave={scheduleClose} onFocusCapture={clearTimers} onBlurCapture={onBlur}>
                {children}
                <div className="literature-card-controls absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-linear-to-t from-ink-950/95 to-transparent p-2 pt-8 text-brand-cream">
                    {!compact && <>
                    <button type="button" disabled={processing} onClick={() => toggleReading('completed')} aria-label={readingStatus === 'completed' ? `Remove completed status for ${literature.title}` : `Mark ${literature.title} as completed`} title="Completed" aria-pressed={readingStatus === 'completed'} className={`grid size-8 place-items-center rounded-sm transition hover:bg-brand-cream/15 focus-visible:outline-2 focus-visible:outline-brand-coral ${readingStatus === 'completed' ? 'text-brand-coral' : ''}`}><ActionIcon name="completed" /></button>
                    <button type="button" disabled={processing} onClick={() => toggleReading('want_to_read')} aria-label={readingStatus === 'want_to_read' ? `Remove ${literature.title} from Readlist` : `Add ${literature.title} to Readlist`} title="Readlist" aria-pressed={readingStatus === 'want_to_read'} className={`grid size-8 place-items-center rounded-sm transition hover:bg-brand-cream/15 focus-visible:outline-2 focus-visible:outline-brand-coral ${readingStatus === 'want_to_read' ? 'text-brand-coral' : ''}`}><ActionIcon name="readlist" /></button>
                    </>}
                    <button ref={triggerRef} type="button" onClick={(event) => { focusRequested.current = event.detail === 0; if (open) close(); else show(); }} aria-label={`Quick actions for ${literature.title}`} title="Quick actions" aria-haspopup="dialog" aria-expanded={open} aria-controls={open ? menuId : undefined} className="grid size-8 place-items-center rounded-sm transition hover:bg-brand-cream/15 focus-visible:outline-2 focus-visible:outline-brand-coral"><ActionIcon name="more" /></button>
                </div>
            </div>

            {open && createPortal(
                <div ref={menuRef} id={menuId} role="dialog" aria-modal="false" aria-label={`Quick actions for ${literature.title}`} tabIndex={-1} style={{ width: Math.min(256, window.innerWidth - 24), ...position, visibility: position ? 'visible' : 'hidden' }} className="fixed z-50 max-h-[calc(100dvh-1.5rem)] overflow-y-auto rounded-lg border border-ink-950/15 bg-brand-cream text-ink-950 shadow-xl shadow-ink-950/20 outline-none" onPointerEnter={clearTimers} onPointerLeave={scheduleClose} onFocusCapture={clearTimers} onBlurCapture={onBlur}>
                    <div className="flex items-start justify-between gap-2 border-b border-ink-950/10 px-4 py-3">
                        <div className="min-w-0"><p className="line-clamp-2 text-sm font-bold">{literature.title}</p><p className="mt-0.5 truncate text-[0.65rem] text-ink-950/55">{[literature.type_label, literature.year].filter(Boolean).join(' · ')}</p></div>
                        <button type="button" onClick={() => close(true)} aria-label="Close preview" className="-mr-1 grid size-6 shrink-0 place-items-center rounded-sm text-lg leading-none text-ink-950/45 hover:bg-brand-sky/20 hover:text-ink-950">×</button>
                    </div>
                    {loading ? <p role="status" className="px-4 py-5 text-center text-xs text-ink-950/55">Loading your actions…</p> : preview && (
                        <>
                            <fieldset disabled={processing} className="border-b border-ink-950/10 px-4 py-2 text-center">
                                <legend className="sr-only">Your rating</legend>
                                <div className="flex justify-center"><RatingInput compact value={preview.literature.review?.rating ?? ''} onChange={() => {}} onCommit={review} /></div>
                                {preview.literature.review && <p className="pb-1 text-[0.65rem] text-ink-950/55">Your rating: {Number(preview.literature.review.rating).toFixed(1)} / 5</p>}
                            </fieldset>
                            {preview.viewer.authenticated ? (
                                <div className="divide-y divide-ink-950/10">
                                    <button type="button" disabled={processing} onClick={() => toggleReading('completed')} className={actionClass}><span>{readingStatus === 'completed' ? 'Remove completed status' : 'Mark as completed'}</span>{readingStatus === 'completed' && <span className="text-brand-coral">✓</span>}</button>
                                    <button type="button" onClick={() => review()} disabled={processing} className={actionClass}>{preview.literature.review ? 'Edit rating or review…' : 'Rate or review literature…'}</button>
                                    <button type="button" disabled={processing} onClick={() => toggleReading('want_to_read')} className={actionClass}><span>{readingStatus === 'want_to_read' ? 'Remove from Readlist' : 'Add to Readlist'}</span>{readingStatus === 'want_to_read' && <span className="text-brand-coral">✓</span>}</button>
                                    <div>
                                        <button type="button" onClick={() => setListsOpen((current) => !current)} className={actionClass} aria-expanded={listsOpen} aria-controls={listsOpen ? `${menuId}-lists` : undefined}><span>Add to lists…</span><span aria-hidden="true">{listsOpen ? '−' : '+'}</span></button>
                                        {listsOpen && <div id={`${menuId}-lists`} className="border-t border-ink-950/10 bg-white/40 py-1">
                                            <div className="max-h-40 overflow-y-auto">
                                                {preview.lists.length ? preview.lists.map((list) => <button key={list.id} type="button" disabled={processing} aria-pressed={list.contains} onClick={() => toggleList(list)} className={actionClass}><span className="min-w-0 truncate">{list.title}{list.is_private ? ' · Private' : ''}</span><span className="text-brand-coral" aria-hidden="true">{list.contains ? '✓' : '+'}</span></button>) : <p className="px-4 py-2 text-xs text-ink-950/55">No custom lists yet.</p>}
                                            </div>
                                            <Link href={preview.routes.create_list} className={`${actionClass} text-brand-coral`} onClick={() => close()}>Create a list →</Link>
                                        </div>}
                                    </div>
                                </div>
                            ) : <button type="button" onClick={requireLogin} className={`${actionClass} text-brand-coral`}>{preview.routes.verification ? 'Verify email to rate and track' : 'Log in to rate and track'}</button>}
                            {preview.routes.where_to_read && <Link href={preview.routes.where_to_read} className={`${actionClass} border-t border-ink-950/10`} onClick={() => close()}>Where to read</Link>}
                        </>
                    )}
                    {error && <p role="alert" className="px-4 py-3 text-xs leading-5 text-red-700">{error}</p>}
                    <Link href={preview?.routes.details ?? literature.url} className={`${actionClass} border-t border-ink-950/10 text-ink-950/60`} onClick={() => close()}>View literature details <span aria-hidden="true">→</span></Link>
                </div>, document.body,
            )}

            {quickLog && <QuickLogDialog open initialLiterature={quickLog.literature} initialRating={quickLog.rating} searchUrl={preview.routes.quick_log_search} onClose={() => { setQuickLog(null); triggerRef.current?.focus(); }} />}
        </>
    );
}
