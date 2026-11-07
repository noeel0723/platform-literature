import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import SiteContainer from '../../Components/SiteContainer';
import { LiteratureCover } from '../../Components/ProfilePageUi';
import { ReportForm, SpoilerContent, StarRating, TimeLabel, UserAvatar } from '../../Components/LiteratureDetail/DetailUi';
import ActivityLiteratureActions from '../../Components/ActivityLiteratureActions';
import QuickLogDialog from '../../Components/QuickLogDialog';
import { actionFeedback, requestAction } from '../../Support/actionFeedback';

export default function ActivityShow({ activity, literature, preview, routes, reportReasons }) {
    const active = useRef(false);
    const requestRef = useRef(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const [quickLog, setQuickLog] = useState(null);

    useEffect(() => () => requestRef.current?.abort(), [activity.id]);

    const mutate = async (url, method, values = {}, leavesActivity = false) => {
        if (active.current) return;
        const finish = actionFeedback.begin();
        if (!finish) return;
        active.current = true;
        setProcessing(true);
        setError('');
        const request = new AbortController();
        requestRef.current = request;
        const done = () => { active.current = false; setProcessing(false); finish(); };
        try {
            const data = await requestAction(url, method, values, { csrfToken: preview.csrf_token, signal: request.signal });
            actionFeedback.publish(data.feedback);
            if (leavesActivity) router.visit(routes.feed, { onFinish: done });
            else router.reload({ only: ['activity', 'preview'], preserveScroll: true, onFinish: done });
        } catch (failure) {
            if (!request.signal.aborted) { setError(failure.message); actionFeedback.error(failure.message); }
            done();
        }
    };
    const reading = (status) => {
        const remove = preview.viewer.reading_status === status;
        const leavesActivity = activity.is_owner && (
            (remove && ['completed', 'started_reading', 'added_to_readlist'].includes(activity.type))
            || (activity.type === 'completed' && status !== 'completed')
        );
        mutate(remove ? preview.routes.reading_destroy : preview.routes.reading_update, remove ? 'DELETE' : 'PUT', remove ? {} : { status }, leavesActivity);
    };
    const toggleList = (list) => mutate(list.contains ? list.destroy_url : list.store_url, list.contains ? 'DELETE' : 'POST', list.contains ? {} : { literature_id: preview.literature.id });
    const toggleLike = () => mutate(activity.review.is_liked ? activity.review.unlike_url : activity.review.like_url, activity.review.is_liked ? 'DELETE' : 'POST');

    return (
        <>
            <Head title={`${activity.label}: ${literature.title}`} />
            <SiteContainer as="main" className="max-w-6xl py-7 sm:py-9">
                <Link href={routes.feed} className="text-[11px] font-semibold text-ink-950/50 hover:text-brand-coral">← Activity feed</Link>
                <div className="mt-5 grid min-w-0 gap-6 md:grid-cols-[130px_minmax(0,1fr)] lg:grid-cols-[150px_minmax(0,1fr)_250px] lg:gap-7">
                    <aside className="min-w-0">
                        <Link href={literature.url} title={`View ${literature.title}`} className="mx-auto block max-w-28 rounded-sm focus-visible:outline-2 focus-visible:outline-brand-coral md:max-w-none"><LiteratureCover literature={literature} className="aspect-[2/3] rounded-sm" loading="eager" /></Link>
                        {preview.routes.where_to_read && <Link href={preview.routes.where_to_read} className="mt-3 block rounded-md border border-ink-950/15 px-2 py-2 text-center text-xs font-semibold text-ink-950 hover:text-brand-coral">Where to read</Link>}
                    </aside>
                    <article className="min-w-0" aria-labelledby="activity-title">
                        <header>
                            <div className="flex items-center gap-2 border-b border-ink-950/15 pb-3 text-xs text-ink-950/60"><UserAvatar user={activity.user} className="size-7" /><p>{activity.label} by <Link href={activity.user.url} className="font-bold text-ink-950 hover:text-brand-coral">{activity.user.name}</Link></p></div>
                            <h1 id="activity-title" className="mt-4 font-serif text-2xl font-bold leading-tight text-ink-950 [overflow-wrap:anywhere]"><Link href={literature.url} className="hover:text-brand-coral">{literature.title}</Link>{literature.year && <span className="ml-2 text-base font-normal text-ink-950/50">{literature.year}</span>}</h1>
                            <p className="mt-1 text-xs text-ink-950/50">{literature.type_label} · {literature.author}</p>
                            {activity.rating !== null && <div className="mt-3 flex items-center gap-2"><StarRating rating={activity.rating} size="text-lg" /><span className="text-xs font-semibold text-ink-950/55">{Number(activity.rating).toFixed(1)} / 5</span></div>}
                            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-950/50">
                                {activity.completed_at && <p>Completed on <TimeLabel value={activity.completed_at} dateOnly /></p>}
                                <p>{activity.label === 'Review' ? 'Reviewed' : 'Activity'} <TimeLabel value={activity.occurred_at} /></p>
                            </div>
                        </header>
                        {activity.discussion_title && <h2 className="mt-5 text-base font-semibold text-ink-950">{activity.discussion_title}</h2>}
                        {activity.body ? <div className="mt-5 [overflow-wrap:anywhere]">{activity.contains_spoiler ? <SpoilerContent body={activity.body} kind={activity.label.toLowerCase()} className="font-serif text-base leading-7 text-ink-950/85" /> : <p className="whitespace-pre-line font-serif text-base leading-7 text-ink-950/85">{activity.body}</p>}</div> : <p className="mt-5 text-sm leading-6 text-ink-950/60">{activity.type === 'completed' ? 'Marked as completed.' : activity.type === 'added_to_readlist' ? 'Saved to Readlist.' : activity.type === 'started_reading' ? 'Started reading this literature.' : 'No written review for this activity.'}</p>}
                        <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-ink-950/10 pt-3">
                            {activity.review && <button type="button" disabled={processing} onClick={toggleLike} aria-pressed={activity.review.is_liked} className="min-h-9 text-xs font-semibold text-ink-950/65 hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50"><span className={activity.review.is_liked ? 'text-brand-coral' : ''}>{activity.review.is_liked ? '♥ Liked' : '♡ Like review'}</span><span className="ml-2 font-normal text-ink-950/45">{activity.review.likes_count}</span></button>}
                            {activity.report && <ReportForm targetType={activity.report.type} targetId={activity.report.id} reasons={reportReasons} action={routes.report} />}
                            {activity.discussion_url && <Link href={activity.discussion_url} className="min-h-9 py-2 text-xs font-semibold text-brand-coral">Open conversation →</Link>}
                        </div>
                        {error && <p role="alert" className="mt-3 text-xs text-red-700">{error}</p>}
                    </article>
                    <div className="min-w-0 md:col-start-2 lg:col-start-3 lg:row-start-1"><ActivityLiteratureActions preview={preview} routes={routes} literature={literature} processing={processing} onReading={reading} onReview={(rating = null) => setQuickLog({ rating })} onList={toggleList} /></div>
                </div>
            </SiteContainer>
            <QuickLogDialog open={quickLog !== null} onClose={() => setQuickLog(null)} searchUrl={preview.routes.quick_log_search} initialLiterature={preview.literature} initialRating={quickLog?.rating ?? null} />
        </>
    );
}
