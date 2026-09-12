import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { LiteratureCover } from '../../Components/ProfilePageUi';
import SiteContainer from '../../Components/SiteContainer';

function SearchResult({ literature, onAdd }) {
    return (
        <button
            type="button"
            onClick={() => onAdd(literature)}
            className="grid w-full grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-3 border-b border-ink-950/10 px-3 py-2 text-left last:border-b-0 hover:bg-brand-sky/20"
        >
            <LiteratureCover
                literature={{ ...literature, initials: literature.title.slice(0, 2).toUpperCase() }}
                className="aspect-[2/3]"
                alt={false}
            />
            <span className="min-w-0">
                <span className="block truncate text-sm font-bold text-ink-950">{literature.title}</span>
                <span className="block truncate text-xs text-ink-950/50">
                    {literature.authors?.join(' & ') || 'Author unavailable'} · {literature.year || 'Year unavailable'}
                </span>
            </span>
            <span className="text-[0.62rem] font-bold uppercase tracking-[0.12em] text-brand-coral">{literature.type}</span>
        </button>
    );
}

export default function EditList({ list, formUrl, profileUrl, searchUrl, items: initialItems = [], addUrl, reorderUrl, showUrl, successMessage }) {
    const editing = list !== null;
    const form = useForm({
        title: list?.title ?? '',
        description: list?.description ?? '',
        is_private: list?.is_private ?? false,
        is_ranked: list?.is_ranked ?? false,
    });
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [addingId, setAddingId] = useState(null);
    const [items, setItems] = useState(initialItems);
    const [draggingId, setDraggingId] = useState(null);

    useEffect(() => setItems(initialItems), [initialItems]);

    useEffect(() => {
        const normalizedQuery = query.trim();
        if (!editing || normalizedQuery.length < 2) {
            setResults([]);
            setSearching(false);
            return undefined;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setSearching(true);
            try {
                const response = await fetch(`${searchUrl}?${new URLSearchParams({ q: normalizedQuery })}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (response.ok) {
                    const payload = await response.json();
                    setResults(payload.data ?? []);
                } else {
                    setResults([]);
                }
            } catch (error) {
                if (error.name !== 'AbortError') setResults([]);
            } finally {
                if (!controller.signal.aborted) setSearching(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [editing, query, searchUrl]);

    const submit = (event) => {
        event.preventDefault();
        if (editing) form.put(formUrl, { preserveScroll: true });
        else form.post(formUrl);
    };

    const addLiterature = (literature) => {
        if (!addUrl || addingId !== null) return;
        setAddingId(literature.id);
        router.post(addUrl, { literature_id: literature.id }, {
            preserveScroll: true,
            onSuccess: () => {
                setQuery('');
                setResults([]);
            },
            onFinish: () => setAddingId(null),
        });
    };

    const reorder = (targetId) => {
        if (draggingId === null || draggingId === targetId) return;
        const reordered = [...items];
        const from = reordered.findIndex((item) => item.id === draggingId);
        const to = reordered.findIndex((item) => item.id === targetId);
        if (from < 0 || to < 0) return;
        const [moved] = reordered.splice(from, 1);
        reordered.splice(to, 0, moved);
        setItems(reordered);
        setDraggingId(null);
        router.patch(reorderUrl, { item_ids: reordered.map((item) => item.id) }, { preserveScroll: true });
    };

    return (
        <>
            <Head title={editing ? 'Edit list' : 'Create list'} />
            {successMessage && <div role="status" className="border-b border-brand-sky/50 bg-brand-sky/20 px-5 py-3 text-center text-sm font-semibold text-ink-950">{successMessage}</div>}
            <SiteContainer as="main" className="py-7 lg:py-10">
                <header className="flex flex-col gap-3 border-b border-ink-950/20 pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Custom Lists</p>
                        <h1 className="mt-1 text-3xl font-bold text-ink-950">{editing ? 'Edit list' : 'Create a list'}</h1>
                    </div>
                    {showUrl && <Link href={showUrl} className="text-xs font-bold uppercase tracking-[0.12em] text-ink-950/55 hover:text-brand-coral">View list</Link>}
                </header>

                <form onSubmit={submit} className="grid gap-5 border-b border-ink-950/15 py-5 lg:grid-cols-2 lg:gap-8">
                    <div className="space-y-4">
                        <label className="block text-sm font-bold text-ink-950">
                            Title
                            <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className="mt-1.5 block w-full border border-ink-950/20 bg-white/50 px-3 py-2 font-normal outline-none focus:border-brand-coral" required maxLength={120} />
                            {form.errors.title && <span className="mt-1 block text-xs text-brand-coral">{form.errors.title}</span>}
                        </label>
                        <label className="block text-sm font-bold text-ink-950">
                            Privacy
                            <select value={form.data.is_private ? 'private' : 'public'} onChange={(event) => form.setData('is_private', event.target.value === 'private')} className="mt-1.5 block w-full border border-ink-950/20 bg-white/50 px-3 py-2 font-normal outline-none focus:border-brand-coral">
                                <option value="public">Public — visible to everyone</option>
                                <option value="private">Private — visible only to you</option>
                            </select>
                        </label>
                        <label className="flex items-start gap-3 border-y border-ink-950/10 py-3 text-sm text-ink-950">
                            <input type="checkbox" checked={form.data.is_ranked} onChange={(event) => form.setData('is_ranked', event.target.checked)} className="mt-0.5" />
                            <span><strong className="block">Ranked list</strong><span className="text-xs text-ink-950/50">Show a numbered position for every literature.</span></span>
                        </label>
                    </div>
                    <label className="block text-sm font-bold text-ink-950">
                        Description <span className="font-normal text-ink-950/45">(optional)</span>
                        <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="mt-1.5 block min-h-40 w-full border border-ink-950/20 bg-white/50 px-3 py-2 font-normal outline-none focus:border-brand-coral" maxLength={3000} />
                    </label>
                    <div className="flex gap-3 lg:col-span-2">
                        <button type="submit" disabled={form.processing} className="bg-ink-950 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral disabled:opacity-50">{editing ? 'Save changes' : 'Create list'}</button>
                        <Link href={profileUrl} className="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-ink-950/60">Cancel</Link>
                    </div>
                </form>

                <section className="py-5" aria-labelledby="add-literature-heading">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">List contents</p>
                            <h2 id="add-literature-heading" className="mt-0.5 text-xl font-bold text-ink-950">Add literature</h2>
                        </div>
                        <span className="text-xs text-ink-950/45">{items.length} {items.length === 1 ? 'item' : 'items'}</span>
                    </div>

                    {editing ? (
                        <div className="relative mt-3 max-w-2xl">
                            <form onSubmit={(event) => { event.preventDefault(); if (results[0]) addLiterature(results[0]); }} className="flex">
                                <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search canonical literature by title…" className="min-w-0 flex-1 border border-ink-950/20 bg-white/60 px-3 py-2.5 text-sm outline-none focus:border-brand-coral" aria-label="Search literature to add" />
                                <button type="submit" disabled={!results[0] || addingId !== null} className="bg-brand-coral px-5 text-xs font-bold uppercase tracking-wider text-white disabled:opacity-40">Add</button>
                            </form>
                            {(query.trim().length >= 2 || searching) && (
                                <div className="absolute z-20 mt-1 max-h-80 w-full overflow-y-auto border border-ink-950/20 bg-brand-cream shadow-xl">
                                    {searching ? <p className="px-3 py-4 text-sm text-ink-950/50">Searching…</p> : results.length > 0 ? results.map((literature) => <SearchResult key={literature.id} literature={literature} onAdd={addLiterature} />) : <p className="px-3 py-4 text-sm text-ink-950/50">No matching literature.</p>}
                                </div>
                            )}
                        </div>
                    ) : <p className="mt-3 border-y border-dashed border-ink-950/15 py-4 text-sm text-ink-950/50">Create the list first, then add and order its literature here.</p>}
                </section>

                {editing && (
                    <section className="border-y border-ink-950/15" aria-label="Editable list items">
                        {items.length === 0 ? <p className="py-10 text-center text-sm text-ink-950/45">This list is empty. Search above to add its first literature.</p> : items.map((item, index) => (
                            <article
                                key={item.id}
                                draggable
                                onDragStart={(event) => { setDraggingId(item.id); event.dataTransfer.effectAllowed = 'move'; }}
                                onDragEnd={() => setDraggingId(null)}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={() => reorder(item.id)}
                                className={`grid grid-cols-[1.5rem_2rem_3.2rem_minmax(0,1fr)_auto] items-center gap-2 border-b border-ink-950/10 py-2.5 last:border-b-0 sm:grid-cols-[1.5rem_2.5rem_3.5rem_minmax(0,1fr)_auto] sm:gap-3 ${draggingId === item.id ? 'opacity-40' : ''}`}
                            >
                                <button type="button" className="cursor-grab text-lg text-ink-950/35 active:cursor-grabbing" title="Drag to reorder" aria-label={`Reorder ${item.literature.title}`}>⠿</button>
                                <span className={`text-center font-serif text-lg font-bold text-ink-950 ${form.data.is_ranked ? '' : 'invisible'}`} aria-hidden={!form.data.is_ranked}>{index + 1}</span>
                                <LiteratureCover literature={item.literature} className="aspect-[2/3]" alt={false} />
                                <div className="min-w-0">
                                    <Link href={item.literature.url} className="block truncate text-sm font-bold text-ink-950 hover:text-brand-coral sm:text-base">{item.literature.title}</Link>
                                    <p className="truncate text-xs text-ink-950/50">{item.literature.year || 'Year unavailable'} · {item.literature.author} · {item.literature.type_label}</p>
                                    {item.average_rating !== null && <p className="mt-0.5 text-[0.68rem] font-semibold text-brand-coral">★ {Number(item.average_rating).toFixed(1)} community rating</p>}
                                </div>
                                <button type="button" onClick={() => router.delete(item.remove_url, { preserveScroll: true })} className="px-2 py-2 text-[0.65rem] font-bold uppercase tracking-wider text-brand-coral hover:text-ink-950">Remove</button>
                            </article>
                        ))}
                    </section>
                )}
            </SiteContainer>
        </>
    );
}
