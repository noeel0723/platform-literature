import { Link } from '@inertiajs/react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { openLoginPanel } from '../Support/loginPanel';
import { actionFeedback, requestAction, ACTION_RESTORED_EVENT } from '../Support/actionFeedback';
import { focusablePreviewActions, previewActionIndex, previewPosition, previewTabBoundary } from '../Support/literatureCardPreview';
import { RatingInput } from './LiteratureDetail/DetailUi';
import QuickLogDialog from './QuickLogDialog';
import LiteraturePreviewSources from './LiteraturePreviewSources';

const OPEN_PREVIEW_EVENT = 'literahaven:open-cover-preview';
const actionClass = 'flex min-h-11 w-full items-center justify-between gap-3 px-4 py-2 text-left text-xs font-semibold transition hover:bg-brand-sky/20 focus-visible:bg-brand-sky/20 focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50';
const controlClass = 'grid size-11 shrink-0 place-items-center rounded-sm transition hover:bg-brand-cream/15 focus-visible:outline-2 focus-visible:outline-brand-coral';

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
    const focusRequested = useRef(false);
    const returnFocusRef = useRef(null);
    const processingRef = useRef(false);
    const restoreQuickLogFocus = useRef(false);
    const [open, setOpen] = useState(false);
    const [preview, setPreview] = useState(null);
    const [position, setPosition] = useState(null);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [listsOpen, setListsOpen] = useState(false);
    const [quickLog, setQuickLog] = useState(null);
    const readingStatus = preview?.viewer.reading_status;

    const close = (restoreFocus = false) => {
        focusRequested.current = false;
        setOpen(false);
        setPosition(null);
        if (restoreFocus) (returnFocusRef.current ?? triggerRef.current)?.focus({ preventScroll: true });
    };
    const show = (focus = false, source = triggerRef.current) => {
        returnFocusRef.current = source;
        focusRequested.current = focus;
        setOpen(true);
        if (focus && open && position) {
            menuRef.current?.focus({ preventScroll: true });
            focusRequested.current = false;
        }
    };
    const onBlur = (event) => {
        if (event.relatedTarget && !coverRef.current?.contains(event.relatedTarget) && !menuRef.current?.contains(event.relatedTarget)) close();
    };

    const onPanelKeyDown = (event) => {
        const actions = focusablePreviewActions(menuRef.current);
        const current = actions.indexOf(document.activeElement);
        if (event.key === 'Tab') {
            const boundary = previewTabBoundary(current, actions.length, event.shiftKey);
            if (!boundary) return;
            event.preventDefault();
            if (boundary === 'first') actions[0]?.focus();
            else if (boundary === 'trigger') close(true);
            else {
                const pageActions = focusablePreviewActions(document).filter((element) => !menuRef.current.contains(element));
                const next = pageActions[pageActions.indexOf(triggerRef.current) + 1];
                close();
                (next ?? triggerRef.current)?.focus({ preventScroll: true });
            }
            return;
        }
        if (event.target.closest('[role="radiogroup"], [role="toolbar"]')) return;
        const next = previewActionIndex(event.key, current, actions.length);
        if (next !== null) {
            event.preventDefault();
            actions[next].focus();
        }
    };

    useEffect(() => {
        const closeOther = (event) => {
            if (event.detail !== menuId) {
                close(menuRef.current?.contains(document.activeElement));
            }
        };
        window.addEventListener(OPEN_PREVIEW_EVENT, closeOther);
        return () => {
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
        const request = new AbortController();
        const refresh = () => {
            if (!open) { setPreview(null); return; }
            fetchPreview(literature.preview_url, request.signal)
                .then((data) => { if (!request.signal.aborted) setPreview(data); })
                .catch(() => { if (!request.signal.aborted) setError('Action undone. Reopen this preview to refresh its details.'); });
        };
        window.addEventListener(ACTION_RESTORED_EVENT, refresh);
        return () => { request.abort(); window.removeEventListener(ACTION_RESTORED_EVENT, refresh); };
    }, [open, literature.preview_url]);

    useLayoutEffect(() => {
        if (!open) return;
        const outside = (event) => {
            if (!coverRef.current?.contains(event.target) && !menuRef.current?.contains(event.target)) close();
        };
        const escape = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close(menuRef.current?.contains(document.activeElement) || coverRef.current?.contains(document.activeElement));
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
        let frame;
        const updatePosition = () => {
            if (!coverRef.current || !menuRef.current) return;
            const cover = coverRef.current.getBoundingClientRect();
            const viewport = window.visualViewport;
            const next = previewPosition(cover, {
                left: viewport?.offsetLeft ?? 0,
                top: viewport?.offsetTop ?? 0,
                width: viewport?.width ?? window.innerWidth,
                height: viewport?.height ?? window.innerHeight,
            }, menuRef.current.getBoundingClientRect().height);
            setPosition((current) => current && Object.keys(next).every((key) => current[key] === next[key]) ? current : next);
        };
        const schedulePosition = () => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(updatePosition);
        };
        updatePosition();
        const observer = new ResizeObserver(schedulePosition);
        observer.observe(menuRef.current);
        observer.observe(coverRef.current);
        window.addEventListener('resize', schedulePosition);
        window.addEventListener('scroll', schedulePosition, true);
        window.visualViewport?.addEventListener('resize', schedulePosition);
        window.visualViewport?.addEventListener('scroll', schedulePosition);
        return () => {
            window.cancelAnimationFrame(frame);
            observer.disconnect();
            window.removeEventListener('resize', schedulePosition);
            window.removeEventListener('scroll', schedulePosition, true);
            window.visualViewport?.removeEventListener('resize', schedulePosition);
            window.visualViewport?.removeEventListener('scroll', schedulePosition);
        };
    }, [open, preview, loading, listsOpen, error]);

    useLayoutEffect(() => {
        if (open && position && focusRequested.current) {
            menuRef.current?.focus({ preventScroll: true });
            focusRequested.current = false;
        }
    }, [open, position]);

    useLayoutEffect(() => {
        if (!quickLog && restoreQuickLogFocus.current) {
            restoreQuickLogFocus.current = false;
            triggerRef.current?.focus({ preventScroll: true });
        }
    }, [quickLog]);

    const requireLogin = () => {
        if (preview?.viewer.authenticated) return false;
        close();
        if (preview?.routes.verification) window.location.assign(preview.routes.verification);
        else openLoginPanel();
        return true;
    };
    const review = (rating = null) => {
        if (!preview || loading) return show(true);
        if (requireLogin()) return;
        setQuickLog({ literature: preview.literature, rating });
        close();
    };
    const mutate = async (url, method, values = {}) => {
        if (processingRef.current) return;
        const finish = actionFeedback.begin();
        if (!finish) return;
        processingRef.current = true;
        const request = new AbortController();
        const previousAction = document.activeElement;
        const restoreAction = menuRef.current?.contains(previousAction) || coverRef.current?.contains(previousAction);
        setProcessing(true);
        setError('');
        try {
            const data = await requestAction(url, method, values, { signal: request.signal, csrfToken: preview.csrf_token });
            actionFeedback.publish(data.feedback);
            try {
                setPreview(await fetchPreview(literature.preview_url, request.signal));
            } catch {
                setError('Saved. Reopen this preview to refresh its details.');
            }
        } catch (failure) {
            if (!request.signal.aborted) {
                setError(failure.message);
                actionFeedback.error(failure.message);
            }
        } finally {
            finish();
            processingRef.current = false;
            if (!request.signal.aborted) setProcessing(false);
            if (restoreAction && !request.signal.aborted) window.requestAnimationFrame(() => {
                if (previousAction.isConnected && document.activeElement === document.body) previousAction.focus({ preventScroll: true });
            });
        }
    };
    const toggleReading = (status, source = triggerRef.current) => {
        if (!preview || loading) return show(true, source);
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
            <div ref={coverRef} className="literature-card-cover relative" data-preview-open={open} onBlurCapture={onBlur}>
                {children}
                <div className="literature-card-controls absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-linear-to-t from-ink-950/95 to-transparent p-2 pt-8 text-brand-cream">
                    {!compact && <>
                    <button type="button" data-cover-status-action disabled={processing} onClick={(event) => toggleReading('completed', event.currentTarget)} aria-label={readingStatus === 'completed' ? `Remove completed status for ${literature.title}` : `Mark ${literature.title} as completed`} title="Completed" aria-pressed={readingStatus === 'completed'} className={`${controlClass} ${readingStatus === 'completed' ? 'text-brand-coral' : ''}`}><ActionIcon name="completed" /></button>
                    <button type="button" data-cover-status-action disabled={processing} onClick={(event) => toggleReading('want_to_read', event.currentTarget)} aria-label={readingStatus === 'want_to_read' ? `Remove ${literature.title} from Readlist` : `Add ${literature.title} to Readlist`} title="Readlist" aria-pressed={readingStatus === 'want_to_read'} className={`${controlClass} ${readingStatus === 'want_to_read' ? 'text-brand-coral' : ''}`}><ActionIcon name="readlist" /></button>
                    </>}
                    <button ref={triggerRef} type="button" onClick={() => { if (open) close(true); else show(true); }} onKeyDown={(event) => {
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || (event.key === 'Tab' && !event.shiftKey && open)) {
                            event.preventDefault();
                            show(true);
                        }
                    }} aria-label={`Quick actions for ${literature.title}`} title="Quick actions" aria-haspopup="dialog" aria-expanded={open} aria-controls={open ? menuId : undefined} className={controlClass}><ActionIcon name="more" /></button>
                </div>
            </div>

            {open && createPortal(
                <div ref={menuRef} id={menuId} data-preview-panel role="dialog" aria-modal="false" aria-labelledby={`${menuId}-title`} aria-describedby={`${menuId}-metadata`} aria-busy={loading || processing} tabIndex={-1} style={{ width: 256, ...position, visibility: position ? 'visible' : 'hidden' }} className="fixed z-50 max-h-[calc(100dvh-1.5rem)] max-w-[calc(100vw_-_1.5rem)] overflow-y-auto overscroll-contain rounded-lg border border-ink-950/15 bg-brand-cream text-ink-950 shadow-xl shadow-ink-950/20 outline-none focus-visible:ring-2 focus-visible:ring-brand-coral" onKeyDown={onPanelKeyDown} onBlurCapture={onBlur}>
                    <div className="flex items-start justify-between gap-2 border-b border-ink-950/10 px-4 py-3">
                        <div className="min-w-0"><p id={`${menuId}-title`} className="line-clamp-2 text-sm font-bold leading-5 [overflow-wrap:anywhere]"><span className="sr-only">Quick actions for </span>{literature.title}</p><p id={`${menuId}-metadata`} className="mt-0.5 truncate text-xs leading-5 text-ink-950/70">{[literature.type_label, literature.year].filter(Boolean).join(' · ')}</p></div>
                        <button type="button" onClick={() => close(true)} aria-label={`Close preview for ${literature.title}`} className="-mr-1 grid size-11 shrink-0 place-items-center rounded-sm text-lg leading-none text-ink-950/65 hover:bg-brand-sky/20 hover:text-ink-950 focus-visible:outline-2 focus-visible:outline-brand-coral">×</button>
                    </div>
                    {loading ? <p role="status" className="px-4 py-5 text-center text-xs text-ink-950/55">Loading your actions…</p> : preview && (
                        <>
                            <fieldset disabled={processing} className="border-b border-ink-950/10 px-4 py-2 text-center">
                                <legend className="sr-only">Your rating</legend>
                                <div className="flex justify-center"><RatingInput compact keyboardNavigation value={preview.literature.review?.rating ?? ''} onChange={() => {}} onCommit={review} /></div>
                                {preview.literature.review && <p className="pb-1 text-xs leading-5 text-ink-950/70">Your rating: {Number(preview.literature.review.rating).toFixed(1)} / 5</p>}
                            </fieldset>
                            {preview.viewer.authenticated ? (
                                <div className="divide-y divide-ink-950/10">
                                    <button type="button" disabled={processing} aria-pressed={readingStatus === 'completed'} onClick={() => toggleReading('completed')} className={actionClass}><span>{readingStatus === 'completed' ? 'Remove completed status' : 'Mark as completed'}</span>{readingStatus === 'completed' && <span aria-hidden="true" className="text-brand-coral">✓</span>}</button>
                                    <button type="button" onClick={() => review()} disabled={processing} className={actionClass}>{preview.literature.review ? 'Edit rating or review…' : 'Rate or review literature…'}</button>
                                    <button type="button" disabled={processing} aria-pressed={readingStatus === 'want_to_read'} onClick={() => toggleReading('want_to_read')} className={actionClass}><span>{readingStatus === 'want_to_read' ? 'Remove from Readlist' : 'Add to Readlist'}</span>{readingStatus === 'want_to_read' && <span aria-hidden="true" className="text-brand-coral">✓</span>}</button>
                                    <div>
                                        <button type="button" disabled={processing} onClick={() => setListsOpen((current) => !current)} className={actionClass} aria-expanded={listsOpen} aria-controls={listsOpen ? `${menuId}-lists` : undefined}><span>Add to lists…</span><span aria-hidden="true">{listsOpen ? '−' : '+'}</span></button>
                                        {listsOpen && <div id={`${menuId}-lists`} className="border-t border-ink-950/10 bg-white/40 py-1">
                                            <div className="max-h-40 overflow-y-auto">
                                                {preview.lists.length ? preview.lists.map((list) => <button key={list.id} type="button" title={list.title} disabled={processing} aria-pressed={list.contains} onClick={() => toggleList(list)} className={actionClass}><span className="min-w-0 truncate">{list.title}{list.is_private ? ' · Private' : ''}</span><span className="text-brand-coral" aria-hidden="true">{list.contains ? '✓' : '+'}</span></button>) : <p className="px-4 py-2 text-xs text-ink-950/55">No custom lists yet.</p>}
                                            </div>
                                            <Link href={preview.routes.create_list} className={`${actionClass} text-brand-coral`} onClick={() => close()}>Create a list →</Link>
                                        </div>}
                                    </div>
                                </div>
                            ) : <button type="button" onClick={requireLogin} className={`${actionClass} text-brand-coral`}>{preview.routes.verification ? 'Verify email to rate and track' : 'Log in to rate and track'}</button>}
                            {preview.routes.where_to_read && <Link href={preview.routes.where_to_read} className={`${actionClass} border-t border-ink-950/10`} onClick={() => close()}>Where to read</Link>}
                            <LiteraturePreviewSources sources={preview.sources} />
                        </>
                    )}
                    {error && <p role="alert" className="px-4 py-3 text-xs leading-5 text-red-700">{error}</p>}
                    <Link href={preview?.routes.details ?? literature.url} className={`${actionClass} border-t border-ink-950/10 text-ink-950/60`} onClick={() => close()}>View literature details <span aria-hidden="true">→</span></Link>
                </div>, document.body,
            )}

            {quickLog && <QuickLogDialog open initialLiterature={quickLog.literature} initialRating={quickLog.rating} searchUrl={preview.routes.quick_log_search} onClose={() => { restoreQuickLogFocus.current = true; setQuickLog(null); }} />}
        </>
    );
}
