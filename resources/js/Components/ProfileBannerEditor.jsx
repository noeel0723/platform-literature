import { useEffect, useState } from 'react';

export default function ProfileBannerEditor({ profile, form }) {
    const [preview, setPreview] = useState(profile.banner_url);
    const [selectionError, setSelectionError] = useState('');

    useEffect(() => {
        if (!form.data.banner) {
            setPreview(form.data.remove_banner ? null : profile.banner_url);
            return;
        }

        const url = URL.createObjectURL(form.data.banner);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.banner, form.data.remove_banner, profile.banner_url]);

    const selectBanner = (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            setSelectionError('Choose a JPG, PNG, or WebP image under 5 MB.');
            return;
        }

        setSelectionError('');
        form.clearErrors('banner');
        form.setData({ ...form.data, banner: file, remove_banner: false, banner_position_x: 50, banner_position_y: 50 });
    };

    return (
        <section aria-labelledby="profile-banner-heading" className="grid min-w-0 gap-3">
            <h3 id="profile-banner-heading" className="text-sm font-bold text-ink-950">Profile banner</h3>
            <div className="relative aspect-[3/1] overflow-hidden rounded-md border border-ink-950/15 bg-brand-sky/15">
                {preview ? <img src={preview} alt="Profile banner preview" className="size-full object-cover" style={{ objectPosition: `${form.data.banner_position_x}% ${form.data.banner_position_y}%` }} /> : <span className="grid size-full place-items-center px-3 text-center text-xs text-ink-700">Add a landscape image to personalize your profile.</span>}
            </div>
            <div>
                <label htmlFor="profile-banner-file" className="sr-only">Choose profile banner</label>
                <input id="profile-banner-file" type="file" accept="image/jpeg,image/png,image/webp" onChange={selectBanner} disabled={form.processing} aria-describedby="profile-banner-help" className="block w-full min-w-0 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-ink-950 file:px-3 file:py-2 file:font-semibold file:text-brand-cream disabled:opacity-50" />
                <p id="profile-banner-help" className="mt-1.5 text-xs leading-5 text-ink-700">JPG, PNG, or WebP under 5 MB, up to 6000 × 6000 px. A wide image around 1500 × 500 px works best.</p>
            </div>
            {preview && <fieldset disabled={form.processing} className="grid grid-cols-1 gap-3 text-xs font-semibold text-ink-700 sm:grid-cols-2">
                <legend className="sr-only">Adjust banner position</legend>
                <label className="grid gap-1.5">Horizontal position<input type="range" min="0" max="100" step="1" value={form.data.banner_position_x} onChange={(event) => form.setData('banner_position_x', Number(event.target.value))} className="w-full accent-brand-coral" /></label>
                <label className="grid gap-1.5">Vertical position<input type="range" min="0" max="100" step="1" value={form.data.banner_position_y} onChange={(event) => form.setData('banner_position_y', Number(event.target.value))} className="w-full accent-brand-coral" /></label>
            </fieldset>}
            {preview && <button type="button" disabled={form.processing} onClick={() => { setSelectionError(''); form.clearErrors('banner'); form.setData({ ...form.data, banner: null, remove_banner: profile.has_banner, banner_position_x: 50, banner_position_y: 50 }); }} className="w-fit text-xs font-semibold text-brand-coral underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50">Remove banner</button>}
            {form.data.remove_banner && profile.has_banner && <button type="button" disabled={form.processing} onClick={() => form.setData({ ...form.data, remove_banner: false, banner_position_x: profile.banner_position_x ?? 50, banner_position_y: profile.banner_position_y ?? 50 })} className="w-fit text-xs font-semibold text-ink-700 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-brand-coral">Restore current banner</button>}
            {[selectionError, form.errors.banner, form.errors.banner_position_x, form.errors.banner_position_y].filter(Boolean).map((error, index) => <p key={`${index}-${error}`} role="alert" className="text-xs font-semibold text-red-700">{error}</p>)}
        </section>
    );
}
