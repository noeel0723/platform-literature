import { useEffect, useRef, useState } from 'react';

import { createReviewDraftSession, reviewDrafts } from './reviewDraft';

function valuesFor(literature) {
    const today = new Date();
    return {
        body: literature.review?.body ?? '',
        rating: literature.review?.rating ?? '',
        contains_spoiler: Boolean(literature.review?.contains_spoiler),
        completed_at: literature.completed_at ?? new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10),
    };
}

export default function useReviewDraft({ open, literature, form, initialRating = null }) {
    const sessionRef = useRef(null);
    const formRef = useRef(form);
    formRef.current = form;
    const [state, setState] = useState({ status: 'checking', hasDraft: false, blocked: true });
    const workKey = literature?.review_draft_context?.work_key;
    const userId = literature?.review_draft_context?.user_id;
    const identity = `${userId}:${workKey}`;

    useEffect(() => {
        if (!open || !literature) return undefined;
        const session = createReviewDraftSession({ repository: reviewDrafts, apply: (values) => formRef.current.setData(values), onState: (next) => setState({ ...next, identity }) });
        sessionRef.current = session;
        setState({ status: 'checking', hasDraft: false, blocked: true, identity });
        const request = new AbortController();
        const timeout = window.setTimeout(() => request.abort(), 8000);
        let disposed = false;
        const prepare = async () => {
            try {
                if (!literature.preview_url) throw new Error('No fresh review context.');
                const response = await fetch(literature.preview_url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: request.signal });
                if (!response.ok) throw new Error('Review context unavailable.');
                const payload = await response.json();
                if (disposed) return;
                if (payload.literature.review_draft_context?.user_id !== userId) {
                    reviewDrafts.activate(payload.literature.review_draft_context?.user_id ?? null);
                    session.start(null, {});
                    return;
                }
                session.start(payload.literature.review_draft_context, valuesFor(payload.literature), { initialRating });
            } catch {
                if (!disposed) session.start(literature.review_draft_context, valuesFor(literature), { trusted: false, initialRating });
            } finally { window.clearTimeout(timeout); }
        };
        void prepare();
        const flush = () => session.flush();
        const onVisibility = () => { if (document.visibilityState === 'hidden') flush(); };
        window.addEventListener('pagehide', flush);
        window.addEventListener('beforeunload', flush);
        document.addEventListener('visibilitychange', onVisibility);
        return () => {
            disposed = true; window.clearTimeout(timeout); request.abort(); session.dispose();
            if (sessionRef.current === session) sessionRef.current = null;
            window.removeEventListener('pagehide', flush);
            window.removeEventListener('beforeunload', flush);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [open, workKey, userId]);

    const initializing = !sessionRef.current || state.identity !== identity;
    return {
        ...state,
        status: initializing ? 'checking' : state.status,
        blocked: initializing || state.blocked,
        hasDraft: !initializing && state.hasDraft,
        change: (field, value) => sessionRef.current?.change(field, value),
        flush: () => sessionRef.current?.flush(),
        restore: () => { formRef.current.clearErrors(); sessionRef.current?.restore(); },
        discard: () => { formRef.current.clearErrors(); sessionRef.current?.discard(); },
        published: () => sessionRef.current?.published(),
    };
}
