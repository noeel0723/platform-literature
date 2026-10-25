const PREFIX = 'literahaven:review-draft:v1:';
export const DRAFT_OWNER_KEY = 'literahaven:review-draft-owner';
const MAX_AGE = 30 * 24 * 60 * 60 * 1000;

export const emptyReviewValues = () => ({ body: '', rating: '', contains_spoiler: false, completed_at: '' });
export const reviewValues = (values = {}) => ({
    body: typeof values.body === 'string' ? values.body.slice(0, 5000) : '',
    rating: values.rating === '' || values.rating == null ? '' : Number(values.rating),
    contains_spoiler: Boolean(values.contains_spoiler),
    completed_at: typeof values.completed_at === 'string' ? values.completed_at : '',
});
const sameValues = (left, right) => JSON.stringify(reviewValues(left)) === JSON.stringify(reviewValues(right));
const validContext = (context) => context && Number.isSafeInteger(context.user_id) && context.user_id > 0 && /^(canonical|literature):[1-9]\d*$/.test(context.work_key) && /^[a-f0-9]{64}$/.test(context.revision);
const validValues = (values) => values && typeof values.body === 'string' && values.body.length <= 5000
    && (values.rating === '' || (typeof values.rating === 'number' && values.rating >= 0.5 && values.rating <= 5 && Number.isInteger(values.rating * 2)))
    && typeof values.contains_spoiler === 'boolean' && typeof values.completed_at === 'string' && (values.completed_at === '' || /^\d{4}-\d{2}-\d{2}$/.test(values.completed_at));

export function createReviewDraftRepository({ storage, now = Date.now }) {
    let userId = null;
    let available = true;
    const listeners = new Set();
    const access = () => typeof storage === 'function' ? storage() : storage;
    const notify = () => listeners.forEach((listener) => listener(userId));
    const key = (context) => `${PREFIX}${context.user_id}:${context.work_key}`;
    const owns = (context) => {
        if (!available) throw new Error('Local storage unavailable.');
        const owner = access().getItem(DRAFT_OWNER_KEY);
        if (owner !== (userId ?? 'guest')) repository.syncOwner();
        return validContext(context) && userId === String(context.user_id) && owner === userId;
    };
    const repository = {
        get userId() { return userId; },
        subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
        activate(id) {
            userId = Number.isSafeInteger(Number(id)) && Number(id) > 0 ? String(Number(id)) : null;
            try {
                const target = access();
                const owner = userId ?? 'guest';
                if (target.getItem(DRAFT_OWNER_KEY) !== owner) {
                    // Invalidate writers in other tabs before removing the previous account's drafts.
                    target.setItem(DRAFT_OWNER_KEY, owner);
                    const keys = Array.from({ length: target.length }, (_, index) => target.key(index));
                    keys.filter((item) => item?.startsWith(PREFIX)).forEach((item) => target.removeItem(item));
                }
                available = true;
            } catch { available = false; }
            notify();
        },
        syncOwner() {
            try {
                const owner = access().getItem(DRAFT_OWNER_KEY);
                userId = /^[1-9]\d*$/.test(owner ?? '') ? owner : null;
                available = true;
            } catch { available = false; }
            notify();
        },
        read(context) {
            if (!owns(context)) return null;
            const raw = access().getItem(key(context));
            if (!raw) return null;
            let record;
            try { record = JSON.parse(raw); } catch { record = null; }
            if (!record || record.version !== 1 || record.user_id !== context.user_id || record.work_key !== context.work_key
                || !/^[a-f0-9]{64}$/.test(record.revision ?? '') || !validValues(record.values)
                || !Number.isFinite(record.saved_at) || now() - record.saved_at > MAX_AGE || record.saved_at > now() + 300000) {
                access().removeItem(key(context));
                return null;
            }
            return record;
        },
        write(context, values) {
            if (!owns(context)) return false;
            const record = { version: 1, user_id: context.user_id, work_key: context.work_key, revision: context.revision, values: reviewValues(values), saved_at: now() };
            access().setItem(key(context), JSON.stringify(record));
            if (access().getItem(DRAFT_OWNER_KEY) !== String(context.user_id)) {
                access().removeItem(key(context));
                return false;
            }
            return true;
        },
        remove(context) {
            if (validContext(context) && owns(context)) access().removeItem(key(context));
        },
    };
    return repository;
}

export const reviewDrafts = createReviewDraftRepository({ storage: () => window.localStorage });

export function installReviewDraftPrivacy({ repository, browserWindow, browserDocument, router, userId, logoutUrl }) {
    let pageUserId = userId ?? null;
    repository.activate(pageUserId);
    const onStorage = (event) => { if (event.key === DRAFT_OWNER_KEY || event.key === null) repository.syncOwner(); };
    const isLogout = (url) => {
        if (!logoutUrl) return false;
        const candidate = new URL(url, browserWindow.location.origin);
        const target = new URL(logoutUrl, browserWindow.location.origin);
        return candidate.origin === target.origin && candidate.pathname === target.pathname;
    };
    const onSubmit = (event) => { if (event.target?.tagName === 'FORM' && isLogout(event.target.action)) repository.activate(null); };
    const offBefore = router.on('before', (event) => { if (isLogout(event.detail.visit.url.href)) repository.activate(null); });
    const offNavigate = router.on('navigate', (event) => {
        const props = event.detail.page.props;
        if (!Object.hasOwn(props, 'reviewDraftUserId') || props.reviewDraftUserId === pageUserId) return;
        pageUserId = props.reviewDraftUserId;
        repository.activate(pageUserId);
    });
    browserWindow.addEventListener('storage', onStorage);
    browserDocument.addEventListener('submit', onSubmit, true);
    return () => {
        offBefore(); offNavigate();
        browserWindow.removeEventListener('storage', onStorage);
        browserDocument.removeEventListener('submit', onSubmit, true);
    };
}

export function createReviewDraftSession({ repository, apply, onState = () => {}, delay = 600, schedule = setTimeout, cancel = clearTimeout }) {
    let context = null;
    let base = emptyReviewValues();
    let values = base;
    let conflict = null;
    let ratingOverride = null;
    let timer = null;
    let closed = true;
    let dirty = false;
    let pendingWrite = false;
    let lastStoredValues = null;
    let state = { status: 'checking', hasDraft: false, blocked: true };
    const emit = (status) => {
        state = { status, hasDraft: Boolean(conflict) || dirty, blocked: status === 'checking' || status === 'conflict' || status === 'account-changed' };
        onState(state);
    };
    const stopTimer = () => { if (timer !== null) cancel(timer); timer = null; };
    const changedAccount = () => {
        if (context && repository.userId !== String(context.user_id)) {
            stopTimer(); closed = true; dirty = false; pendingWrite = false; conflict = null;
            values = emptyReviewValues(); apply(values); emit('account-changed');
            return true;
        }
        return false;
    };
    const unsubscribe = repository.subscribe(changedAccount);
    const queue = () => { stopTimer(); timer = schedule(() => session.flush(), delay); };
    const session = {
        get state() { return state; },
        start(nextContext, serverValues, { trusted = true, initialRating = null } = {}) {
            context = nextContext; base = reviewValues(serverValues); values = base;
            closed = false; dirty = false; pendingWrite = false; lastStoredValues = null; conflict = null; ratingOverride = initialRating;
            if (!validContext(context) || changedAccount()) {
                closed = true; apply(emptyReviewValues()); emit('account-changed'); return;
            }
            let record = null;
            let available = true;
            try { record = repository.read(context); } catch { available = false; }
            lastStoredValues = record?.values ?? null;
            if (changedAccount()) return;
            if (record && trusted && sameValues(record.values, base)) {
                try { repository.remove(context); } catch { available = false; }
                record = null;
            }
            if (record && (!trusted || record.revision !== context.revision)) {
                conflict = record; apply(base); emit('conflict'); return;
            }
            values = reviewValues(record?.values ?? base);
            if (initialRating != null) values.rating = Number(initialRating);
            dirty = !sameValues(values, base);
            apply(values);
            emit(!available ? 'unavailable' : !trusted ? 'offline' : record ? 'recovered' : 'idle');
            if (dirty && initialRating != null && (!record || values.rating !== record.values.rating)) { pendingWrite = true; emit('pending'); queue(); }
        },
        change(field, value) {
            if (closed || state.blocked || changedAccount() || !Object.hasOwn(emptyReviewValues(), field)) return;
            values = reviewValues({ ...values, [field]: value });
            dirty = !sameValues(values, base);
            pendingWrite = true;
            apply(values); emit('pending'); queue();
        },
        flush() {
            stopTimer();
            if (closed || state.blocked || changedAccount() || !pendingWrite) return;
            try {
                if (!dirty) { repository.remove(context); pendingWrite = false; lastStoredValues = null; emit('idle'); return; }
                const saved = repository.write(context, values);
                if (changedAccount()) return;
                if (saved) { pendingWrite = false; lastStoredValues = { ...values }; }
                emit(saved ? 'saved' : 'unavailable');
            } catch { emit('unavailable'); }
        },
        restore() {
            if (!conflict || closed || changedAccount()) return;
            values = reviewValues(conflict.values);
            if (ratingOverride != null) values.rating = Number(ratingOverride);
            conflict = null; dirty = !sameValues(values, base); pendingWrite = true;
            apply(values); emit('recovered'); queue();
        },
        discard() {
            if (closed || changedAccount()) return;
            stopTimer();
            let available = true;
            try { repository.remove(context); } catch { available = false; }
            values = base; dirty = false; pendingWrite = false; lastStoredValues = null; conflict = null;
            apply(values); emit(available ? 'discarded' : 'unavailable');
        },
        published() {
            stopTimer(); closed = true; dirty = false; pendingWrite = false; conflict = null;
            try {
                const stored = repository.read(context);
                if (!stored || sameValues(stored.values, values) || (lastStoredValues && sameValues(stored.values, lastStoredValues))) repository.remove(context);
            } catch { /* A matching server value is recognized on the next open. */ }
            emit('idle');
        },
        dispose() { session.flush(); closed = true; stopTimer(); unsubscribe(); },
    };
    return session;
}
