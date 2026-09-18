import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { RatingInput } from './DetailUi';

const coverThemes = {
    coral: 'from-brand-coral via-brand-cream to-brand-sky',
    sky: 'from-brand-sky via-brand-cream to-ink-950',
    cream: 'from-brand-cream via-brand-sky to-brand-cream',
    deep: 'from-ink-950 via-brand-cream to-brand-coral',
    mixed: 'from-brand-cream via-brand-coral to-brand-sky',
};

export default function ReviewDialog({ literature, viewer, routes, form, open, onClose }) {
    const dialogRef = useRef(null);

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const submit = (event) => {
        event.preventDefault();
        form.put(routes.review_update, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const remove = () => {
        if (!window.confirm('Delete your rating and review? This action cannot be undone.')) return;

        router.delete(routes.review_destroy, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <dialog
            ref={dialogRef}
            id="review-dialog"
            data-review-dialog
            aria-labelledby="review-dialog-title"
            className="review-dialog m-auto max-h-[92vh] w-[min(896px,calc(100%_-_1.5rem))] max-w-4xl overflow-y-auto rounded-sm border border-brand-blueberry/15 bg-brand-cream p-0 text-ink-950 shadow-[0_22px_60px_rgba(16,47,98,0.24)] backdrop:bg-ink-950/65"
            onCancel={(event) => { event.preventDefault(); onClose(); }}
            onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}
        >
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-brand-blueberry/15 bg-brand-cream px-5 py-3.5 sm:px-6">
                <div>
                    <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Log literature</p>
                    <h2 id="review-dialog-title" className="mt-0.5 text-xl font-extrabold tracking-tight text-brand-berry sm:text-2xl">I read…</h2>
                </div>
                <button type="button" className="grid size-9 place-items-center rounded-sm border border-transparent text-xl leading-none text-brand-blueberry transition hover:bg-brand-sky/20 hover:text-brand-coral" aria-label="Close review dialog" onClick={onClose}>×</button>
            </header>

            <form onSubmit={submit} className="grid gap-6 p-5 sm:p-6 md:grid-cols-[150px_minmax(0,1fr)] md:gap-7">
                <div className="mx-auto w-32 md:w-full">
                    <div className={`aspect-[2/3] overflow-hidden rounded-sm border border-brand-blueberry/15 bg-linear-to-br ${coverThemes[literature.theme] ?? 'from-brand-cream via-brand-sky to-brand-coral'} shadow-[0_8px_24px_rgba(16,47,98,0.16)]`}>
                        {literature.cover_url ? <img src={literature.cover_url} alt={`Cover of ${literature.title}`} className="size-full object-cover" /> : <div className="grid size-full place-items-center p-4 text-center font-serif text-4xl font-bold text-ink-950">{literature.initials}</div>}
                    </div>
                    <p className="mt-2 truncate text-center text-[0.62rem] font-bold uppercase tracking-[0.14em] text-brand-blueberry/60">{literature.type_label}</p>
                </div>

                <div className="min-w-0">
                    <h3 className="font-serif text-2xl font-bold leading-tight text-brand-blueberry">{literature.title}</h3>
                    <p className="mt-1 text-sm text-brand-blueberry/65">{[literature.author, literature.year].filter(Boolean).join(' · ')}</p>

                    <div className="mt-5 flex flex-wrap items-end gap-x-6 gap-y-4 border-y border-brand-blueberry/15 py-4">
                        <label className="flex h-10 items-center gap-2 text-sm font-semibold text-brand-berry">
                            <input type="checkbox" checked readOnly className="size-4 accent-brand-coral" />
                            Completed / Read
                        </label>
                        <label htmlFor="review-completed-at" className="text-sm font-semibold text-brand-berry">
                            <span className="mb-1 block text-xs text-brand-blueberry/65">Read on</span>
                            <input
                                id="review-completed-at"
                                type="date"
                                required
                                value={form.data.completed_at}
                                onChange={(event) => form.setData('completed_at', event.target.value)}
                                className="h-10 rounded-sm border border-brand-blueberry/20 bg-white/75 px-3 text-sm text-ink-950 outline-none focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20"
                            />
                        </label>
                    </div>
                    {form.errors.completed_at && <p className="mt-2 text-sm font-semibold text-red-700">{form.errors.completed_at}</p>}

                    <div className="mt-5">
                        <label htmlFor="review-body" className="text-sm font-semibold text-brand-berry">Review <span className="font-normal text-brand-blueberry/60">(optional)</span></label>
                        <textarea id="review-body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} rows="7" maxLength="5000" placeholder="Add a review…" className="mt-2 w-full resize-y rounded-sm border border-brand-blueberry/20 bg-white/75 px-4 py-3 text-base leading-6 text-ink-950 outline-none placeholder:text-brand-blueberry/55 focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20" />
                        {form.errors.body && <p className="mt-2 text-sm font-semibold text-red-700">{form.errors.body}</p>}
                    </div>

                    <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <fieldset>
                            <legend className="text-sm font-semibold text-brand-berry">Rating</legend>
                            <div className="mt-1 flex flex-wrap items-center gap-3">
                                <RatingInput value={form.data.rating} onChange={(rating) => form.setData('rating', rating)} compact inactiveClassName="text-brand-blueberry/25" />
                                <output className="text-xs font-semibold text-brand-blueberry/65">{form.data.rating ? `${Number(form.data.rating).toFixed(1)} out of 5` : 'Choose a rating'}</output>
                            </div>
                            {form.errors.rating && <p className="mt-1.5 text-sm font-semibold text-red-700">{form.errors.rating}</p>}
                        </fieldset>

                        <label className="flex items-center gap-2 text-sm text-brand-blueberry/75">
                            <input type="checkbox" checked={form.data.contains_spoiler} onChange={(event) => form.setData('contains_spoiler', event.target.checked)} className="size-4 accent-brand-coral" />
                            Contains spoilers
                        </label>
                    </div>

                    <div className="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-brand-blueberry/15 bg-white/35 px-4 py-3">
                        {viewer.current_review && <button type="button" onClick={remove} className="mr-auto rounded-sm border border-red-700/25 px-3.5 py-2 text-xs font-bold text-red-700 transition hover:border-red-700/50 hover:bg-red-100/70">Delete review</button>}
                        <button type="button" onClick={onClose} className="rounded-sm border border-brand-blueberry/20 px-3.5 py-2 text-xs font-bold text-brand-blueberry transition hover:border-brand-coral hover:text-brand-coral">Cancel</button>
                        <button disabled={form.processing} className="rounded-sm bg-brand-berry px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-blueberry disabled:opacity-50">Save</button>
                    </div>
                </div>
            </form>
        </dialog>
    );
}
