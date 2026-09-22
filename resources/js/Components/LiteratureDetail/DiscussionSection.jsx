import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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
            className={`inline-flex items-center gap-1.5 text-xs font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral ${
                discussion.is_liked
                    ? 'text-brand-coral'
                    : 'text-ink-950/50 hover:text-brand-coral'
            }`}
        >
            <span aria-hidden="true">{discussion.is_liked ? '♥' : '♡'}</span>
            <span>{discussion.is_liked ? 'Liked' : 'Like'} · {plural(discussion.likes_count, 'like')}</span>
        </button>
    );
}

function CommentIcon() {
    return (
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-4" aria-hidden="true">
            <path d="M4.25 4.75h11.5v8.5H9l-3.75 2.5v-2.5h-1a1 1 0 0 1-1-1v-6.5a1 1 0 0 1 1-1Z" />
        </svg>
    );
}

function DiscussionThread({ discussion, viewer, reportReasons, reportAction, expanded, onToggle }) {
    const detailsId = `discussion-content-${discussion.id}`;

    return (
        <article
            id={`discussion-${discussion.id}`}
            className="scroll-mt-28 bg-white/25"
            data-discussion-thread
        >
            <div className={`grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 px-4 py-4 transition sm:gap-4 sm:px-5 ${expanded ? 'bg-brand-sky/10' : 'hover:bg-brand-sky/10'}`}>
                <UserAvatar user={discussion.user} className="size-8 sm:size-9" />

                <div className="min-w-0">
                    <button
                        type="button"
                        onClick={onToggle}
                        aria-expanded={expanded}
                        aria-controls={detailsId}
                        className="flex max-w-full items-center gap-2 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral"
                    >
                        <span className="line-clamp-2 text-sm font-bold leading-5 text-ink-950 sm:truncate">{discussion.title}</span>
                        {discussion.contains_spoiler && (
                            <span className="shrink-0 rounded border border-brand-coral/60 px-1.5 py-0.5 text-[0.58rem] font-bold uppercase tracking-wider text-brand-coral">Spoiler</span>
                        )}
                    </button>
                    <div className="mt-1 flex min-w-0 flex-wrap items-center gap-x-1.5 text-[0.68rem] text-ink-950/45">
                        <Link href={discussion.user.url} className="truncate font-semibold transition hover:text-brand-coral">
                            {discussion.user.name}
                        </Link>
                        <span aria-hidden="true">·</span>
                        <TimeLabel value={discussion.created_at} />
                    </div>
                </div>

                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={expanded}
                    aria-controls={detailsId}
                    aria-label={`${expanded ? 'Collapse' : 'Expand'} ${discussion.title}`}
                    className="flex shrink-0 items-center gap-2 text-xs font-semibold text-ink-950/45 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral"
                >
                    <span className="inline-flex items-center gap-1"><CommentIcon /> {discussion.comments_count}</span>
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.7" className={`size-4 transition-transform ${expanded ? 'rotate-90' : ''}`} aria-hidden="true"><path d="m7 4 6 6-6 6" /></svg>
                </button>
            </div>

            {expanded && (
                <div id={detailsId} className="border-t border-ink-950/10 bg-white/20 px-4 py-4 sm:px-5">
                    <div>
                        {discussion.contains_spoiler
                            ? <SpoilerContent body={discussion.body} kind="discussion" className="text-sm leading-6 text-ink-900" />
                            : <p className="whitespace-pre-line text-sm leading-6 text-ink-900">{discussion.body}</p>
                        }
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-3 border-b border-ink-950/10 pb-3">
                        {viewer.authenticated
                            ? <LikeButton discussion={discussion} />
                            : <span className="text-xs text-ink-950/40">{plural(discussion.likes_count, 'like')}</span>
                        }
                        {viewer.authenticated && discussion.can_report && (
                            <ReportForm targetType="discussion" targetId={discussion.id} reasons={reportReasons} action={reportAction} />
                        )}
                    </div>

                    <p className="mt-4 text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/40">
                        {plural(discussion.comments_count, 'Comment')}
                    </p>
                    {discussion.comments.length > 0 ? (
                        <div className="mt-1 grid">
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
                        <p className="mt-3 text-sm text-ink-950/40">No comments yet — be the first to respond.</p>
                    )}
                    {viewer.authenticated && <CommentForm action={discussion.comment_url} />}
                </div>
            )}
        </article>
    );
}

export default function DiscussionSection({ literature, discussions, discussionCount, viewer, routes, reportReasons }) {
    const form = useForm({ title: '', discussion_body: '', discussion_contains_spoiler: false });
    const [composerOpen, setComposerOpen] = useState(false);
    const [expandedDiscussionId, setExpandedDiscussionId] = useState(null);
    const discussionIds = discussions.map((discussion) => discussion.id).join(',');
    const literatureTitle = literature?.title ?? 'this literature';

    useEffect(() => {
        const availableDiscussionIds = new Set(
            discussionIds
                .split(',')
                .filter(Boolean)
                .map(Number),
        );

        const expandDiscussionFromHash = () => {
            const match = window.location.hash.match(/^#discussion-(\d+)$/);
            const discussionId = match ? Number(match[1]) : null;

            if (discussionId && availableDiscussionIds.has(discussionId)) {
                setExpandedDiscussionId(discussionId);
            }
        };

        expandDiscussionFromHash();
        window.addEventListener('hashchange', expandDiscussionFromHash);

        return () => window.removeEventListener('hashchange', expandDiscussionFromHash);
    }, [discussionIds]);

    const submit = (event) => {
        event.preventDefault();
        form.post(routes.discussion_store, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setComposerOpen(false);
            },
        });
    };

    const cancelComposer = () => {
        form.reset();
        form.clearErrors();
        setComposerOpen(false);
    };

    return (
        <section id="discussions" className="scroll-mt-24 border-t border-ink-950/10 bg-white/20">
            <SiteContainer className="py-9 lg:py-10">
                <div className="mx-auto max-w-3xl">
                    <div className="border-b border-ink-950/20 pb-2.5">
                        <SectionHeading>Discussions</SectionHeading>
                        <p className="mt-1 text-xs text-ink-950/45">
                            {plural(discussionCount, 'discussion')} · {literatureTitle}
                            {discussionCount > discussions.length && ` · latest ${discussions.length} shown`}
                        </p>
                    </div>

                    <div id="new-discussion" className="mt-5 scroll-mt-28">
                        {viewer.authenticated ? (
                            composerOpen ? (
                                <form onSubmit={submit} className="rounded-lg border border-ink-950/12 bg-brand-cream/55 p-4 sm:p-5">
                                    <div className="grid grid-cols-[auto_minmax(0,1fr)] items-start gap-3">
                                        {viewer.user && <UserAvatar user={viewer.user} className="size-8 sm:size-9" />}
                                        <div className="grid min-w-0 gap-3">
                                            <label htmlFor="discussion-title" className="sr-only">Discussion title</label>
                                            <input
                                                id="discussion-title"
                                                value={form.data.title}
                                                onChange={(event) => form.setData('title', event.target.value)}
                                                required
                                                minLength="3"
                                                maxLength="150"
                                                autoFocus
                                                placeholder={`Start a focused conversation about ${literatureTitle}...`}
                                                className="w-full border-0 border-b border-ink-950/15 bg-transparent px-1 pb-2 text-sm font-semibold text-ink-950 outline-none placeholder:font-normal placeholder:text-ink-950/40 focus:border-brand-coral"
                                            />
                                            {form.errors.title && <span className="text-xs font-semibold text-red-700">{form.errors.title}</span>}

                                            <label htmlFor="discussion-body" className="sr-only">Opening post</label>
                                            <textarea
                                                id="discussion-body"
                                                value={form.data.discussion_body}
                                                onChange={(event) => form.setData('discussion_body', event.target.value)}
                                                rows="4"
                                                required
                                                minLength="10"
                                                maxLength="5000"
                                                placeholder="Add context so other readers can join the conversation..."
                                                className="w-full resize-y rounded border border-ink-950/15 bg-white/60 px-3 py-2.5 text-sm leading-6 text-ink-950 outline-none transition placeholder:text-ink-950/40 focus:border-brand-coral focus:bg-white/90"
                                            />
                                            {form.errors.discussion_body && <span className="text-xs font-semibold text-red-700">{form.errors.discussion_body}</span>}
                                        </div>
                                    </div>

                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:pl-12">
                                        <label className="flex cursor-pointer items-center gap-2 text-xs text-ink-950/55 transition hover:text-ink-950/80">
                                            <input
                                                type="checkbox"
                                                checked={form.data.discussion_contains_spoiler}
                                                onChange={(event) => form.setData('discussion_contains_spoiler', event.target.checked)}
                                                className="size-3.5 accent-brand-coral"
                                            />
                                            Contains spoilers
                                        </label>
                                        <div className="flex flex-wrap items-center justify-end gap-2">
                                            <button type="button" onClick={cancelComposer} className="px-3 py-2 text-xs font-bold text-ink-950/55 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">Cancel</button>
                                            <button type="submit" disabled={form.processing} className="rounded bg-ink-950 px-4 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral disabled:cursor-wait disabled:opacity-40">
                                                {form.processing ? 'Starting…' : 'Start discussion'}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            ) : (
                                <div className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-lg border border-ink-950/12 bg-brand-cream/45 p-3 transition hover:border-brand-coral/40 hover:bg-brand-cream/65 sm:px-4">
                                    {viewer.user && <UserAvatar user={viewer.user} className="size-8 sm:size-9" />}
                                    <button type="button" onClick={() => setComposerOpen(true)} className="min-w-0 truncate text-left text-sm text-ink-950/45 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                                        Start a focused conversation about {literatureTitle}...
                                    </button>
                                    <button type="button" onClick={() => setComposerOpen(true)} className="rounded bg-ink-950 px-3 py-2 text-[0.65rem] font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">New</button>
                                </div>
                            )
                        ) : (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-ink-950/12 bg-brand-cream/45 px-4 py-3">
                                <p className="text-sm text-ink-950/55">Sign in to start a discussion about {literatureTitle}.</p>
                                <Link href={routes.login} className="rounded bg-ink-950 px-4 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">Log in</Link>
                            </div>
                        )}
                    </div>

                    {discussions.length > 0 ? (
                        <div className="mt-5 divide-y divide-ink-950/10 overflow-hidden rounded-lg border border-ink-950/12 bg-white/25">
                            {discussions.map((discussion) => (
                                <DiscussionThread
                                    key={discussion.id}
                                    discussion={discussion}
                                    viewer={viewer}
                                    reportReasons={reportReasons}
                                    reportAction={routes.report_store}
                                    expanded={expandedDiscussionId === discussion.id}
                                    onToggle={() => setExpandedDiscussionId((currentId) => currentId === discussion.id ? null : discussion.id)}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="mt-5 py-3 text-sm leading-6 text-ink-950/50">
                            <p className="font-semibold text-ink-950/65">No discussions yet.</p>
                            <p>Be the first reader to start a conversation about {literatureTitle}.</p>
                        </div>
                    )}
                </div>
            </SiteContainer>
        </section>
    );
}
