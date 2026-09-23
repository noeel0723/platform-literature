import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { RatingInput } from './LiteratureDetail/DetailUi';

function localDateValue() {
    const today = new Date();
    return new Date(today.getTime() - today.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
}

export default function QuickLogDialog({ open, onClose, searchUrl }) {
    const dialogRef = useRef(null);
    const searchRef = useRef(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [status, setStatus] = useState('Loading recent literature...');
    const [selected, setSelected] = useState(null);
    const form = useForm({
        rating: '',
        body: '',
        contains_spoiler: false,
        completed_at: localDateValue(),
    });

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) {
            setQuery('');
            setSelected(null);
            form.clearErrors();
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    useEffect(() => {
        if (open && !selected) searchRef.current?.focus();
    }, [open, selected]);

    useEffect(() => {
        if (!open || selected) return;

        const request = new AbortController();
        const timer = window.setTimeout(async () => {
            setStatus(query.trim() ? 'Searching literature...' : 'Loading recent literature...');

            try {
                const url = new URL(searchUrl, window.location.origin);
                if (query.trim()) url.searchParams.set('q', query.trim());

                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                });
                if (!response.ok) throw new Error('Quick log search failed.');

                const payload = await response.json();
                const items = Array.isArray(payload.data) ? payload.data : [];
                setResults(items);
                setStatus(items.length ? '' : 'No literature matched your search.');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setResults([]);
                    setStatus('Search is temporarily unavailable. Please try again.');
                }
            }
        }, query.trim() ? 250 : 0);

        return () => {
            window.clearTimeout(timer);
            request.abort();
        };
    }, [open, query, searchUrl, selected]);

    const chooseLiterature = (literature) => {
        form.clearErrors();
        form.setData({
            rating: literature.review?.rating ?? '',
            body: literature.review?.body ?? '',
            contains_spoiler: Boolean(literature.review?.contains_spoiler),
            completed_at: literature.completed_at ?? localDateValue(),
        });
        setSelected(literature);
    };

    const submit = (event) => {
        event.preventDefault();
        if (!selected) return;

        if (!Number(form.data.rating)) {
            form.setError('rating', 'Please choose a rating before saving.');
            return;
        }

        form.put(selected.review_url, { onSuccess: onClose });
    };

    return (
        <dialog
            ref={dialogRef}
            id="quick-log-dialog"
            aria-labelledby="quick-log-title"
            className="review-dialog m-auto max-h-[92vh] w-[min(896px,calc(100%_-_1.5rem))] max-w-4xl overflow-y-auto rounded-sm border border-brand-blueberry/15 bg-brand-cream p-0 text-ink-950 shadow-[0_22px_60px_rgba(16,47,98,0.24)]"
            onCancel={(event) => { event.preventDefault(); onClose(); }}
            onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}
        >
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-brand-blueberry/15 bg-brand-cream px-5 py-3.5 sm:px-6">
                <div className="flex items-center gap-3">
                    {selected && <button type="button" onClick={() => setSelected(null)} className="rounded-sm border border-brand-blueberry/20 px-2.5 py-1.5 text-xs font-bold text-brand-blueberry transition hover:border-brand-coral hover:text-brand-coral">← Back</button>}
                    <div>
                        <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Quick log</p>
                        <h2 id="quick-log-title" className="mt-0.5 text-xl font-extrabold tracking-tight text-brand-berry sm:text-2xl">{selected ? 'I read…' : 'Choose a literature'}</h2>
                    </div>
                </div>
                <button type="button" onClick={onClose} className="grid size-9 place-items-center rounded-sm text-xl text-brand-blueberry transition hover:bg-brand-sky/20 hover:text-brand-coral" aria-label="Close quick log">×</button>
            </header>

            {!selected ? (
                <div className="p-5 sm:p-6">
                    <label htmlFor="quick-log-search" className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">Search your catalog</label>
                    <div className="mt-2 flex items-center gap-3 rounded-lg border border-ink-950/15 bg-white/75 px-4 focus-within:border-brand-berry focus-within:ring-3 focus-within:ring-brand-sky/20">
                        <svg aria-hidden="true" className="size-4 shrink-0 text-ink-950/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                        <input ref={searchRef} id="quick-log-search" type="search" autoComplete="off" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search by title or author..." className="h-12 min-w-0 flex-1 border-0 bg-transparent text-sm outline-none" />
                    </div>
                    <p className="mt-3 text-xs text-ink-950/45">Select a result to add or update your rating and review.</p>
                    <div className="mt-4 max-h-[24rem] overflow-y-auto rounded-lg border border-ink-950/10" aria-live="polite">
                        {status ? <p className="px-4 py-8 text-center text-sm text-ink-950/50">{status}</p> : results.map((literature) => (
                            <button key={literature.id} type="button" onClick={() => chooseLiterature(literature)} className="quick-log-result">
                                <span className="quick-log-result-cover">
                                    {literature.cover_url ? <img src={literature.cover_url} alt="" loading="lazy" className="size-full object-cover" /> : literature.title.slice(0, 2).toUpperCase()}
                                </span>
                                <span className="min-w-0 flex-1 text-left">
                                    <span className="block truncate font-serif text-base font-bold text-ink-950">{literature.title}</span>
                                    <span className="mt-0.5 block truncate text-xs text-ink-950/50">{literature.authors?.length ? literature.authors.join(' & ') : 'Author unavailable'}</span>
                                    <span className="mt-1 block text-[0.65rem] font-bold uppercase tracking-[0.12em] text-ink-950/40">{[literature.type, literature.year].filter(Boolean).join(' · ')}</span>
                                </span>
                                <span className="shrink-0 text-xs font-bold uppercase tracking-wider text-brand-stem">{literature.review ? 'Edit' : 'Log'}</span>
                            </button>
                        ))}
                    </div>
                </div>
            ) : (
                <form onSubmit={submit} className="grid gap-6 p-5 sm:p-6 md:grid-cols-[150px_minmax(0,1fr)] md:gap-7">
                    <div className="mx-auto w-32 md:w-full">
                        <div className="aspect-[2/3] overflow-hidden rounded-sm border border-brand-blueberry/15 bg-brand-sky/20 shadow-[0_8px_24px_rgba(16,47,98,0.16)]">
                            {selected.cover_url ? <img src={selected.cover_url} alt={`Cover of ${selected.title}`} className="size-full object-cover" /> : <span className="grid size-full place-items-center font-serif text-3xl font-bold text-ink-950">{selected.title.slice(0, 2).toUpperCase()}</span>}
                        </div>
                        <p className="mt-2 truncate text-center text-[0.62rem] font-bold uppercase tracking-[0.14em] text-brand-blueberry/60">{selected.type}</p>
                    </div>
                    <div className="min-w-0">
                        <h3 className="font-serif text-2xl font-bold leading-tight text-brand-blueberry">{selected.title}</h3>
                        <p className="mt-1 text-sm text-brand-blueberry/65">{[selected.authors?.join(' & ') || 'Author unavailable', selected.year].filter(Boolean).join(' · ')}</p>
                        <div className="mt-5 flex flex-wrap items-end gap-x-6 gap-y-4 border-y border-brand-blueberry/15 py-4">
                            <span className="flex h-10 items-center gap-2 text-sm font-semibold text-brand-berry">✓ Completed / Read</span>
                            <label htmlFor="quick-log-completed-at" className="text-sm font-semibold text-brand-berry"><span className="mb-1 block text-xs text-brand-blueberry/65">Read on</span><input id="quick-log-completed-at" type="date" required value={form.data.completed_at} onChange={(event) => form.setData('completed_at', event.target.value)} className="h-10 rounded-sm border border-brand-blueberry/20 bg-white/75 px-3 text-sm text-ink-950 outline-none focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20" /></label>
                        </div>
                        {form.errors.completed_at && <p className="mt-2 text-sm font-semibold text-red-700">{form.errors.completed_at}</p>}
                        <div className="mt-5"><label htmlFor="quick-log-body" className="text-sm font-semibold text-brand-berry">Review <span className="font-normal text-brand-blueberry/60">(optional)</span></label><textarea id="quick-log-body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} rows="7" maxLength="5000" placeholder="Add a review…" className="mt-2 w-full resize-y rounded-sm border border-brand-blueberry/20 bg-white/75 px-4 py-3 text-base leading-6 text-ink-950 outline-none placeholder:text-brand-blueberry/55 focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20" />{form.errors.body && <p className="mt-2 text-sm font-semibold text-red-700">{form.errors.body}</p>}</div>
                        <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <fieldset><legend className="text-sm font-semibold text-brand-berry">Rating</legend><div className="mt-1 flex flex-wrap items-center gap-3"><RatingInput value={form.data.rating} onChange={(rating) => { form.setData('rating', rating); form.clearErrors('rating'); }} compact inactiveClassName="text-brand-blueberry/25" /><output className="text-xs font-semibold text-brand-blueberry/65">{form.data.rating ? `${Number(form.data.rating).toFixed(1)} out of 5` : 'Choose a rating'}</output></div>{form.errors.rating && <p className="mt-1.5 text-sm font-semibold text-red-700">{form.errors.rating}</p>}</fieldset>
                            <label className="flex items-center gap-2 text-sm text-brand-blueberry/75"><input type="checkbox" checked={form.data.contains_spoiler} onChange={(event) => form.setData('contains_spoiler', event.target.checked)} className="size-4 accent-brand-coral" />Contains spoilers</label>
                        </div>
                        <div className="mt-6 flex justify-end border-t border-brand-blueberry/15 bg-white/35 px-4 py-3"><button type="submit" disabled={form.processing} className="rounded-sm bg-brand-berry px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-blueberry disabled:opacity-50">{form.processing ? 'Saving…' : 'Save'}</button></div>
                    </div>
                </form>
            )}
        </dialog>
    );
}
