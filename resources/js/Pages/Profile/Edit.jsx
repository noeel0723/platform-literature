import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';

function FieldError({ message }) {
    return message ? <p className="mt-1.5 text-xs font-semibold text-red-700">{message}</p> : null;
}

function Initials({ value, className = '' }) {
    return <span className={`grid size-full place-items-center bg-ink-950 font-bold text-brand-cream ${className}`}>{value}</span>;
}

function LiteratureSlot({ index, items, value, selectedValues, onChange }) {
    const selected = useMemo(() => items.find((item) => String(item.id) === String(value)), [items, value]);

    return (
        <div className="min-w-0">
            <div className="relative aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/15 shadow-[0_6px_18px_rgba(16,47,98,0.08)]">
                {selected?.cover_url ? <img src={selected.cover_url} alt={`Cover of ${selected.title}`} className="size-full object-cover" /> : selected ? <Initials value={selected.initials} className="text-xl" /> : <span className="grid size-full place-items-center text-2xl font-light text-ink-950/30" aria-hidden="true">+</span>}
                <span className="absolute left-1.5 top-1.5 grid size-5 place-items-center rounded-full bg-ink-950/80 text-[10px] font-bold text-brand-cream">{index + 1}</span>
            </div>
            <select value={value} onChange={(event) => onChange(event.target.value)} aria-label={`Favorite literature position ${index + 1}`} className="mt-2 w-full truncate border border-ink-950/15 bg-white/55 px-2 py-2 text-xs outline-none focus:border-brand-coral">
                <option value="">Choose literature</option>
                {items.map((item) => <option key={item.id} value={item.id} disabled={String(item.id) !== String(value) && selectedValues.includes(String(item.id))}>{item.title}</option>)}
            </select>
        </div>
    );
}

function AuthorSlot({ index, items, value, selectedValues, onChange }) {
    const selected = useMemo(() => items.find((item) => String(item.id) === String(value)), [items, value]);

    return (
        <div className="min-w-0 text-center">
            <div className="relative mx-auto aspect-square overflow-hidden rounded-full border border-ink-950/15 bg-brand-sky/15 shadow-[0_6px_18px_rgba(16,47,98,0.08)]">
                {selected?.image_url ? <img src={selected.image_url} alt={`Portrait of ${selected.name}`} className="size-full object-cover" /> : selected ? <Initials value={selected.initials} className="text-lg" /> : <span className="grid size-full place-items-center text-2xl font-light text-ink-950/30" aria-hidden="true">+</span>}
                <span className="absolute left-1 top-1 grid size-5 place-items-center rounded-full bg-ink-950/80 text-[10px] font-bold text-brand-cream">{index + 1}</span>
            </div>
            <select value={value} onChange={(event) => onChange(event.target.value)} aria-label={`Favorite author position ${index + 1}`} className="mt-2 w-full truncate border border-ink-950/15 bg-white/55 px-2 py-2 text-xs outline-none focus:border-brand-coral">
                <option value="">Choose author</option>
                {items.map((item) => <option key={item.id} value={item.id} disabled={String(item.id) !== String(value) && selectedValues.includes(String(item.id))}>{item.name}</option>)}
            </select>
        </div>
    );
}

export default function ProfileEdit({ profile, navigation, literatures, authors, favoriteLiteratureIds, favoriteAuthorIds, routes }) {
    const form = useForm({
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

    useEffect(() => {
        if (!form.data.avatar) {
            setAvatarPreview(form.data.remove_avatar ? null : profile.avatar_url);
            return undefined;
        }

        const previewUrl = URL.createObjectURL(form.data.avatar);
        setAvatarPreview(previewUrl);
        return () => URL.revokeObjectURL(previewUrl);
    }, [form.data.avatar, form.data.remove_avatar, profile.avatar_url]);

    const updateSlot = (key, index, value) => {
        const next = [...form.data[key]];
        next[index] = value;
        form.setData(key, next);
    };
    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' })).post(routes.update, { forceFormData: true, preserveScroll: true });
    };
    const chosenLiteratures = form.data.favorite_literature_ids.filter(Boolean).map(String);
    const chosenAuthors = form.data.favorite_author_ids.filter(Boolean).map(String);

    return (
        <>
            <Head title="Edit profile" />
            <ProfileSubNavigation navigation={navigation} />
            <main className="mx-auto max-w-7xl px-5 py-7 sm:px-8 sm:py-9 lg:px-10 lg:py-11">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-3 border-b border-ink-950/15 pb-3">
                    <div><p className="text-[11px] font-bold uppercase tracking-[0.18em] text-brand-coral">Account settings</p><h1 className="mt-1 text-2xl font-semibold text-ink-950 sm:text-3xl">Edit profile</h1></div>
                    <Link href={routes.profile} className="text-sm font-bold text-ink-950/55 hover:text-brand-coral">Back to profile</Link>
                </div>
                <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)] lg:items-start">
                    <section className="border border-ink-950/10 bg-white/30 p-5 sm:p-6" aria-labelledby="reader-information-heading">
                        <h2 id="reader-information-heading" className="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Reader information</h2>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-4 border-b border-ink-950/10 pb-4 sm:col-span-2">
                                <div className="size-20 shrink-0 overflow-hidden rounded-full border border-ink-950/15 bg-ink-950">{avatarPreview ? <img src={avatarPreview} alt="Profile preview" className="size-full object-cover" /> : <Initials value={profile.initials} className="text-xl" />}</div>
                                <div className="min-w-0 flex-1">
                                    <label htmlFor="avatar" className="text-sm font-bold text-ink-950">Profile photo</label>
                                    <input id="avatar" type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => form.setData('avatar', event.target.files?.[0] ?? null)} className="mt-2 block w-full text-xs file:mr-3 file:border-0 file:bg-ink-950 file:px-3 file:py-2 file:font-bold file:text-brand-cream" />
                                    {profile.has_avatar && <label className="mt-2 flex items-center gap-2 text-xs text-ink-950/55"><input type="checkbox" checked={form.data.remove_avatar} onChange={(event) => form.setData('remove_avatar', event.target.checked)} className="size-4 accent-brand-coral" /> Remove current photo</label>}
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
                            <div className="mt-4 grid grid-cols-4 gap-2.5 sm:gap-3">{form.data.favorite_literature_ids.map((value, index) => <LiteratureSlot key={index} index={index} items={literatures} value={value} selectedValues={chosenLiteratures} onChange={(nextValue) => updateSlot('favorite_literature_ids', index, nextValue)} />)}</div>
                            <FieldError message={form.errors.favorite_literature_ids ?? form.errors['favorite_literature_ids.0']} />
                        </fieldset>
                        <fieldset className="border border-ink-950/10 bg-white/30 p-4 sm:p-5">
                            <div className="flex items-end justify-between gap-3 border-b border-ink-950/10 pb-2.5"><legend className="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Favorite authors</legend><span className="text-[11px] text-ink-950/45">Choose up to 4</span></div>
                            <div className="mt-4 grid grid-cols-4 gap-2.5 sm:gap-3">{form.data.favorite_author_ids.map((value, index) => <AuthorSlot key={index} index={index} items={authors} value={value} selectedValues={chosenAuthors} onChange={(nextValue) => updateSlot('favorite_author_ids', index, nextValue)} />)}</div>
                            <FieldError message={form.errors.favorite_author_ids ?? form.errors['favorite_author_ids.0']} />
                        </fieldset>
                        <div className="flex justify-end gap-3 border-t border-ink-950/10 pt-5"><Link href={routes.profile} className="inline-flex min-h-10 items-center border border-ink-950/15 px-5 text-sm font-bold text-ink-950 hover:border-brand-coral">Cancel</Link><button type="submit" disabled={form.processing} className="min-h-10 bg-ink-950 px-6 text-sm font-bold text-brand-cream transition hover:bg-brand-coral disabled:cursor-wait disabled:opacity-60">{form.processing ? 'Saving…' : 'Save profile'}</button></div>
                    </div>
                </form>
            </main>
        </>
    );
}
