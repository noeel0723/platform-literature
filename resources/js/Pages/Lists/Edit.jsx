import { Head, Link, useForm } from '@inertiajs/react';

import SiteContainer from '../../Components/SiteContainer';

export default function EditList({ list, formUrl, profileUrl }) {
    const editing = list !== null;
    const form = useForm({
        title: list?.title ?? '',
        description: list?.description ?? '',
        is_private: list?.is_private ?? false,
    });

    const submit = (event) => {
        event.preventDefault();
        if (editing) form.put(formUrl);
        else form.post(formUrl);
    };

    return (
        <>
            <Head title={editing ? 'Edit list' : 'Create list'} />
            <SiteContainer as="main" className="py-8 lg:py-12">
                <div className="mx-auto max-w-2xl">
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Custom Lists</p>
                    <h1 className="mt-1 font-serif text-3xl font-bold text-ink-950">{editing ? 'Edit list' : 'Create a list'}</h1>
                    <form onSubmit={submit} className="mt-6 space-y-5 border-y border-ink-950/15 py-6">
                        <label className="block text-sm font-bold text-ink-950">
                            Title
                            <input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className="mt-2 block w-full border border-ink-950/20 bg-white/50 px-3 py-2.5 font-normal outline-none focus:border-brand-coral" required maxLength={120} />
                            {form.errors.title && <span className="mt-1 block text-xs text-brand-coral">{form.errors.title}</span>}
                        </label>
                        <label className="block text-sm font-bold text-ink-950">
                            Description <span className="font-normal text-ink-950/45">(optional)</span>
                            <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="mt-2 block min-h-32 w-full border border-ink-950/20 bg-white/50 px-3 py-2.5 font-normal outline-none focus:border-brand-coral" maxLength={3000} />
                        </label>
                        <label className="flex items-center gap-2 text-sm font-semibold text-ink-950">
                            <input type="checkbox" checked={form.data.is_private} onChange={(event) => form.setData('is_private', event.target.checked)} />
                            Private list
                        </label>
                        <div className="flex gap-3">
                            <button type="submit" disabled={form.processing} className="bg-ink-950 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral disabled:opacity-50">{editing ? 'Save changes' : 'Create list'}</button>
                            <Link href={profileUrl} className="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-ink-950/60">Cancel</Link>
                        </div>
                    </form>
                </div>
            </SiteContainer>
        </>
    );
}
