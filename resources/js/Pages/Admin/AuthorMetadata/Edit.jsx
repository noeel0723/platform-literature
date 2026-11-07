import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import SiteContainer from '../../../Components/SiteContainer';

const inputClass = 'mt-2 w-full rounded-md border border-ink-950/20 bg-brand-cream/40 px-3 py-2 text-sm text-ink-950 outline-none focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/15';

function FieldError({ name, message }) {
    return message ? <p id={`${name}-error`} className="mt-1 text-xs text-red-700">{message}</p> : null;
}

export default function EditAuthor({ author, updateUrl }) {
    const errorRef = useRef(null);
    const [imagePreview, setImagePreview] = useState(null);
    const form = useForm({
        _method: 'PUT',
        biography_override: author.biography_override ?? '',
        image_url_override: author.image_url_override ?? '',
        image_upload: null,
        remove_image_upload: false,
    });

    useEffect(() => {
        if (!form.data.image_upload) {
            setImagePreview(null);
            return;
        }
        const url = URL.createObjectURL(form.data.image_upload);
        setImagePreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.image_upload]);

    const submit = (event) => {
        event.preventDefault();
        if (form.processing) return;
        form.post(updateUrl, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => errorRef.current?.focus(),
            onNetworkError: () => form.setError('save', 'The changes could not be sent. Please try again.'),
            onHttpException: () => form.setError('save', 'The changes could not be saved. Please try again.'),
        });
    };
    const currentUpload = form.data.remove_image_upload ? null : author.uploaded_image_url;
    const portrait = imagePreview || currentUpload || form.data.image_url_override || author.api_image_url;
    const errors = Object.values(form.errors);

    return (
        <>
            <Head title={`Edit ${author.name}`} />
            <SiteContainer as="main" className="max-w-4xl py-8 sm:py-10">
                <header className="flex flex-wrap items-end justify-between gap-3 border-b border-ink-950/15 pb-4">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-coral">Admin curation</p>
                        <h1 className="mt-1 font-serif text-2xl font-bold text-ink-950">Edit author</h1>
                        <p className="mt-1 text-sm text-ink-950/60">{author.name}</p>
                    </div>
                    <Link href={author.url} className="text-xs font-semibold text-ink-950/60 hover:text-brand-coral">Back to author</Link>
                </header>
                <form onSubmit={submit} encType="multipart/form-data" noValidate className="mt-5 space-y-5">
                    {errors.length > 0 && <div ref={errorRef} role="alert" tabIndex={-1} className="rounded-md border border-red-700/20 bg-red-50 p-3 text-xs leading-5 text-red-800">
                        <p className="font-semibold">Author details could not be saved.</p>
                        <ul className="mt-1 list-disc pl-4">{errors.map((error, index) => <li key={index}>{error}</li>)}</ul>
                    </div>}
                    <div className="grid gap-6 rounded-xl border border-ink-950/12 bg-white/50 p-5 sm:p-6 md:grid-cols-[minmax(0,1fr)_170px]">
                        <div className="min-w-0 space-y-5">
                            <div>
                                <label htmlFor="author-biography" className="text-xs font-semibold text-ink-950">Description</label>
                                <textarea id="author-biography" rows={7} maxLength={20000} value={form.data.biography_override} onChange={(event) => form.setData('biography_override', event.target.value)} aria-invalid={Boolean(form.errors.biography_override)} aria-describedby={`author-biography-help${form.errors.biography_override ? ' biography_override-error' : ''}`} className={`${inputClass} min-h-36 leading-6`} />
                                <p id="author-biography-help" className="mt-1 text-xs leading-5 text-ink-950/50">Leave empty to use the description from the metadata sources.</p>
                                <FieldError name="biography_override" message={form.errors.biography_override} />
                                {author.api_biography && <details className="mt-2 text-xs text-ink-950/55"><summary className="cursor-pointer">Original description</summary><p className="mt-2 whitespace-pre-line leading-5">{author.api_biography}</p></details>}
                            </div>
                            <div>
                                <label htmlFor="author-image" className="text-xs font-semibold text-ink-950">Upload portrait</label>
                                <input id="author-image" type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => form.setData('image_upload', event.target.files?.[0] ?? null)} aria-invalid={Boolean(form.errors.image_upload)} aria-describedby={`author-image-help${form.errors.image_upload ? ' image_upload-error' : ''}`} className={`${inputClass} file:mr-2 file:rounded file:border-0 file:bg-brand-sky/25 file:px-2 file:py-1 file:text-xs file:text-ink-950`} />
                                <p id="author-image-help" className="mt-1 text-xs text-ink-950/50">JPG, PNG, or WebP. Maximum 5 MB.</p>
                                <FieldError name="image_upload" message={form.errors.image_upload} />
                                {author.uploaded_image_url && <label className="mt-3 flex items-center gap-2 text-xs text-ink-950/70"><input type="checkbox" checked={form.data.remove_image_upload} onChange={(event) => form.setData('remove_image_upload', event.target.checked)} className="size-4 accent-brand-coral" />Remove uploaded portrait</label>}
                            </div>
                            <div>
                                <label htmlFor="author-image-url" className="text-xs font-semibold text-ink-950">Or use a portrait URL</label>
                                <input id="author-image-url" type="url" maxLength={4096} value={form.data.image_url_override} onChange={(event) => form.setData('image_url_override', event.target.value)} placeholder="https://..." aria-invalid={Boolean(form.errors.image_url_override)} aria-describedby={form.errors.image_url_override ? 'image_url_override-error' : undefined} className={`${inputClass} h-10`} />
                                <FieldError name="image_url_override" message={form.errors.image_url_override} />
                                <p className="mt-1 text-xs leading-5 text-ink-950/50">An uploaded portrait takes priority. Clear the URL and remove the upload to use the original photo.</p>
                            </div>
                        </div>
                        <aside className="min-w-0">
                            <p className="text-[10px] font-bold uppercase tracking-wide text-ink-950/50">Portrait preview</p>
                            <div className="mt-2 aspect-[4/5] max-w-44 overflow-hidden rounded-md border border-ink-950/15 bg-brand-sky/20">
                                {portrait ? <img src={portrait} alt={`Portrait preview of ${author.name}`} className="size-full object-cover" /> : <div className="grid size-full place-items-center p-3 text-center text-xs text-ink-950/50">No portrait available</div>}
                            </div>
                        </aside>
                    </div>
                    {form.progress && <p role="status" className="text-xs text-ink-950/60">Uploading portrait… {form.progress.percentage}%</p>}
                    <div className="flex items-center gap-4">
                        <button type="submit" disabled={form.processing} className="h-10 rounded-md bg-ink-950 px-4 text-sm font-semibold text-brand-cream hover:bg-brand-coral disabled:cursor-wait disabled:opacity-50">{form.processing ? 'Saving…' : 'Save changes'}</button>
                        <Link href={author.url} className="text-xs font-semibold text-ink-950/55 hover:text-brand-coral">Cancel</Link>
                    </div>
                </form>
            </SiteContainer>
        </>
    );
}
