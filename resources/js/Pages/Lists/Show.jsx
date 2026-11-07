import { Head, Link, usePage } from '@inertiajs/react';

import { LiteratureCover, Pagination } from '../../Components/ProfilePageUi';
import { TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import SiteContainer from '../../Components/SiteContainer';
import EmptyState from '../../Components/EmptyState';
import ListComments from '../../Components/ListComments';
import ListBanner from '../../Components/ListBanner';
import ListActions from '../../Components/ListActions';
import useFeedbackAction from '../../Support/useFeedbackAction';

export default function ShowList({ list, canEdit, successMessage, comments, commenting }) {
    const { actionFeedback } = usePage().props;
    const { processing, visit } = useFeedbackAction();
    const move = (index, direction) => {
        const items = [...list.items.data];
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
        visit('patch', list.reorder_url, { item_ids: items.map((item) => item.id) });
    };
    const destroyList = () => {
        if (!processing && window.confirm('Delete this list?')) visit('delete', list.delete_url);
    };
    const like = () => visit(list.is_liked ? 'delete' : 'post', list.is_liked ? list.unlike_url : list.like_url);

    return (
        <>
            <Head title={list.title} />
            <ListBanner literature={list.hero} />
            {successMessage && !actionFeedback && <div role="status" className="relative border-b border-brand-sky/50 bg-brand-sky/20 px-5 py-3 text-center text-sm font-semibold text-ink-950">{successMessage}</div>}
            <SiteContainer as="main" className={`relative z-10 max-w-6xl pb-10 ${list.hero ? '-mt-12' : 'pt-7 lg:pt-10'}`}>
                <div className="grid min-w-0 gap-x-8 gap-y-6 lg:grid-cols-[minmax(0,1fr)_240px]">
                    <header className="min-w-0 lg:col-start-1">
                        <div className="flex items-center gap-2.5 border-b border-ink-950/15 pb-3 text-sm text-ink-950/60">
                            <Link href={list.owner.url} className="grid size-8 shrink-0 place-items-center overflow-hidden rounded-full bg-brand-sky/30 text-[10px] font-bold text-ink-950" aria-label={`Profile of ${list.owner.name}`}>
                                {list.owner.avatar_url ? <img src={list.owner.avatar_url} alt="" className="size-full object-cover" /> : list.owner.initials}
                            </Link>
                            <p className="min-w-0">List by <Link href={list.owner.url} className="font-semibold text-ink-950 hover:text-brand-coral">{list.owner.name}</Link>{list.is_official && <span className="ml-2 inline-block rounded bg-brand-sky/25 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-ink-950">Official</span>}</p>
                        </div>
                        <div className="mt-3 flex flex-wrap justify-between gap-x-4 gap-y-1 text-[11px] text-ink-950/55">
                            {list.updated_at && <p>Updated <TimeLabel value={list.updated_at} /></p>}
                            <p>{list.items.total} literature · {list.is_private ? 'Private' : 'Public'} · {list.is_ranked ? 'Ranked' : 'Unranked'}</p>
                        </div>
                        <h1 className="mt-4 font-serif text-2xl font-bold leading-tight text-ink-950 [overflow-wrap:anywhere] sm:text-3xl">{list.title}</h1>
                        {list.description && <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-ink-950/70 [overflow-wrap:anywhere]">{list.description}</p>}
                    </header>
                    <div className="lg:col-start-2 lg:row-start-1 lg:row-span-3">
                        <ListActions list={list} canEdit={canEdit} commenting={commenting} commentsCount={comments?.total ?? 0} processing={processing} onLike={like} onDelete={destroyList} />
                    </div>
                    <section aria-label="Literature in list order" className="min-w-0 lg:col-start-1">
                        {list.items.data.length === 0 ? <EmptyState title="This list is empty." description={canEdit ? 'Add literature from the list editor or a literature detail page.' : 'Titles added by the list owner will appear here.'} action={canEdit ? { href: list.edit_url, label: 'Add literature' } : null} /> : (
                            <div className="grid grid-cols-2 gap-x-3 gap-y-5 min-[420px]:grid-cols-3 sm:grid-cols-4 lg:grid-cols-5">
                                {list.items.data.map((item, index) => item.literature && (
                                    <article key={item.id} className="min-w-0">
                                        <Link href={item.literature.url} title={item.literature.title} className="block rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral"><LiteratureCover literature={item.literature} className="aspect-[2/3] rounded-sm transition-colors hover:border-brand-coral" /></Link>
                                        {(list.is_ranked || item.completed) && <div className="mt-1.5 flex min-h-5 items-center justify-center gap-1.5 text-xs text-ink-950/65">
                                            {list.is_ranked && <span aria-label={`Position ${item.position}`}>{item.position}</span>}
                                            {item.completed && <span title="Completed" aria-label="Completed" className="font-bold text-brand-coral">✓</span>}
                                        </div>}
                                        <Link href={item.literature.url} title={item.literature.title} className="mt-1 line-clamp-2 text-xs font-semibold leading-5 text-ink-950 [overflow-wrap:anywhere] hover:text-brand-coral">{item.literature.title}</Link>
                                        {canEdit && <div className="mt-1 flex items-center justify-between gap-1 text-[10px] text-ink-950/65">
                                            <button type="button" aria-label={`Move ${item.literature.title} earlier`} disabled={processing || index === 0} onClick={() => move(index, -1)} className="min-h-8 px-1 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-20">←</button>
                                            <button type="button" disabled={processing} onClick={() => visit('delete', item.remove_url)} className="min-h-8 text-brand-coral focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50">Remove</button>
                                            <button type="button" aria-label={`Move ${item.literature.title} later`} disabled={processing || index === list.items.data.length - 1} onClick={() => move(index, 1)} className="min-h-8 px-1 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-20">→</button>
                                        </div>}
                                    </article>
                                ))}
                            </div>
                        )}
                        <Pagination paginator={list.items} label="List items pagination" />
                        {comments && <ListComments comments={comments} commenting={commenting} />}
                    </section>
                </div>
            </SiteContainer>
        </>
    );
}
