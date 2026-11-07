import { Link, useForm } from '@inertiajs/react';

import { Pagination } from './ProfilePageUi';
import { TimeLabel } from './LiteratureDetail/DetailUi';
import { openLoginPanel } from '../Support/loginPanel';
import useFeedbackAction from '../Support/useFeedbackAction';

export default function ListComments({ comments, commenting = {} }) {
    const form = useForm({ body: '' });
    const { processing: deleting, visit } = useFeedbackAction();
    const postFailed = () => {
        form.setError('body', 'Your comment could not be posted. Please try again.');
        return false;
    };
    const submit = (event) => {
        event.preventDefault();
        if (!commenting.store_url || form.processing || deleting) return;
        form.post(commenting.store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
            onNetworkError: postFailed,
            onHttpException: postFailed,
        });
    };

    return (
        <section id="comments" aria-labelledby="list-comments-heading" className="mx-auto mt-9 max-w-4xl scroll-mt-24 border-t border-ink-950/15 pt-5 sm:mt-11">
            <div className="flex items-center justify-between gap-3 border-b border-ink-950/15 pb-3">
                <h2 id="list-comments-heading" className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">{comments.total} {comments.total === 1 ? 'comment' : 'comments'}</h2>
                {comments.total > 0 && <span className="text-[11px] text-ink-950/50">Newest first</span>}
            </div>
            <div className="divide-y divide-ink-950/10">
                {comments.data.map((comment) => (
                    <article key={comment.id} id={`list-comment-${comment.id}`} className="grid min-w-0 gap-2 py-4 sm:grid-cols-[160px_minmax(0,1fr)] sm:gap-5">
                        <div className="flex min-w-0 items-start gap-2.5">
                            <Link href={comment.user.url} tabIndex={-1} aria-hidden="true" className="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-brand-sky/25 text-[10px] font-bold text-ink-950">
                                {comment.user.avatar_url ? <img src={comment.user.avatar_url} alt="" className="size-full object-cover" loading="lazy" /> : comment.user.initials}
                            </Link>
                            <div className="min-w-0">
                                <Link href={comment.user.url} className="block truncate text-xs font-semibold text-ink-950 hover:text-brand-coral focus-visible:underline">{comment.user.name}</Link>
                                <TimeLabel value={comment.created_at} className="mt-0.5 block text-[11px] text-ink-950/50" />
                            </div>
                        </div>
                        <div className="min-w-0">
                            <p className="whitespace-pre-wrap text-sm leading-6 text-ink-950/75 [overflow-wrap:anywhere]">{comment.body}</p>
                            {comment.delete_url && <button type="button" disabled={deleting || form.processing} onClick={() => visit('delete', comment.delete_url)} aria-label={`Delete comment by ${comment.user.name}`} className="mt-1 min-h-8 text-[11px] font-semibold text-ink-950/45 hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:opacity-50">Delete</button>}
                        </div>
                    </article>
                ))}
            </div>
            {comments.data.length === 0 && <p className="py-5 text-xs text-ink-950/55">{comments.total === 0 ? 'No comments yet.' : 'No comments on this page.'}</p>}
            <Pagination paginator={comments} label="List comments pagination" />

            {commenting.store_url ? (
                <form onSubmit={submit} className="mt-5 border-t border-ink-950/10 pt-4">
                    <label htmlFor="list-comment-body" className="mb-2 block text-xs font-semibold text-ink-950">Leave a comment</label>
                    <textarea id="list-comment-body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} rows={3} maxLength={3000} required placeholder="Add a comment…" aria-invalid={Boolean(form.errors.body)} aria-describedby={form.errors.body ? 'list-comment-error' : undefined} className="block min-h-24 w-full resize-y rounded-md border border-ink-950/15 bg-white/50 px-3 py-2 text-sm leading-6 text-ink-950 outline-none placeholder:text-ink-950/40 focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/10" />
                    {form.errors.body && <p id="list-comment-error" role="alert" className="mt-1 text-xs text-brand-coral">{form.errors.body}</p>}
                    <div className="mt-3 flex justify-end"><button type="submit" disabled={form.processing || deleting} className="inline-flex min-h-9 items-center rounded-md bg-ink-950 px-4 text-xs font-semibold text-brand-cream transition-colors hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50">{form.processing ? 'Posting…' : 'Post comment'}</button></div>
                </form>
            ) : (
                <p className="mt-4 border-t border-ink-950/10 pt-4 text-xs text-ink-950/60">
                    {commenting.verification_url
                        ? <Link href={commenting.verification_url} className="font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Verify your email to comment</Link>
                        : !commenting.authenticated
                            ? <button type="button" onClick={openLoginPanel} className="min-h-8 font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Log in to join the conversation</button>
                            : 'Commenting is unavailable for this list.'}
                </p>
            )}
        </section>
    );
}
