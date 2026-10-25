export const CONNECTION_MESSAGE = 'Connection interrupted. Refresh to check whether your change was saved before trying again.';
export const ACTION_RESTORED_EVENT = 'literahaven:action-restored';

export function actionErrorMessage(status, payload = {}) {
    payload ??= {};
    if (status === 422) return Object.values(payload.errors ?? {}).flat()[0] ?? 'Check the form and try again.';
    if (status === 401 || status === 419) return 'Your session has expired. Refresh the page and sign in again.';
    if (status === 403) return 'This action is not available for your account. Check your sign-in and email verification.';
    if (status === 404 || status === 410) return 'This action is no longer available. Please refresh the page.';
    if (status === 409) return payload.message ?? 'This item has changed. Refresh before trying again.';
    if (status === 429) return 'Please wait a moment before trying again.';
    return 'The change could not be saved. Please try again in a moment.';
}

export async function requestAction(url, method = 'POST', values = {}, { signal, csrfToken, fetcher = fetch } = {}) {
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
    else {
        const cookie = typeof document !== 'undefined' ? document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN=')) : null;
        if (cookie) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
    }
    let response;
    try {
        response = await fetcher(url, { method, headers, credentials: 'same-origin', signal, body: JSON.stringify(values) });
    } catch (cause) {
        if (cause.name === 'AbortError') throw cause;
        throw new Error(CONNECTION_MESSAGE, { cause });
    }
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || response.redirected) {
        const error = new Error(actionErrorMessage(response.redirected ? 401 : response.status, payload));
        error.status = response.status;
        throw error;
    }
    if (!payload.feedback?.id || !payload.feedback.message) throw new Error(CONNECTION_MESSAGE);
    return payload;
}

export function createFeedbackStore({ undoRequest = requestAction, refresh = () => {} } = {}) {
    let snapshot = { toast: null, pending: 0 };
    let sequence = 0;
    const listeners = new Set();
    const seen = new Set();
    const update = (changes) => {
        snapshot = { ...snapshot, ...changes };
        listeners.forEach((listener) => listener());
    };
    const store = {
        subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
        getSnapshot: () => snapshot,
        setRefresh(callback) { refresh = callback; },
        publish(feedback) {
            if (!feedback?.id || !feedback.message || seen.has(feedback.id)) return;
            seen.add(feedback.id);
            if (seen.size > 100) seen.delete(seen.values().next().value);
            const undo = feedback.undo && Date.parse(feedback.undo.expires_at) > Date.now() ? feedback.undo : null;
            update({ toast: { ...feedback, undo, tone: feedback.tone ?? 'success', busy: false } });
        },
        error(message) {
            store.publish({ id: `error-${++sequence}`, message, tone: 'error' });
        },
        dismiss(id) {
            if (snapshot.toast?.id === id && !snapshot.toast.busy) update({ toast: null });
        },
        begin() {
            if (snapshot.toast?.busy) return null;
            update({ pending: snapshot.pending + 1 });
            let finished = false;
            return () => {
                if (finished) return;
                finished = true;
                update({ pending: Math.max(0, snapshot.pending - 1) });
            };
        },
        async undo() {
            const toast = snapshot.toast;
            if (!toast?.undo || toast.busy || snapshot.pending) return;
            if (Date.parse(toast.undo.expires_at) <= Date.now()) {
                store.error('This Undo has expired. Your saved change is still in place.');
                return;
            }
            update({ toast: { ...toast, busy: true } });
            let result;
            try {
                result = await undoRequest(toast.undo.url);
            } catch (error) {
                if (snapshot.toast?.id === toast.id) update({ toast: { ...toast, busy: false, undo: null, tone: 'error', message: error.message || CONNECTION_MESSAGE } });
                return;
            }
            if (snapshot.toast?.id === toast.id) store.publish(result.feedback);
            try {
                await refresh(toast.scope);
            } catch {
                store.error('Action undone. Refresh the page to see the updated details.');
            }
        },
    };
    return store;
}

export const actionFeedback = createFeedbackStore();

export function feedbackVisitOptions(options = {}, store = actionFeedback) {
    return {
        preserveScroll: true,
        ...options,
        onSuccess: (page) => {
            store.publish(page.props?.actionFeedback);
            options.onSuccess?.(page);
        },
        onError: (errors) => {
            store.error(Object.values(errors).flat()[0] ?? 'The change could not be saved. Check the form and try again.');
            options.onError?.(errors);
        },
        onNetworkError: (error) => {
            store.error(CONNECTION_MESSAGE);
            options.onNetworkError?.(error);
            return false;
        },
        onHttpException: (response) => {
            store.error(actionErrorMessage(response.status, response.data));
            options.onHttpException?.(response);
            return false;
        },
    };
}
