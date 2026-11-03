import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

import { feedbackVisitOptions } from '../Support/actionFeedback';
import { reviewDrafts } from '../Support/reviewDraft';

export default function DeleteAccountSection({ deleteUrl, homeUrl }) {
    const form = useForm({ password: '', confirmation: false });
    const detailsRef = useRef(null);
    const summaryRef = useRef(null);
    const passwordRef = useRef(null);
    const submitting = useRef(false);

    const cancel = () => {
        if (form.processing || submitting.current) return;
        form.reset();
        form.clearErrors();
        detailsRef.current.open = false;
        summaryRef.current?.focus();
    };
    const submit = (event) => {
        event.preventDefault();
        if (form.processing || submitting.current) return;
        submitting.current = true;
        form.delete(deleteUrl, feedbackVisitOptions({
            errorBag: 'deleteAccount',
            onError: () => passwordRef.current?.focus(),
            onSuccess: () => {
                reviewDrafts.activate(null);
                window.location.assign(homeUrl);
            },
            onFinish: () => { submitting.current = false; form.reset('password'); },
        }));
    };

    return (
        <section className="mt-8 rounded-xl border border-brand-coral/25 bg-white/30 p-5 sm:p-6" aria-labelledby="delete-account-heading">
            <h2 id="delete-account-heading" className="text-sm font-semibold text-ink-950">Delete account</h2>
            <p className="mt-1 text-sm leading-6 text-ink-950/65">Permanently remove your profile and personal activity from Literahaven. This cannot be undone.</p>
            <details ref={detailsRef} className="mt-3">
                <summary ref={summaryRef} className="w-fit cursor-pointer rounded-sm text-xs font-bold text-brand-coral underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral">Delete my account</summary>
                <form onSubmit={submit} className="mt-4 max-w-lg">
                    <p id="delete-account-help" className="text-xs leading-5 text-ink-950/65">Your reviews, ratings, reading history, favorites, lists, discussions, comments, and follows will be deleted, including replies in deleted threads. Your profile photo and banner will also be removed.</p>
                    <fieldset disabled={form.processing} aria-describedby="delete-account-help" className="mt-4 grid gap-3 disabled:opacity-60">
                        <div>
                            <label htmlFor="delete-account-password" className="text-xs font-semibold text-ink-950">Current password</label>
                            <input ref={passwordRef} id="delete-account-password" name="password" type="password" autoComplete="current-password" required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} aria-invalid={Boolean(form.errors.password)} aria-describedby={form.errors.password ? 'delete-account-password-error' : undefined} className="mt-1 h-10 w-full rounded-md border border-ink-950/15 bg-white/70 px-3 text-sm outline-none focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/15" />
                            {form.errors.password && <p id="delete-account-password-error" role="alert" className="mt-1 text-xs text-red-700">{form.errors.password}</p>}
                        </div>
                        <label className="flex items-start gap-2 text-xs leading-5 text-ink-950/70"><input name="confirmation" type="checkbox" required checked={form.data.confirmation} onChange={(event) => form.setData('confirmation', event.target.checked)} className="mt-0.5 size-4 shrink-0 accent-brand-coral" />I understand that deleting my account is permanent.</label>
                        {form.errors.confirmation && <p role="alert" className="text-xs text-red-700">{form.errors.confirmation}</p>}
                        <div className="flex flex-wrap items-center gap-3">
                            <button type="submit" className="h-10 rounded-md bg-brand-coral px-4 text-xs font-bold text-ink-950 hover:bg-brand-coral/85 disabled:cursor-wait">{form.processing ? 'Deleting…' : 'Permanently delete account'}</button>
                            <button type="button" onClick={cancel} className="h-10 px-2 text-xs font-semibold text-ink-950/65 hover:text-ink-950">Cancel</button>
                        </div>
                    </fieldset>
                </form>
            </details>
        </section>
    );
}
