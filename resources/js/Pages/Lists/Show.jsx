import { Head, Link, router } from '@inertiajs/react';

import { LiteratureCover, Pagination } from '../../Components/ProfilePageUi';
import SiteContainer from '../../Components/SiteContainer';

export default function ShowList({ list, canEdit, successMessage }) {
    const move = (index, direction) => {
        const items = [...list.items.data];
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        [items[index], items[target]] = [items[target], items[index]];
        router.patch(list.reorder_url, { item_ids: items.map((item) => item.id) }, { preserveScroll: true });
    };

    const destroyList = () => {
        if (window.confirm('Delete this list?')) router.delete(list.delete_url);
    };

    return (
        <>
            <Head title={list.title} />
            {successMessage && <div role="status" className="border-b border-brand-sky/50 bg-brand-sky/20 px-5 py-3 text-center text-sm font-semibold text-ink-950">{successMessage}</div>}
            <SiteContainer as="main" className="py-8 lg:py-12">
                <header className="border-b border-ink-950/20 pb-5">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">{list.is_private ? 'Private list' : 'Public list'} · {list.items.total} works</p>
                            <h1 className="mt-1 font-serif text-3xl font-bold text-ink-950 sm:text-4xl">{list.title}</h1>
                            <p className="mt-2 text-sm text-ink-950/55">By <Link href={list.owner.url} className="font-bold text-ink-950 hover:text-brand-coral">{list.owner.name}</Link></p>
                        </div>
                        {canEdit && <div className="flex gap-2"><Link href={list.edit_url} className="border border-ink-950/20 px-3 py-2 text-xs font-bold uppercase tracking-wider text-ink-950">Edit</Link><button type="button" onClick={destroyList} className="px-3 py-2 text-xs font-bold uppercase tracking-wider text-brand-coral">Delete</button></div>}
                    </div>
                    {list.description && <p className="mt-4 max-w-3xl text-sm leading-6 text-ink-950/65">{list.description}</p>}
                </header>

                {list.items.data.length === 0 ? <div className="py-12 text-center text-sm text-ink-950/50">This list is empty. Add literature from its detail page.</div> : (
                    <div className="mt-5 grid grid-cols-3 gap-x-3 gap-y-6 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-10">
                        {list.items.data.map((item, index) => item.literature && (
                            <article key={item.id} className="min-w-0">
                                <Link href={item.literature.url}><LiteratureCover literature={item.literature} className="aspect-[2/3]" /></Link>
                                <Link href={item.literature.url} className="mt-1.5 block truncate text-xs font-bold text-ink-950 hover:text-brand-coral">{item.literature.title}</Link>
                                {canEdit && <div className="mt-1 flex items-center justify-between text-[0.65rem]"><button type="button" disabled={index === 0} onClick={() => move(index, -1)} className="disabled:opacity-20">←</button><button type="button" onClick={() => router.delete(item.remove_url, { preserveScroll: true })} className="text-brand-coral">Remove</button><button type="button" disabled={index === list.items.data.length - 1} onClick={() => move(index, 1)} className="disabled:opacity-20">→</button></div>}
                            </article>
                        ))}
                    </div>
                )}
                <Pagination paginator={list.items} label="List items pagination" />
            </SiteContainer>
        </>
    );
}
