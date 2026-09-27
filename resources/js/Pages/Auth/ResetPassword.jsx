import { Head, useForm } from '@inertiajs/react';

import SiteContainer from '../../Components/SiteContainer';

export default function ResetPassword({ token, email, routes }) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(routes.update, {
            preserveScroll: true,
            onSuccess: (page) => window.location.assign(page.url),
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Reset password" />
            <section className="catalog-grid min-h-[70vh] border-b border-ink-950/10">
                <SiteContainer className="py-14 sm:py-20">
                    <div className="mx-auto max-w-md">
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader account</p>
                        <h1 className="mt-3 text-3xl font-bold text-ink-950">Choose a new password</h1>
                        <p className="mt-2 text-sm leading-6 text-ink-950/60">Use the email address associated with this reset link.</p>

                        <form onSubmit={submit} className="mt-6 grid gap-4">
                            <div>
                                <label htmlFor="reset-email" className="text-sm font-semibold text-ink-950">Email address</label>
                                <input id="reset-email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoComplete="email" className="mt-2 h-10 w-full rounded-md border border-ink-950/15 bg-white px-3 text-sm text-ink-950 outline-none focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20" />
                                {form.errors.email && <p role="alert" className="mt-1 text-xs text-red-700">{form.errors.email}</p>}
                            </div>
                            <div>
                                <label htmlFor="reset-password" className="text-sm font-semibold text-ink-950">New password</label>
                                <input id="reset-password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required minLength="8" autoComplete="new-password" className="mt-2 h-10 w-full rounded-md border border-ink-950/15 bg-white px-3 text-sm text-ink-950 outline-none focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20" />
                                {form.errors.password && <p role="alert" className="mt-1 text-xs text-red-700">{form.errors.password}</p>}
                            </div>
                            <div>
                                <label htmlFor="reset-password-confirmation" className="text-sm font-semibold text-ink-950">Confirm new password</label>
                                <input id="reset-password-confirmation" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required minLength="8" autoComplete="new-password" className="mt-2 h-10 w-full rounded-md border border-ink-950/15 bg-white px-3 text-sm text-ink-950 outline-none focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20" />
                                {form.errors.password_confirmation && <p role="alert" className="mt-1 text-xs text-red-700">{form.errors.password_confirmation}</p>}
                            </div>
                            <button type="submit" disabled={form.processing} className="h-10 rounded-md bg-brand-coral px-5 text-sm font-bold text-ink-950 transition hover:bg-brand-coral/85 disabled:cursor-wait disabled:opacity-60">
                                {form.processing ? 'Resetting password…' : 'Reset password'}
                            </button>
                        </form>

                        <a href={routes.home_login} className="mt-5 inline-block text-sm font-semibold text-ink-950/65 underline decoration-brand-coral underline-offset-4 hover:text-ink-950">Back to sign in</a>
                    </div>
                </SiteContainer>
            </section>
        </>
    );
}
