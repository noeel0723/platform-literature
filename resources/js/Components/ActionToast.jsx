import { useEffect, useLayoutEffect, useRef, useState, useSyncExternalStore } from 'react';

import { actionFeedback } from '../Support/actionFeedback';

export function ActionToastMessage({ toast, blocked, onUndo, onDismiss, onPause, onResume }) {
    return (
        <div data-action-toast className="pointer-events-auto flex min-w-0 items-center gap-2 rounded-lg border border-brand-cream/15 bg-ink-950 px-3 py-2 text-brand-cream shadow-lg shadow-ink-950/15" onMouseEnter={onPause} onMouseLeave={onResume} onFocusCapture={onPause} onBlurCapture={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) onResume(); }}>
            <p role={toast.tone === 'error' ? 'alert' : 'status'} aria-atomic="true" className="min-w-0 flex-1 text-xs leading-5 [overflow-wrap:anywhere]">
                <span aria-hidden="true" className={`mr-1.5 ${toast.tone === 'error' ? 'text-brand-coral' : 'text-brand-sky'}`}>{toast.tone === 'error' ? '!' : '✓'}</span>
                {toast.busy ? 'Undoing…' : toast.message}
            </p>
            {toast.undo && <button type="button" disabled={toast.busy || blocked} onClick={onUndo} className="min-h-10 shrink-0 rounded px-2 text-xs font-bold text-brand-sky underline decoration-brand-sky/50 underline-offset-4 hover:text-brand-cream focus-visible:outline-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50">Undo</button>}
            <button type="button" disabled={toast.busy} onClick={onDismiss} aria-label="Dismiss notification" className="grid size-10 shrink-0 place-items-center rounded text-lg text-brand-cream/65 hover:text-brand-cream focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-40">×</button>
        </div>
    );
}

export default function ActionToast() {
    const { toast, pending } = useSyncExternalStore(actionFeedback.subscribe, actionFeedback.getSnapshot, actionFeedback.getSnapshot);
    const [paused, setPaused] = useState(false);
    const lastFocus = useRef(null);
    const regionRef = useRef(null);
    const previousToast = useRef(null);

    useLayoutEffect(() => {
        const region = regionRef.current;
        const root = region?.parentElement;
        if (!root) return undefined;
        const updateSpace = () => root.style.setProperty('--action-toast-space', toast ? `${region.getBoundingClientRect().height + 24}px` : '0px');
        updateSpace();
        const observer = new ResizeObserver(updateSpace);
        observer.observe(region);
        return () => observer.disconnect();
    }, [Boolean(toast)]);

    useEffect(() => {
        setPaused(false);
        if (previousToast.current && document.activeElement === document.body && lastFocus.current?.isConnected) lastFocus.current.focus({ preventScroll: true });
        previousToast.current = toast?.id;
    }, [toast?.id]);

    useEffect(() => {
        if (!toast) return undefined;
        if (!regionRef.current?.contains(document.activeElement)) lastFocus.current = document.activeElement;
        if (toast.busy || paused || pending) return undefined;
        const duration = toast.undo ? Math.min(12000, Date.parse(toast.undo.expires_at) - Date.now()) : 7000;
        const timer = window.setTimeout(() => actionFeedback.dismiss(toast.id), Math.max(0, duration));
        return () => window.clearTimeout(timer);
    }, [toast, paused, pending]);

    useEffect(() => {
        if (toast || document.activeElement !== document.body || !lastFocus.current?.isConnected) return;
        lastFocus.current.focus({ preventScroll: true });
    }, [toast]);

    return (
        <div ref={regionRef} aria-label="Action feedback" onKeyDown={(event) => { if (event.key === 'Escape' && toast && !toast.busy) { event.preventDefault(); event.stopPropagation(); actionFeedback.dismiss(toast.id); } }} className="action-toast-region pointer-events-none fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-[70] mx-auto w-auto max-w-sm sm:left-auto sm:right-5 sm:mx-0 sm:w-96">
            {toast && <ActionToastMessage key={toast.id} toast={toast} blocked={pending > 0} onUndo={() => { void actionFeedback.undo(); }} onDismiss={() => actionFeedback.dismiss(toast.id)} onPause={() => setPaused(true)} onResume={() => setPaused(false)} />}
        </div>
    );
}
