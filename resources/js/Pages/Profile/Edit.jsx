import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import AvatarCropDialog from '../../Components/AvatarCropDialog';
import ProfileSubNavigation from '../../Components/ProfileSubNavigation';

function FieldError({ message }) {
    return message ? <p className="mt-1.5 text-xs font-semibold text-red-700">{message}</p> : null;
}

function Initials({ value, className = '' }) {
    return <span className={`grid size-full place-items-center bg-ink-950 font-bold text-brand-cream ${className}`}>{value}</span>;
}

function LiteratureSlot({ index, items, value, onOpen }) {
    const selected = useMemo(() => items.find((item) => String(item.id) === String(value)), [items, value]);

    return (
        <div className="min-w-0">
            <button type="button" onClick={onOpen} aria-label={`${selected ? 'Change' : 'Choose'} favorite literature position ${index + 1}`} className="relative block aspect-[2/3] w-full overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/15 text-left shadow-[0_6px_18px_rgba(16,47,98,0.08)] transition hover:border-brand-coral focus-visible:border-brand-coral focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral/25">
                {selected?.cover_url ? <img src={selected.cover_url} alt={`Cover of ${selected.title}`} className="size-full object-cover" /> : selected ? <Initials value={selected.initials} className="text-xl" /> : <span className="grid size-full place-items-center text-2xl font-light text-ink-950/30" aria-hidden="true">+</span>}
                <span className="absolute left-1.5 top-1.5 grid size-5 place-items-center rounded-full bg-ink-950/80 text-[10px] font-bold text-brand-cream">{index + 1}</span>
            </button>
            <p className="mt-2 truncate text-center text-xs text-ink-950/60">{selected?.title ?? 'Choose literature'}</p>
        </div>
    );
}

function AuthorSlot({ index, items, value, onOpen }) {
    const selected = useMemo(() => items.find((item) => String(item.id) === String(value)), [items, value]);

    return (
        <div className="min-w-0 text-center">
            <button type="button" onClick={onOpen} aria-label={`${selected ? 'Change' : 'Choose'} favorite author position ${index + 1}`} className="relative mx-auto block aspect-square w-full overflow-hidden rounded-full border border-ink-950/15 bg-brand-sky/15 shadow-[0_6px_18px_rgba(16,47,98,0.08)] transition hover:border-brand-coral focus-visible:border-brand-coral focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral/25">
                {selected?.image_url ? <img src={selected.image_url} alt={`Portrait of ${selected.name}`} className="size-full object-cover" /> : selected ? <Initials value={selected.initials} className="text-lg" /> : <span className="grid size-full place-items-center text-2xl font-light text-ink-950/30" aria-hidden="true">+</span>}
                <span className="absolute left-1 top-1 grid size-5 place-items-center rounded-full bg-ink-950/80 text-[10px] font-bold text-brand-cream">{index + 1}</span>
            </button>
            <p className="mt-2 truncate text-xs text-ink-950/60">{selected?.name ?? 'Choose author'}</p>
        </div>
    );
}

function FavoritePickerDialog({ picker, searchUrl, selectedValues, currentValue, onClose, onSelect }) {
    const dialogRef = useRef(null);
    const inputRef = useRef(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [hasSearched, setHasSearched] = useState(false);
    const mode = picker?.mode ?? 'literature';
    const isLiterature = mode === 'literature';

    useEffect(() => {
        const dialog = dialogRef.current;

        if (picker && dialog && !dialog.open) {
            setQuery('');
            setResults([]);
            setSearching(false);
            setHasSearched(false);
            dialog.showModal();
            window.requestAnimationFrame(() => inputRef.current?.focus());
        } else if (!picker && dialog?.open) {
            dialog.close();
        }
    }, [picker]);

    useEffect(() => {
        const normalizedQuery = query.trim();

        if (!picker || normalizedQuery.length < 2) {
            setResults([]);
            setSearching(false);
            setHasSearched(false);
            return undefined;
        }

        const controller = new AbortController();
        setResults([]);
        setHasSearched(false);

        const timeout = window.setTimeout(async () => {
            setSearching(true);

            try {
                const response = await fetch(`${searchUrl}?${new URLSearchParams({ q: normalizedQuery })}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                const payload = response.ok ? await response.json() : { results: [] };

                if (!controller.signal.aborted) {
                    setResults(payload.results ?? []);
                    setHasSearched(true);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setResults([]);
                    setHasSearched(true);
                }
            } finally {
                if (!controller.signal.aborted) setSearching(false);
            }
        }, 275);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [picker, query, searchUrl]);

    const visibleResults = useMemo(() => results
        .filter((item) => {
            const id = String(item.id);
            return !selectedValues.includes(id) || id === String(currentValue ?? '');
        })
        .slice(0, 20), [results, selectedValues, currentValue]);

    const choose = (item) => {
        onSelect(item);
        onClose();
    };

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby="favorite-picker-title"
            className="m-auto max-h-[82vh] w-[min(640px,calc(100%_-_1.5rem))] overflow-hidden rounded-sm border border-brand-blueberry/20 bg-brand-cream p-0 text-ink-950 shadow-[0_24px_70px_rgba(16,47,98,0.28)] backdrop:bg-ink-950/70"
            onCancel={(event) => { event.preventDefault(); onClose(); }}
            onClick={(event) => { if (event.target === dialogRef.current) onClose(); }}
        >
            <div className="flex items-center justify-between border-b border-ink-950/10 px-5 py-4">
                <h2 id="favorite-picker-title" className="text-sm font-bold uppercase tracking-[0.16em] text-brand-blueberry">Pick a favorite {isLiterature ? 'literature' : 'author'}</h2>
                <button type="button" onClick={onClose} className="grid size-8 place-items-center text-xl text-brand-blueberry transition hover:bg-brand-sky/25 hover:text-brand-coral focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral/30" aria-label="Close favorite picker">×</button>
            </div>

            <div className="px-5 py-4">
                <label htmlFor="favorite-picker-search" className="text-sm font-bold text-ink-950">Name of {isLiterature ? 'Literature' : 'Author'}</label>
                <input
                    ref={inputRef}
                    id="favorite-picker-search"
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={`Search ${isLiterature ? 'literature' : 'author'}...`}
                    className="mt-1.5 w-full border border-ink-950/15 bg-white/70 px-3 py-2.5 text-sm outline-none placeholder:text-ink-950/40 focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/15"
                />
            </div>

            <div className="max-h-[50vh] overflow-y-auto border-t border-ink-950/10" aria-live="polite">
                {visibleResults.length > 0 ? visibleResults.map((item) => (
                    <button key={item.id} type="button" onClick={() => choose(item)} className="flex w-full items-center gap-3 border-b border-ink-950/8 px-5 py-3 text-left transition last:border-b-0 hover:bg-brand-sky/20 focus-visible:bg-brand-sky/20 focus-visible:outline-none">
                        <span className={`shrink-0 overflow-hidden border border-ink-950/10 bg-brand-sky/15 ${isLiterature ? 'h-16 w-11 rounded-sm' : 'size-12 rounded-full'}`}>
                            {(isLiterature ? item.cover_url : item.image_url)
                                ? <img src={isLiterature ? item.cover_url : item.image_url} alt="" className="size-full object-cover" />
                                : <Initials value={item.initials} className="text-xs" />}
                        </span>
                        <span className="min-w-0 flex-1">
                            <strong className="block truncate text-sm text-ink-950">{isLiterature ? item.title : item.name}</strong>
                            {isLiterature && <span className="mt-0.5 block truncate text-xs text-ink-950/55">{item.author}{item.year ? ` · ${item.year}` : ''}{item.type_label ? ` · ${item.type_label}` : ''}</span>}
                        </span>
                        {String(item.id) === String(currentValue ?? '') && <span className="text-[10px] font-bold uppercase tracking-[0.12em] text-brand-coral">Current</span>}
                    </button>
                )) : (
                    <p className="px-5 py-10 text-center text-sm text-ink-950/55">
                        {query.trim().length < 2
                            ? `Type at least 2 characters to search ${isLiterature ? 'literature' : 'authors'}.`
                            : searching || !hasSearched
                                ? 'Searching...'
                                : `No ${isLiterature ? 'literature' : 'authors'} matched your search.`}
                    </p>
                )}
            </div>

            {currentValue && (
                <div className="flex justify-end border-t border-ink-950/10 px-5 py-3">
                    <button type="button" onClick={() => choose(null)} className="text-xs font-bold text-ink-950/55 transition hover:text-brand-coral">Remove current favorite</button>
                </div>
            )}
        </dialog>
    );
}

export default function ProfileEdit({ profile, navigation, favoriteLiteratures, favoriteAuthors, favoriteLiteratureIds, favoriteAuthorIds, routes }) {
    const form = useForm({
        _method: 'put',
        name: profile.name,
        username: profile.username,
        location: profile.location ?? '',
        bio: profile.bio ?? '',
        avatar: null,
        remove_avatar: false,
        favorite_literature_ids: favoriteLiteratureIds.map(String),
        favorite_author_ids: favoriteAuthorIds.map(String),
    });
    const [avatarPreview, setAvatarPreview] = useState(profile.avatar_url);
    const [avatarSourceFile, setAvatarSourceFile] = useState(null);
    const [editingAvatarFile, setEditingAvatarFile] = useState(null);
    const [avatarCropOpen, setAvatarCropOpen] = useState(false);
    const [avatarSelectionError, setAvatarSelectionError] = useState('');
    const avatarInputRef = useRef(null);
    const [picker, setPicker] = useState(null);
    const [literatureItems, setLiteratureItems] = useState(favoriteLiteratures);
    const [authorItems, setAuthorItems] = useState(favoriteAuthors);

    useEffect(() => {
        if (!form.data.avatar) {
            setAvatarPreview(form.data.remove_avatar ? null : profile.avatar_url);
            return undefined;
        }

        const previewUrl = URL.createObjectURL(form.data.avatar);
        setAvatarPreview(previewUrl);
        return () => URL.revokeObjectURL(previewUrl);
    }, [form.data.avatar, form.data.remove_avatar, profile.avatar_url]);

    const selectAvatar = (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            setAvatarSelectionError('Choose a JPG, PNG, or WebP image under 2 MB.');
            return;
        }

        setAvatarSelectionError('');
        setEditingAvatarFile(file);
        setAvatarCropOpen(true);
    };

    const closeAvatarCrop = () => {
        setAvatarCropOpen(false);
        setEditingAvatarFile(avatarSourceFile);
        window.requestAnimationFrame(() => avatarInputRef.current?.focus());
    };

    const applyAvatarCrop = (file) => {
        setAvatarSourceFile(editingAvatarFile);
        form.setData({ ...form.data, avatar: file, remove_avatar: false });
        form.clearErrors('avatar');
        setAvatarCropOpen(false);
        window.requestAnimationFrame(() => avatarInputRef.current?.focus());
    };

    const updateSlot = (key, index, value) => {
        const next = [...form.data[key]];
        next[index] = value;
        form.setData(key, next);
    };
    const submit = (event) => {
        event.preventDefault();
        form.clearErrors();
        form.post(routes.update, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };
    const chosenLiteratures = form.data.favorite_literature_ids.filter(Boolean).map(String);
    const chosenAuthors = form.data.favorite_author_ids.filter(Boolean).map(String);
    const pickerKey = picker?.mode === 'author' ? 'favorite_author_ids' : 'favorite_literature_ids';
    const pickerSelectedValues = picker?.mode === 'author' ? chosenAuthors : chosenLiteratures;
    const pickerCurrentValue = picker ? form.data[pickerKey][picker.index] : '';
    const pickerSearchUrl = picker?.mode === 'author' ? routes.favoriteAuthorsSearch : routes.favoriteLiteraturesSearch;

    const selectFavorite = (item) => {
        if (item) {
            const setItems = picker.mode === 'author' ? setAuthorItems : setLiteratureItems;
            setItems((currentItems) => currentItems.some((current) => String(current.id) === String(item.id))
                ? currentItems
                : [...currentItems, item]);
        }

        updateSlot(pickerKey, picker.index, item ? String(item.id) : '');
    };

    return (
        <>
            <Head title="Edit profile" />
            <ProfileSubNavigation navigation={navigation} />
            <main className="mx-auto max-w-6xl px-5 py-7 sm:px-8 sm:py-9 lg:px-10 lg:py-11">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-3 border-b border-ink-950/15 pb-3">
                    <div><p className="text-[11px] font-bold uppercase tracking-[0.18em] text-brand-coral">Account settings</p><h1 className="mt-1 text-2xl font-semibold text-ink-950 sm:text-3xl">Edit profile</h1></div>
                    <Link href={routes.profile} className="text-sm font-bold text-ink-950/55 hover:text-brand-coral">Back to profile</Link>
                </div>
                <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-start">
                    <section className="border border-ink-950/10 bg-white/30 p-5 sm:p-6" aria-labelledby="reader-information-heading">
                        <h2 id="reader-information-heading" className="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Reader information</h2>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-4 border-b border-ink-950/10 pb-4 sm:col-span-2">
                                <div className="size-20 shrink-0 overflow-hidden rounded-full border border-ink-950/15 bg-ink-950">{avatarPreview ? <img src={avatarPreview} alt="Profile preview" className="size-full object-cover" /> : <Initials value={profile.initials} className="text-xl" />}</div>
                                <div className="min-w-0 flex-1">
                                    <label htmlFor="avatar" className="text-sm font-bold text-ink-950">Profile photo</label>
                                    <input ref={avatarInputRef} id="avatar" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-help" onChange={selectAvatar} className="mt-2 block w-full text-xs file:mr-3 file:border-0 file:bg-ink-950 file:px-3 file:py-2 file:font-bold file:text-brand-cream" />
                                    <p id="avatar-help" className="mt-1.5 text-xs text-ink-950/50">JPG, PNG, or WebP under 2 MB. Adjust the position before saving.</p>
                                    {avatarSourceFile && <button type="button" onClick={() => { setEditingAvatarFile(avatarSourceFile); setAvatarCropOpen(true); }} className="mt-2 text-xs font-semibold text-brand-coral underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand-coral">Adjust selected photo</button>}
                                    {profile.has_avatar && <label className="mt-2 flex items-center gap-2 text-xs text-ink-950/55"><input type="checkbox" checked={form.data.remove_avatar} onChange={(event) => { const remove = event.target.checked; form.setData({ ...form.data, remove_avatar: remove, avatar: remove ? null : form.data.avatar }); if (remove) { setAvatarSourceFile(null); setEditingAvatarFile(null); } }} className="size-4 accent-brand-coral" /> Remove current photo</label>}
                                    {avatarSelectionError && <p role="alert" className="mt-1.5 text-xs font-semibold text-red-700">{avatarSelectionError}</p>}
                                    <FieldError message={form.errors.avatar} />
                                </div>
                            </div>
                            <label className="text-sm font-bold text-ink-950">Display name<input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required maxLength="100" className="mt-1.5 w-full border border-ink-950/15 bg-white/55 px-3 py-2.5 font-normal outline-none focus:border-brand-coral" /><FieldError message={form.errors.name} /></label>
                            <label className="text-sm font-bold text-ink-950">Username<div className="mt-1.5 flex border border-ink-950/15 bg-white/55 focus-within:border-brand-coral"><span className="grid place-items-center border-r border-ink-950/10 px-3 text-ink-950/40">@</span><input value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} required className="min-w-0 flex-1 bg-transparent px-3 py-2.5 font-normal outline-none" /></div><FieldError message={form.errors.username} /></label>
                            <label className="text-sm font-bold text-ink-950 sm:col-span-2">Location <span className="font-normal text-ink-950/40">(optional)</span><input value={form.data.location} onChange={(event) => form.setData('location', event.target.value)} maxLength="100" className="mt-1.5 w-full border border-ink-950/15 bg-white/55 px-3 py-2.5 font-normal outline-none focus:border-brand-coral" /><FieldError message={form.errors.location} /></label>
                            <label className="text-sm font-bold text-ink-950 sm:col-span-2">Bio <span className="font-normal text-ink-950/40">({form.data.bio.length}/500)</span><textarea value={form.data.bio} onChange={(event) => form.setData('bio', event.target.value)} rows="6" maxLength="500" className="mt-1.5 w-full resize-y border border-ink-950/15 bg-white/55 px-3 py-2.5 font-normal leading-6 outline-none focus:border-brand-coral" /><FieldError message={form.errors.bio} /></label>
                        </div>
                    </section>
                    <div className="grid gap-7">
                        <fieldset className="border border-ink-950/10 bg-white/30 p-4 sm:p-5">
                            <div className="flex items-end justify-between gap-3 border-b border-ink-950/10 pb-2.5"><legend className="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Favorite literature</legend><span className="text-[11px] text-ink-950/45">Choose up to 4</span></div>
                            <div className="mt-4 grid grid-cols-4 gap-2.5 sm:gap-3">{form.data.favorite_literature_ids.map((value, index) => <LiteratureSlot key={index} index={index} items={literatureItems} value={value} onOpen={() => setPicker({ mode: 'literature', index })} />)}</div>
                            <FieldError message={form.errors.favorite_literature_ids ?? form.errors['favorite_literature_ids.0']} />
                        </fieldset>
                        <fieldset className="border border-ink-950/10 bg-white/30 p-4 sm:p-5">
                            <div className="flex items-end justify-between gap-3 border-b border-ink-950/10 pb-2.5"><legend className="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Favorite authors</legend><span className="text-[11px] text-ink-950/45">Choose up to 4</span></div>
                            <div className="mt-4 grid grid-cols-4 gap-2.5 sm:gap-3">{form.data.favorite_author_ids.map((value, index) => <AuthorSlot key={index} index={index} items={authorItems} value={value} onOpen={() => setPicker({ mode: 'author', index })} />)}</div>
                            <FieldError message={form.errors.favorite_author_ids ?? form.errors['favorite_author_ids.0']} />
                        </fieldset>
                        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-ink-950/10 pt-5">{form.recentlySuccessful && <span role="status" className="mr-auto text-sm font-semibold text-green-800">Profile saved.</span>}<Link href={routes.profile} className="inline-flex min-h-10 items-center border border-ink-950/15 px-5 text-sm font-bold text-ink-950 hover:border-brand-coral">Cancel</Link><button type="submit" disabled={form.processing} className="min-h-10 bg-ink-950 px-6 text-sm font-bold text-brand-cream transition hover:bg-brand-coral disabled:cursor-wait disabled:opacity-60">{form.processing ? 'Saving…' : 'Save profile'}</button></div>
                    </div>
                </form>
            </main>
            <FavoritePickerDialog
                picker={picker}
                searchUrl={pickerSearchUrl}
                selectedValues={pickerSelectedValues}
                currentValue={pickerCurrentValue}
                onClose={() => setPicker(null)}
                onSelect={selectFavorite}
            />
            <AvatarCropDialog file={editingAvatarFile} open={avatarCropOpen} onClose={closeAvatarCrop} onApply={applyAvatarCrop} />
        </>
    );
}
