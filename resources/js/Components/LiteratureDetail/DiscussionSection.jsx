import { Link, router, useForm } from '@inertiajs/react';

import { ReportForm, SectionHeading, SpoilerContent, TimeLabel, UserAvatar, plural } from './DetailUi';

function CommentForm({ action, parentId = null, compact = false, label = 'Add a comment' }) {
    const form = useForm({
        parent_id: parentId,
        comment_body: '',
        comment_contains_spoiler: false,
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(action, {
            preserveScroll: true,
            onSuccess: () => form.reset('comment_body', 'comment_contains_spoiler'),
        });
    };

    return (
        <form onSubmit={submit} className={`grid gap-2 ${compact ? 'mt-2' : 'mt-3 border-t border-ink-950/10 pt-3'}`}>
            <label className={compact ? 'sr-only' : 'text-xs font-bold text-ink-950'}>{label}</label>
            <textarea
                value={form.data.comment_body}
                onChange={(event) => form.setData('comment_body', event.target.value)}
                rows="2"
                required
                minLength="2"
                maxLength="3000"
                placeholder={compact ? 'Write a reply...' : 'Add to this discussion...'}
                className={`w-full resize-y border border-ink-950/20 bg-white/60 px-3 py-2 outline-none focus:border-brand-coral ${compact ? 'text-xs leading-5' : 'text-sm leading-6'}`}
            />
            {form.errors.comment_body && <p className="text-xs font-semibold text-red-700">{form.errors.comment_body}</p>}
            {form.errors.parent_id && <p className="text-xs font-semibold text-red-700">{form.errors.parent_id}</p>}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <label className={`${compact ? 'text-[11px]' : 'text-xs'} flex items-center gap-2 text-ink-950/60`}>
                    <input type="checkbox" checked={form.data.comment_contains_spoiler} onChange={(event) => form.setData('comment_contains_spoiler', event.target.checked)} className="size-3.5 accent-brand-coral" />
                    This {compact ? 'reply' : 'comment'} contains spoilers
                </label>
                <button disabled={form.processing} className="w-fit bg-ink-950 px-3 py-1.5 text-xs font-bold text-brand-cream transition hover:bg-brand-coral disabled:opacity-50">Publish {compact ? 'reply' : 'comment'}</button>
            </div>
        </form>
    );
}

function Reply({ reply, viewer, reportReasons, reportAction }) {
    return (
        <div className="mt-3 grid gap-1.5 border-l border-ink-950/12 pl-3 sm:grid-cols-[100px_minmax(0,1fr)] sm:gap-3">
            <div>
                <Link href={reply.user.url} className="truncate text-xs font-bold text-ink-950 transition hover:text-brand-coral">{reply.user.name}</Link>
                <TimeLabel value={reply.created_at} className="block text-[10px] leading-4 text-ink-950/40" />
            </div>
            <div>
                {reply.contains_spoiler ? <SpoilerContent body={reply.body} kind="reply" className="mt-1 text-xs leading-5 text-ink-900" /> : <p className="whitespace-pre-line text-xs leading-5 text-ink-900">{reply.body}</p>}
                {viewer.authenticated && reply.can_report && <div className="mt-1"><ReportForm targetType="comment" targetId={reply.id} reasons={reportReasons} action={reportAction} label="Report reply" /></div>}
            </div>
        </div>
    );
}

function Comment({ comment, discussion, viewer, reportReasons, reportAction }) {
    return (
        <div className="grid gap-2 border-b border-ink-950/8 py-3 sm:grid-cols-[120px_minmax(0,1fr)] sm:gap-4" data-discussion-comment>
            <div className="flex items-start gap-2">
                <UserAvatar user={comment.user} className="size-7" tone="bg-brand-sky text-ink-950" />
                <div className="min-w-0">
                    <Link href={comment.user.url} className="block truncate text-xs font-bold text-ink-950 transition hover:text-brand-coral">{comment.user.name}</Link>
                    <TimeLabel value={comment.created_at} className="mt-0.5 block text-[10px] leading-4 text-ink-950/40" />
                </div>
            </div>
            <div className="min-w-0">
                {comment.contains_spoiler ? <SpoilerContent body={comment.body} kind="comment" className="mt-1 text-sm leading-6 text-ink-900" /> : <p className="whitespace-pre-line text-sm leading-6 text-ink-900">{comment.body}</p>}
                {viewer.authenticated && comment.can_report && <div className="mt-1.5"><ReportForm targetType="comment" targetId={comment.id} reasons={reportReasons} action={reportAction} /></div>}
                {comment.replies.map((reply) => <Reply key={reply.id} reply={reply} viewer={viewer} reportReasons={reportReasons} reportAction={reportAction} />)}
                {viewer.authenticated && (
                    <details className="mt-2">
                        <summary className="w-fit cursor-pointer text-xs font-bold text-ink-950 hover:text-brand-coral">Reply</summary>
                        <CommentForm action={discussion.comment_url} parentId={comment.id} compact label={`Reply to ${comment.user.name}`} />
                    </details>
                )}
            </div>
        </div>
    );
}

function DiscussionThread({ discussion, viewer, reportReasons, reportAction }) {
    const toggleLike = () => {
        const options = { preserveScroll: true };
        if (discussion.is_liked) router.delete(discussion.unlike_url, options);
        else router.post(discussion.like_url, {}, options);
    };

    return (
        <article id={`discussion-${discussion.id}`} className="scroll-mt-28 border-b border-ink-950/10 py-5 first:border-t" data-discussion-thread>
            <div className="grid gap-3 sm:grid-cols-[140px_minmax(0,1fr)] sm:gap-5">
                <div className="flex items-start gap-2.5 sm:block">
                    <UserAvatar user={discussion.user} className="size-8 sm:mb-2" />
                    <div>
                        <Link href={discussion.user.url} className="text-sm font-bold text-ink-950 transition hover:text-brand-coral">{discussion.user.name}</Link>
                        <TimeLabel value={discussion.created_at} className="mt-0.5 block text-[11px] text-ink-950/40" />
                    </div>
                </div>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <h3 className="font-serif text-xl font-bold leading-tight text-ink-950">{discussion.title}</h3>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-ink-950/40">{plural(discussion.comments_count, 'comment')}</span>
                    </div>
                    <div className="mt-2">
                        {discussion.contains_spoiler ? <SpoilerContent body={discussion.body} kind="discussion" className="mt-2 text-sm leading-6 text-ink-900" /> : <p className="whitespace-pre-line text-sm leading-6 text-ink-900">{discussion.body}</p>}
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-ink-950/8 pt-2.5">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-ink-950/40">{plural(discussion.likes_count, 'like')}</span>
                        {viewer.authenticated && (
                            <>
                                <button type="button" onClick={toggleLike} className={`border px-2 py-1 text-[10px] font-bold uppercase tracking-wider transition ${discussion.is_liked ? 'border-brand-coral bg-brand-coral text-brand-cream' : 'border-ink-950/15 text-ink-950 hover:border-brand-coral'}`} aria-pressed={discussion.is_liked}>{discussion.is_liked ? 'Liked' : 'Like'}</button>
                                {discussion.can_report && <ReportForm targetType="discussion" targetId={discussion.id} reasons={reportReasons} action={reportAction} />}
                            </>
                        )}
                    </div>
                </div>
            </div>
            <div className="mt-5 border-t border-ink-950/10 pt-4 sm:ml-[160px]">
                <SectionHeading as="h4">Comments</SectionHeading>
                <div className="mt-2 grid">
                    {discussion.comments.map((comment) => <Comment key={comment.id} comment={comment} discussion={discussion} viewer={viewer} reportReasons={reportReasons} reportAction={reportAction} />)}
                </div>
                {viewer.authenticated && <CommentForm action={discussion.comment_url} />}
            </div>
        </article>
    );
}

export default function DiscussionSection({ literature, discussions, discussionCount, viewer, routes, reportReasons }) {
    const form = useForm({ title: '', discussion_body: '', discussion_contains_spoiler: false });
    const submit = (event) => {
        event.preventDefault();
        form.post(routes.discussion_store, {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <section id="discussions" className="scroll-mt-24 border-t border-ink-950/10 bg-white/20">
            <div className="mx-auto max-w-7xl px-5 py-9 sm:px-8 lg:px-10 lg:py-12">
                <div className="flex flex-col gap-2 border-b border-ink-950/15 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <SectionHeading>Discussions</SectionHeading>
                    <div className="text-left sm:text-right">
                        <p className="text-xs font-bold uppercase tracking-wider text-ink-950/50">{plural(discussionCount, 'discussion')}</p>
                        {discussionCount > discussions.length && <p className="mt-1 text-xs text-ink-950/45">Showing the latest {discussions.length}</p>}
                    </div>
                </div>
                <div className="mt-5 grid gap-7 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <div>
                        {viewer.authenticated ? (
                            <form onSubmit={submit} className="grid gap-4 border border-ink-950/12 bg-brand-cream/55 p-4 lg:sticky lg:top-24">
                                <label className="text-sm font-bold text-ink-950">Discussion title
                                    <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} required minLength="3" maxLength="150" placeholder="What would you like to discuss?" className="mt-1.5 w-full border border-ink-950/20 bg-white/60 px-3 py-2.5 text-sm font-normal outline-none focus:border-brand-coral" />
                                    {form.errors.title && <span className="mt-2 block text-sm font-semibold text-red-700">{form.errors.title}</span>}
                                </label>
                                <label className="text-sm font-bold text-ink-950">Opening post
                                    <textarea value={form.data.discussion_body} onChange={(event) => form.setData('discussion_body', event.target.value)} rows="5" required minLength="10" maxLength="5000" placeholder="Add context so other readers can join..." className="mt-1.5 w-full resize-y border border-ink-950/20 bg-white/60 px-3 py-2.5 text-sm font-normal leading-6 outline-none focus:border-brand-coral" />
                                    {form.errors.discussion_body && <span className="mt-2 block text-sm font-semibold text-red-700">{form.errors.discussion_body}</span>}
                                </label>
                                <label className="flex items-start gap-2 text-xs leading-5 text-ink-950/60"><input type="checkbox" checked={form.data.discussion_contains_spoiler} onChange={(event) => form.setData('discussion_contains_spoiler', event.target.checked)} className="mt-0.5 size-3.5 accent-brand-coral" />This discussion contains spoilers.</label>
                                <button disabled={form.processing} className="bg-ink-950 px-4 py-2.5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral disabled:opacity-50">Start discussion</button>
                            </form>
                        ) : (
                            <div className="border border-ink-950/15 bg-brand-cream/70 p-7">
                                <p className="font-serif text-2xl font-bold text-ink-950">Join the discussion</p>
                                <p className="mt-3 leading-7 text-ink-950/65">Log in to start a discussion, leave a comment, or reply to another reader.</p>
                                <Link href={routes.login} className="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral">Log in</Link>
                            </div>
                        )}
                    </div>
                    <div className="grid content-start">
                        {discussions.length > 0 ? discussions.map((discussion) => <DiscussionThread key={discussion.id} discussion={discussion} viewer={viewer} reportReasons={reportReasons} reportAction={routes.report_store} />) : <div className="border border-dashed border-ink-950/20 p-7 text-ink-950/60">No discussions yet. Start a focused conversation about this work.</div>}
                    </div>
                </div>
            </div>
        </section>
    );
}
