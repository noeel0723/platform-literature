import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import SiteContainer from '../SiteContainer';
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
        <form onSubmit={submit} className={`grid gap-2.5 ${compact ? 'mt-3' : 'mt-4 border-t border-ink-950/10 pt-4'}`}>
            {!compact && <p className="text-xs font-bold uppercase tracking-wider text-ink-950/50">{label}</p>}
            <textarea
                value={form.data.comment_body}
                onChange={(event) => form.setData('comment_body', event.target.value)}
                rows={compact ? 2 : 3}
                required
                minLength="2"
                maxLength="3000"
                placeholder={compact ? 'Write a reply…' : 'Share your thoughts on this discussion…'}
                className={`w-full resize-y border border-ink-950/15 bg-white/70 px-3 py-2.5 outline-none transition focus:border-brand-coral focus:bg-white/90 ${compact ? 'text-xs leading-5' : 'text-sm leading-6'}`}
            />
            {form.errors.comment_body && <p className="text-xs font-semibold text-red-700">{form.errors.comment_body}</p>}
            {form.errors.parent_id && <p className="text-xs font-semibold text-red-700">{form.errors.parent_id}</p>}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <label className={`${compact ? 'text-[11px]' : 'text-xs'} flex cursor-pointer items-center gap-2 text-ink-950/55 transition hover:text-ink-950/80`}>
                    <input
                        type="checkbox"
                        checked={form.data.comment_contains_spoiler}
                        onChange={(event) => form.setData('comment_contains_spoiler', event.target.checked)}
                        className="size-3.5 accent-brand-coral"
                    />
                    Contains spoilers
                </label>
                <button
                    disabled={form.processing}
                    className="w-fit bg-ink-950 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral disabled:opacity-40"
                >
                    {form.processing ? 'Publishing…' : `Publish ${compact ? 'reply' : 'comment'}`}
                </button>
            </div>
        </form>
    );
}

function Reply({ reply, viewer, reportReasons, reportAction }) {
    return (
        <div className="mt-3 flex gap-3 border-l-2 border-ink-950/8 pl-3">
            <UserAvatar user={reply.user} className="mt-0.5 size-5 shrink-0" tone="bg-brand-sky/60 text-ink-950" />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <Link href={reply.user.url} className="text-xs font-bold text-ink-950 transition hover:text-brand-coral">
                        {reply.user.name}
                    </Link>
                    <TimeLabel value={reply.created_at} className="text-[10px] text-ink-950/35" />
                </div>
                <div className="mt-1">
                    {reply.contains_spoiler
                        ? <SpoilerContent body={reply.body} kind="reply" className="text-xs leading-5 text-ink-900" />
                        : <p className="whitespace-pre-line text-xs leading-5 text-ink-900">{reply.body}</p>
                    }
                </div>
                {viewer.authenticated && reply.can_report && (
                    <div className="mt-1.5">
                        <ReportForm targetType="comment" targetId={reply.id} reasons={reportReasons} action={reportAction} label="Report reply" />
                    </div>
                )}
            </div>
        </div>
    );
}

function Comment({ comment, discussion, viewer, reportReasons, reportAction }) {
    const [showReply, setShowReply] = useState(false);

    return (
        <div className="grid gap-3 border-b border-ink-950/6 py-4 last:border-0 sm:grid-cols-[auto_minmax(0,1fr)]" data-discussion-comment>
            <UserAvatar user={comment.user} className="size-7 shrink-0" tone="bg-brand-sky/50 text-ink-950" />
            <div className="min-w-0">
                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <Link href={comment.user.url} className="text-xs font-bold text-ink-950 transition hover:text-brand-coral">
                        {comment.user.name}
                    </Link>
                    <TimeLabel value={comment.created_at} className="text-[10px] text-ink-950/35" />
                </div>
                <div className="mt-1.5">
                    {comment.contains_spoiler
                        ? <SpoilerContent body={comment.body} kind="comment" className="text-sm leading-6 text-ink-900" />
                        : <p className="whitespace-pre-line text-sm leading-6 text-ink-900">{comment.body}</p>
                    }
                </div>
                <div className="mt-2 flex items-center gap-3">
                    {viewer.authenticated && (
                        <button
                            type="button"
                            onClick={() => setShowReply((v) => !v)}
                            className="text-[11px] font-bold text-ink-950/45 transition hover:text-brand-coral"
                        >
                            {showReply ? 'Cancel' : 'Reply'}
                        </button>
                    )}
                    {viewer.authenticated && comment.can_report && (
                        <ReportForm targetType="comment" targetId={comment.id} reasons={reportReasons} action={reportAction} />
                    )}
                </div>
                {comment.replies.length > 0 && (
                    <div className="mt-3 grid gap-0">
                        {comment.replies.map((reply) => (
                            <Reply key={reply.id} reply={reply} viewer={viewer} reportReasons={reportReasons} reportAction={reportAction} />
                        ))}
                    </div>
                )}
                {showReply && (
                    <CommentForm action={discussion.comment_url} parentId={comment.id} compact label={`Reply to ${comment.user.name}`} />
                )}
            </div>
        </div>
    );
}

function LikeButton({ discussion }) {
    const toggleLike = () => {
        const options = { preserveScroll: true };
        if (discussion.is_liked) router.delete(discussion.unlike_url, options);
        else router.post(discussion.like_url, {}, options);
    };

    return (
        <button
            type="button"
            onClick={toggleLike}
            aria-pressed={discussion.is_liked}
            className={`inline-flex items-center gap-1.5 border px-3 py-1 text-xs font-bold transition ${
                discussion.is_liked
                    ? 'border-brand-coral bg-brand-coral text-brand-cream'
                    : 'border-ink-950/15 text-ink-950/60 hover:border-brand-coral hover:text-ink-950'
            }`}
        >
            <span aria-hidden="true">{discussion.is_liked ? '♥' : '♡'}</span>
            <span>{discussion.likes_count} {discussion.likes_count === 1 ? 'like' : 'likes'}</span>
        </button>
    );
}

function DiscussionThread({ discussion, viewer, reportReasons, reportAction }) {
    const [open, setOpen] = useState(true);

    return (
        <article
            id={`discussion-${discussion.id}`}
            className="scroll-mt-28 border border-ink-950/8 bg-white/30 transition hover:bg-white/45"
            data-discussion-thread
        >
            {/* Thread header */}
            <div className="grid gap-4 p-5 sm:grid-cols-[auto_minmax(0,1fr)]">
                <div className="flex items-start gap-3 sm:block sm:text-center">
                    <UserAvatar user={discussion.user} className="size-9 sm:mx-auto sm:mb-2" />
                    <div className="sm:hidden">
                        <Link href={discussion.user.url} className="block text-sm font-bold text-ink-950 transition hover:text-brand-coral">
                            {discussion.user.name}
                        </Link>
                        <TimeLabel value={discussion.created_at} className="text-[10px] text-ink-950/40" />
                    </div>
                    <div className="hidden sm:block">
                        <Link href={discussion.user.url} className="block truncate text-xs font-bold text-ink-950 transition hover:text-brand-coral">
                            {discussion.user.name}
                        </Link>
                        <TimeLabel value={discussion.created_at} className="mt-0.5 block text-[10px] text-ink-950/40" />
                    </div>
                </div>

                <div className="min-w-0">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <h3 className="border-l-2 border-brand-coral pl-3 font-serif text-lg font-bold leading-snug text-ink-950">
                            {discussion.title}
                        </h3>
                        <button
                            type="button"
                            onClick={() => setOpen((v) => !v)}
                            className="shrink-0 text-[10px] font-bold uppercase tracking-wider text-ink-950/40 transition hover:text-ink-950"
                            aria-expanded={open}
                        >
                            {open ? 'Collapse' : 'Expand'} · {plural(discussion.comments_count, 'comment')}
                        </button>
                    </div>

                    {open && (
                        <div className="mt-3">
                            {discussion.contains_spoiler
                                ? <SpoilerContent body={discussion.body} kind="discussion" className="text-sm leading-6 text-ink-900" />
                                : <p className="whitespace-pre-line text-sm leading-6 text-ink-900">{discussion.body}</p>
                            }
                        </div>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-ink-950/8 pt-3">
                        {viewer.authenticated
                            ? <LikeButton discussion={discussion} />
                            : <span className="text-xs text-ink-950/40">{plural(discussion.likes_count, 'like')}</span>
                        }
                        {viewer.authenticated && discussion.can_report && (
                            <ReportForm targetType="discussion" targetId={discussion.id} reasons={reportReasons} action={reportAction} />
                        )}
                    </div>
                </div>
            </div>

            {/* Comments */}
            {open && (
                <div className="border-t border-ink-950/8 bg-white/20 px-5 pb-5 pt-4">
                    <p className="mb-3 text-[10px] font-bold uppercase tracking-wider text-ink-950/40">
                        {plural(discussion.comments_count, 'Comment')}
                    </p>
                    {discussion.comments.length > 0 ? (
                        <div className="grid">
                            {discussion.comments.map((comment) => (
                                <Comment
                                    key={comment.id}
                                    comment={comment}
                                    discussion={discussion}
                                    viewer={viewer}
                                    reportReasons={reportReasons}
                                    reportAction={reportAction}
                                />
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-ink-950/40">No comments yet — be the first to respond.</p>
                    )}
                    {viewer.authenticated && <CommentForm action={discussion.comment_url} />}
                </div>
            )}
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
            <SiteContainer className="py-9 lg:py-12">

                {/* Section header */}
                <div className="flex flex-col gap-2 border-b border-ink-950/15 pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <SectionHeading>Discussions</SectionHeading>
                        <p className="mt-1 text-xs text-ink-950/45">
                            {plural(discussionCount, 'discussion')}
                            {discussionCount > discussions.length && ` · showing the latest ${discussions.length}`}
                        </p>
                    </div>
                    {viewer.authenticated && (
                        <a href="#new-discussion" className="text-xs font-bold text-ink-950/50 transition hover:text-brand-coral">
                            + Start a discussion
                        </a>
                    )}
                </div>

                <div className="mt-6 grid gap-8 lg:grid-cols-[260px_minmax(0,1fr)]">

                    {/* Sidebar: new discussion form or guest CTA */}
                    <aside>
                        {viewer.authenticated ? (
                            <form
                                id="new-discussion"
                                onSubmit={submit}
                                className="grid gap-5 border border-ink-950/12 bg-brand-cream/60 p-5 lg:sticky lg:top-24"
                            >
                                <div className="border-b border-ink-950/10 pb-3">
                                    <p className="text-xs font-bold uppercase tracking-wider text-ink-950/50">New Discussion</p>
                                    <p className="mt-1 text-xs text-ink-950/40">Start a focused conversation about this work.</p>
                                </div>

                                <div className="grid gap-1.5">
                                    <label className="text-xs font-bold uppercase tracking-wider text-ink-950/60">
                                        Title
                                    </label>
                                    <input
                                        value={form.data.title}
                                        onChange={(event) => form.setData('title', event.target.value)}
                                        required
                                        minLength="3"
                                        maxLength="150"
                                        placeholder="What would you like to discuss?"
                                        className="w-full border border-ink-950/15 bg-white/70 px-3 py-2.5 text-sm outline-none transition focus:border-brand-coral focus:bg-white/90"
                                    />
                                    {form.errors.title && <span className="text-xs font-semibold text-red-700">{form.errors.title}</span>}
                                </div>

                                <div className="grid gap-1.5">
                                    <label className="text-xs font-bold uppercase tracking-wider text-ink-950/60">
                                        Opening post
                                    </label>
                                    <textarea
                                        value={form.data.discussion_body}
                                        onChange={(event) => form.setData('discussion_body', event.target.value)}
                                        rows="5"
                                        required
                                        minLength="10"
                                        maxLength="5000"
                                        placeholder="Add context so other readers can join…"
                                        className="w-full resize-y border border-ink-950/15 bg-white/70 px-3 py-2.5 text-sm leading-6 outline-none transition focus:border-brand-coral focus:bg-white/90"
                                    />
                                    {form.errors.discussion_body && <span className="text-xs font-semibold text-red-700">{form.errors.discussion_body}</span>}
                                </div>

                                <label className="flex cursor-pointer items-start gap-2 text-xs leading-5 text-ink-950/55 transition hover:text-ink-950/80">
                                    <input
                                        type="checkbox"
                                        checked={form.data.discussion_contains_spoiler}
                                        onChange={(event) => form.setData('discussion_contains_spoiler', event.target.checked)}
                                        className="mt-0.5 size-3.5 accent-brand-coral"
                                    />
                                    This discussion contains spoilers
                                </label>

                                <button
                                    disabled={form.processing}
                                    className="bg-ink-950 px-4 py-2.5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral disabled:opacity-40"
                                >
                                    {form.processing ? 'Starting…' : 'Start discussion'}
                                </button>
                            </form>
                        ) : (
                            <div className="border border-ink-950/10 bg-brand-cream/70 p-6">
                                <p className="font-serif text-xl font-bold leading-snug text-ink-950">Join the discussion</p>
                                <p className="mt-2.5 text-sm leading-6 text-ink-950/60">
                                    Log in to start a discussion, leave a comment, or reply to another reader.
                                </p>
                                <Link
                                    href={routes.login}
                                    className="mt-5 inline-block border border-ink-950 bg-ink-950 px-5 py-2.5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:border-brand-coral"
                                >
                                    Log in to participate
                                </Link>
                            </div>
                        )}
                    </aside>

                    {/* Thread list */}
                    <div className="grid content-start gap-4">
                        {discussions.length > 0 ? (
                            discussions.map((discussion) => (
                                <DiscussionThread
                                    key={discussion.id}
                                    discussion={discussion}
                                    viewer={viewer}
                                    reportReasons={reportReasons}
                                    reportAction={routes.report_store}
                                />
                            ))
                        ) : (
                            <div className="flex flex-col items-start gap-3 border border-dashed border-ink-950/15 bg-white/20 px-7 py-10">
                                <p className="font-serif text-xl font-bold text-ink-950/70">No discussions yet</p>
                                <p className="text-sm leading-6 text-ink-950/45">
                                    Be the first to spark a conversation about <em>{literature?.title ?? 'this work'}</em>.
                                </p>
                                {viewer.authenticated && (
                                    <a
                                        href="#new-discussion"
                                        className="mt-1 bg-ink-950 px-4 py-2 text-sm font-bold text-brand-cream transition hover:bg-brand-coral"
                                    >
                                        Start the first discussion
                                    </a>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </SiteContainer>
        </section>
    );
}
