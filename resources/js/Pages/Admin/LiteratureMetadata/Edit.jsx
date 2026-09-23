import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const metadataFields = [
    { name: 'title', label: 'Display title', type: 'text' },
    { name: 'original_title', label: 'Original or edition title', type: 'text' },
    { name: 'publication_year', label: 'Publication year', type: 'number' },
    { name: 'tagline', label: 'Short description', type: 'textarea' },
    { name: 'synopsis', label: 'Synopsis', type: 'textarea' },
    { name: 'publisher', label: 'Publisher', type: 'text' },
    { name: 'language', label: 'Language code', type: 'text' },
    { name: 'format', label: 'Format', type: 'text' },
];

function useObjectUrl(file) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!file) {
            setUrl(null);
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return url;
}

function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs font-semibold text-red-700">{message}</p> : null;
}

function ApiValue({ value }) {
    return <p className="mt-1.5 line-clamp-2 text-xs leading-5 text-ink-950/45">API value: {value || 'Unavailable'}</p>;
}

function UploadField({ kind, form, override, literature, previewUrl }) {
    const isCover = kind === 'cover';
    const label = isCover ? 'Cover' : 'Hero artwork';
    const uploadName = isCover ? 'cover_upload' : 'backdrop_upload';
    const removeName = isCover ? 'remove_cover_upload' : 'remove_backdrop_upload';
    const urlName = isCover ? 'cover_url' : 'backdrop_url';
    const existingUrl = isCover ? override?.uploaded_cover_url : override?.uploaded_backdrop_url;
    const visiblePreview = previewUrl || existingUrl;

    return (
        <div className={isCover ? 'sm:col-span-2' : 'border-t border-ink-950/10 pt-5 sm:col-span-2'}>
            <h2 className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">{label}</h2>
            <div className={`mt-3 grid gap-4 ${isCover ? 'sm:grid-cols-[minmax(0,1fr)_100px]' : 'sm:grid-cols-[minmax(0,1fr)_180px]'} sm:items-start`}>
                <div>
                    <label htmlFor={uploadName} className="text-xs font-semibold text-ink-950">Upload {isCover ? 'cover' : 'hero artwork'}</label>
                    <input id={uploadName} type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => form.setData(uploadName, event.target.files?.[0] ?? null)} className="mt-2 block w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 file:mr-3 file:border-0 file:bg-brand-sky/30 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-950 focus:border-brand-coral" />
                    <p className="mt-1.5 text-xs leading-5 text-ink-950/55">{isCover ? 'JPG, PNG, or WebP. Maximum file size 5 MB.' : 'JPG, PNG, or WebP. A wide landscape image works best. Maximum file size 5 MB.'}</p>
                    <FieldError message={form.errors[uploadName]} />
                    {existingUrl && <label className="mt-3 flex items-center gap-2 text-xs font-semibold text-ink-950/70"><input type="checkbox" checked={Boolean(form.data[removeName])} onChange={(event) => form.setData(removeName, event.target.checked)} className="size-4 accent-brand-coral" />Remove uploaded {isCover ? 'cover' : 'hero artwork'}</label>}
                </div>
                {visiblePreview && <div className="overflow-hidden border border-ink-950/15 bg-brand-cream"><img src={visiblePreview} alt={previewUrl ? `Selected ${label.toLowerCase()} preview` : `Current curated ${label.toLowerCase()}`} className={`${isCover ? 'aspect-[2/3]' : 'aspect-video'} w-full object-cover`} /></div>}
            </div>
            <div className="my-4 flex items-center gap-3 text-[0.65rem] font-bold uppercase tracking-[0.16em] text-ink-950/45"><span className="h-px flex-1 bg-ink-950/10" /><span>Or</span><span className="h-px flex-1 bg-ink-950/10" /></div>
            <label htmlFor={urlName} className="text-xs font-semibold text-ink-950">External {isCover ? 'Cover URL' : 'Hero Artwork URL'}</label>
            <input id={urlName} type="url" value={form.data[urlName]} onChange={(event) => form.setData(urlName, event.target.value)} placeholder="https://..." className="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral" />
            <p className="mt-1.5 text-xs leading-5 text-ink-950/55">Uploaded {isCover ? 'cover takes priority over an external cover URL.' : 'hero artwork takes priority over an external hero URL.'}</p>
            <ApiValue value={literature.api_values[urlName]} />
            <FieldError message={form.errors[urlName]} />
        </div>
    );
}

export default function Edit({ literature, override, updateUrl, maxYear }) {
    const errorRef = useRef(null);
    const form = useForm({
        _method: 'PUT',
        ...Object.fromEntries([...metadataFields.map((field) => field.name), 'cover_url', 'backdrop_url', 'source_url', 'notes'].map((name) => [name, override?.[name] ?? ''])),
        cover_upload: null,
        remove_cover_upload: false,
        backdrop_upload: null,
        remove_backdrop_upload: false,
    });
    const coverPreview = useObjectUrl(form.data.cover_upload);
    const backdropPreview = useObjectUrl(form.data.backdrop_upload);
    const errors = Object.values(form.errors);

    useEffect(() => {
        if (errors.length) errorRef.current?.focus();
    }, [form.errors]);

    const submit = (event) => {
        event.preventDefault();
        form.post(updateUrl, { forceFormData: true, preserveScroll: true });
    };

    const reset = () => {
        router.post(updateUrl, { _method: 'PUT', reset: '1' }, { preserveScroll: true });
    };

    const effectiveCover = coverPreview || (form.data.remove_cover_upload ? form.data.cover_url || literature.api_values.cover_url : literature.effective.cover_url);
    const effectiveBackdrop = backdropPreview || (form.data.remove_backdrop_upload ? form.data.backdrop_url || literature.api_values.backdrop_url : literature.effective.backdrop_url);

    return (
        <>
            <Head title={`Curate ${literature.title}`} />
            <section className="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <div className="flex flex-col gap-4 border-b border-ink-950/15 pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div><p className="text-xs font-semibold uppercase tracking-[0.18em] text-brand-coral">Admin curation</p><h1 className="mt-2 text-3xl font-bold tracking-tight text-ink-950">Edit literature metadata</h1><p className="mt-2 text-sm text-ink-950/55">{literature.title} · {literature.source_name}</p></div>
                    <Link href={literature.url} className="text-sm font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Back to literature</Link>
                </div>

                <div className="mt-7 grid gap-7 lg:grid-cols-[minmax(0,1fr)_250px]">
                    <form id="literature-metadata-form" onSubmit={submit} encType="multipart/form-data" noValidate className="space-y-5">
                        {errors.length > 0 && <div ref={errorRef} role="alert" tabIndex="-1" className="border border-red-700/25 bg-red-50 px-4 py-3 text-sm text-red-800"><p className="font-bold">Metadata could not be saved. Please check the fields below.</p><ul className="mt-2 list-disc space-y-1 pl-5 text-xs leading-5">{errors.map((error, index) => <li key={index}>{error}</li>)}</ul></div>}
                        <div className="border border-ink-950/12 bg-white/45 p-5 sm:p-7">
                            <p className="text-sm leading-6 text-ink-900">Only filled fields override API metadata. Leave a field empty to inherit its current API value again.</p>
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <UploadField kind="cover" form={form} override={override} literature={literature} previewUrl={coverPreview} />
                                <UploadField kind="backdrop" form={form} override={override} literature={literature} previewUrl={backdropPreview} />
                                {metadataFields.map((field) => <div key={field.name} className={['tagline', 'synopsis'].includes(field.name) ? 'sm:col-span-2' : ''}>
                                    <label htmlFor={field.name} className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">{field.label}</label>
                                    {field.type === 'textarea' ? <textarea id={field.name} rows={field.name === 'synopsis' ? 7 : 3} value={form.data[field.name]} onChange={(event) => form.setData(field.name, event.target.value)} className="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm leading-6 text-ink-950 outline-none focus:border-brand-coral" /> : <input id={field.name} type={field.type} min={field.name === 'publication_year' ? 1 : undefined} max={field.name === 'publication_year' ? maxYear : undefined} value={form.data[field.name]} onChange={(event) => form.setData(field.name, event.target.value)} className="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral" />}
                                    <ApiValue value={literature.api_values[field.name]} /><FieldError message={form.errors[field.name]} />
                                </div>)}
                            </div>
                        </div>
                        <div className="border border-ink-950/12 bg-white/45 p-5 sm:p-7"><h2 className="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Curation provenance</h2><div className="mt-4 grid gap-5 sm:grid-cols-2"><div><label htmlFor="source_url" className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">Reference URL</label><input id="source_url" type="url" value={form.data.source_url} onChange={(event) => form.setData('source_url', event.target.value)} className="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral" /><FieldError message={form.errors.source_url} /></div><div><label htmlFor="notes" className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">Internal notes</label><textarea id="notes" rows="3" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral" /><FieldError message={form.errors.notes} /></div></div></div>
                        <div className="flex flex-wrap items-center gap-3"><button type="submit" disabled={form.processing} className="rounded-full bg-brand-coral px-5 py-3 text-sm font-bold text-white transition hover:bg-ink-950 disabled:cursor-wait disabled:opacity-60">{form.processing ? 'Saving…' : 'Save curated metadata'}</button>{override && <button type="button" onClick={reset} disabled={form.processing} className="rounded-full border border-ink-950/20 px-5 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral hover:text-brand-coral">Reset all to API</button>}</div>
                    </form>

                    <aside className="h-fit border border-ink-950/12 bg-brand-sky/15 p-5">
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-ink-950/55">Effective preview</p>
                        <div className="mt-4 aspect-video overflow-hidden border border-ink-950/15 bg-brand-cream">{effectiveBackdrop ? <img src={effectiveBackdrop} alt="" className="size-full object-cover" /> : effectiveCover && <img src={effectiveCover} alt="" className="size-full scale-110 object-cover blur-sm" />}</div>
                        <p className="mt-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-ink-950/45">Hero artwork</p>
                        <div className="mt-4 aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-cream">{effectiveCover && <img src={effectiveCover} alt="" className="size-full object-cover" />}</div>
                        <h2 className="mt-4 text-lg font-bold text-ink-950">{form.data.title || literature.effective.title}</h2>
                        <p className="mt-1 text-sm text-ink-950/55">{form.data.publication_year || literature.effective.year || 'Year unavailable'}</p>
                        {override && <p className="mt-4 border-t border-ink-950/10 pt-4 text-xs leading-5 text-ink-950/55">Last curated {override.updated_label}{override.editor_name ? ` by ${override.editor_name}` : ''}.</p>}
                    </aside>
                </div>
            </section>
        </>
    );
}
