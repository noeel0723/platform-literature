import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { LiteratureCover } from '../../Components/ProfilePageUi';
import SiteContainer from '../../Components/SiteContainer';

function SearchResult({ literature, onAdd }) {
    return (
        <button
            type="button"
            onClick={() => onAdd(literature)}
            className="grid w-full grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-3 border-b border-ink-950/10 px-3 py-2 text-left transition-colors last:border-b-0 hover:bg-brand-sky/20 focus-visible:bg-brand-sky/20"
        >
            <LiteratureCover
                literature={{ ...literature, initials: literature.title.slice(0, 2).toUpperCase() }}
                className="aspect-[2/3] rounded-sm"
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
                <div className="mx-auto max-w-5xl">
                    <header className="flex items-start justify-between gap-4">
                        <div>
                            <p className="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-brand-coral">Custom Lists</p>
                            <h1 className="mt-1 font-serif text-2xl font-bold text-ink-950 sm:text-3xl">{editing ? 'Edit list' : 'Create a list'}</h1>
                        </div>
                        <Link href={showUrl || profileUrl} className="shrink-0 pt-1 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-ink-950/55 transition hover:text-brand-coral focus-visible:underline">
                            {showUrl ? 'View list' : 'Cancel'}
                        </Link>
                    </header>

                    <form onSubmit={submit} className="mt-5">
                        <div className="grid gap-6 rounded-xl border border-ink-950/10 bg-white/50 p-5 lg:grid-cols-2 lg:p-6">
                            <div className="grid content-start gap-4">
                                <label className="block text-xs font-bold text-ink-950">
                                    Title
                                    <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className="mt-1.5 block h-10 w-full rounded-md border border-ink-950/15 bg-white/70 px-3 text-sm font-normal outline-none transition focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20" required maxLength={120} placeholder="Name your list" />
                                    {form.errors.title && <span className="mt-1 block text-xs font-medium text-red-700" role="alert">{form.errors.title}</span>}
                                </label>
                                <label className="block text-xs font-bold text-ink-950">
                                    Privacy
                                    <select value={form.data.is_private ? 'private' : 'public'} onChange={(event) => form.setData('is_private', event.target.value === 'private')} className="mt-1.5 block h-10 w-full rounded-md border border-ink-950/15 bg-white/70 px-3 text-sm font-normal outline-none transition focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20">
                                        <option value="public">Public — visible to everyone</option>
                                        <option value="private">Private — visible only to you</option>
                                    </select>
                                </label>
                                <label className="flex items-start gap-3 pt-1 text-sm text-ink-950">
                                    <input type="checkbox" checked={form.data.is_ranked} onChange={(event) => form.setData('is_ranked', event.target.checked)} className="mt-0.5 accent-brand-coral" />
                                    <span><strong className="block text-xs">Ranked list</strong><span className="mt-0.5 block text-xs text-ink-950/50">Show a numbered position for every literature.</span></span>
                                </label>
                            </div>
                            <label className="block text-xs font-bold text-ink-950">
                                Description <span className="font-normal text-ink-950/45">(optional)</span>
                                <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="mt-1.5 block min-h-40 w-full rounded-md border border-ink-950/15 bg-white/70 px-3 py-2 text-sm font-normal outline-none transition focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20" maxLength={3000} placeholder="Say something about this list..." />
                                {form.errors.description && <span className="mt-1 block text-xs font-medium text-red-700" role="alert">{form.errors.description}</span>}
                            </label>
                        </div>
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <button type="submit" disabled={form.processing} className="inline-flex min-h-10 items-center rounded-md bg-ink-950 px-5 text-xs font-bold text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:opacity-50">{editing ? 'Save changes' : 'Create list'}</button>
                            <Link href={profileUrl} className="px-3 py-2 text-xs font-bold uppercase tracking-wider text-ink-950/60 transition hover:text-brand-coral">Cancel</Link>
                        </div>
                    </form>

                    <section className="mt-10" aria-labelledby="add-literature-heading">
                        <div className="flex items-end justify-between gap-3">
                            <div>
                                <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">List contents</p>
                                <h2 id="add-literature-heading" className="mt-1 font-serif text-xl font-bold text-ink-950">Add literature</h2>
                            </div>
                            <span className="pb-0.5 text-xs text-ink-950/50">{items.length} {items.length === 1 ? 'item' : 'items'}</span>
                        </div>

                        <div className="relative mt-4">
                            <form onSubmit={(event) => { event.preventDefault(); if (results[0]) addLiterature(results[0]); }} className="flex min-w-0 overflow-hidden rounded-md border border-ink-950/15 bg-white/70 shadow-sm shadow-ink-950/5 focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/20">
                                <span className="grid w-10 shrink-0 place-items-center text-ink-950/40" aria-hidden="true">
                                    <svg className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
                                </span>
                                <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search canonical literature by title…" disabled={!editing} className="h-10 min-w-0 flex-1 bg-transparent pr-2 text-sm text-ink-950 outline-none placeholder:text-ink-950/40 disabled:cursor-not-allowed" aria-label="Search literature to add" />
                                <button type="submit" disabled={!editing || !results[0] || addingId !== null} className="shrink-0 border-l border-ink-950/10 bg-brand-coral px-4 text-xs font-bold uppercase tracking-wider text-ink-950 transition hover:bg-brand-coral/85 disabled:cursor-not-allowed disabled:opacity-45 sm:px-5">Add</button>
                            </form>
                            {editing && (query.trim().length >= 2 || searching) && (
                                <div className="absolute z-20 mt-1 max-h-80 w-full overflow-y-auto rounded-md border border-ink-950/15 bg-brand-cream shadow-lg shadow-ink-950/10">
                                    {searching ? <p className="px-3 py-4 text-sm text-ink-950/50">Searching…</p> : results.length > 0 ? results.map((literature) => <SearchResult key={literature.id} literature={literature} onAdd={addLiterature} />) : <p className="px-3 py-4 text-sm text-ink-950/50">No matching literature.</p>}
                                </div>
                            )}
                        </div>
                    </section>

                    {!editing || items.length === 0 ? (
                        <div className="mt-4 rounded-xl border border-dashed border-ink-950/15 bg-white/40 px-5 py-8 text-center">
                            <p className="font-serif text-base font-bold text-ink-950">No literature yet</p>
                            <p className="mt-1 text-sm text-ink-950/55">{editing ? 'Search above to add your first title to this list.' : 'Create the list first, then add and order its literature here.'}</p>
                        </div>
                    ) : (
                        <section className="mt-4 divide-y divide-ink-950/10 overflow-hidden rounded-xl border border-ink-950/10 bg-white/50" aria-label="Editable list items">
                            {items.map((item, index) => (
                                <article
                                    key={item.id}
                                    draggable
                                    onDragStart={(event) => { setDraggingId(item.id); event.dataTransfer.effectAllowed = 'move'; }}
                                    onDragEnd={() => setDraggingId(null)}
                                    onDragOver={(event) => event.preventDefault()}
                                    onDrop={() => reorder(item.id)}
                                    className={`flex min-w-0 items-center gap-2 px-3 py-3 transition-colors hover:bg-brand-sky/10 sm:gap-3 sm:px-4 ${draggingId === item.id ? 'opacity-40' : ''}`}
                                >
                                    <button type="button" className="w-6 shrink-0 cursor-grab text-lg text-ink-950/40 active:cursor-grabbing" title="Drag to reorder" aria-label={`Reorder ${item.literature.title}`}>⠿</button>
                                    {form.data.is_ranked && <span className="w-6 shrink-0 text-center font-serif text-lg font-bold text-ink-950">{index + 1}</span>}
                                    <div className="w-10 shrink-0 sm:w-12"><LiteratureCover literature={item.literature} className="aspect-[2/3] rounded-sm" alt={false} /></div>
                                    <div className="min-w-0 flex-1">
                                        <Link href={item.literature.url} className="block truncate text-sm font-bold text-ink-950 hover:text-brand-coral sm:text-base">{item.literature.title}</Link>
                                        <p className="truncate text-xs text-ink-950/50">{item.literature.year || 'Year unavailable'} · {item.literature.author} · {item.literature.type_label}</p>
                                        {item.average_rating !== null && <p className="mt-0.5 text-[0.68rem] font-semibold text-brand-coral">★ {Number(item.average_rating).toFixed(1)} community rating</p>}
                                    </div>
                                    <button type="button" onClick={() => router.delete(item.remove_url, { preserveScroll: true })} className="shrink-0 px-1 py-2 text-[0.65rem] font-bold uppercase tracking-wider text-brand-coral transition hover:text-ink-950 sm:px-2">Remove</button>
                                </article>
                            ))}
                        </section>
                    )}
                </div>
            </SiteContainer>
        </>
    );
}
