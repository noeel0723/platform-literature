import { Head, useForm } from '@inertiajs/react';

import SiteContainer from '../../Components/SiteContainer';

export default function ForgotPassword({ status, uses_log_mailer: usesLogMailer, routes }) {
    const form = useForm({ email: '' });

    const submit = (event) => {
        event.preventDefault();
        form.post(routes.email, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Forgot password" />
            <section className="catalog-grid min-h-[70vh] border-b border-ink-950/10">
                <SiteContainer className="py-14 sm:py-20">
                    <div className="mx-auto max-w-md">
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader account</p>
                        <h1 className="mt-3 text-3xl font-bold text-ink-950">Reset your password</h1>
                        <p className="mt-2 text-sm leading-6 text-ink-950/60">
                            Enter the email address on your account to request a secure reset link.
                        </p>
                        {usesLogMailer && (
                            <p className="mt-3 text-sm leading-6 text-ink-950/60">
                                For this temporary local setup, the link is saved in the application log instead of sent through Gmail. Ask the site administrator for your link.
                            </p>
                        )}
                        {status && <p role="status" className="mt-5 rounded-md border border-brand-sky/50 bg-brand-sky/15 px-4 py-3 text-sm text-ink-950">{status}</p>}

                        <form onSubmit={submit} className="mt-6">
                            <label htmlFor="reset-request-email" className="text-sm font-semibold text-ink-950">Email address</label>
                            <input
                                id="reset-request-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                required
                                autoComplete="email"
                                className="mt-2 h-10 w-full rounded-md border border-ink-950/15 bg-white px-3 text-sm text-ink-950 outline-none focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20"
                            />
                            {form.errors.email && <p role="alert" className="mt-1 text-xs text-red-700">{form.errors.email}</p>}
                            <button type="submit" disabled={form.processing} className="mt-4 h-10 rounded-md bg-brand-coral px-5 text-sm font-bold text-ink-950 transition hover:bg-brand-coral/85 disabled:cursor-wait disabled:opacity-60">
                                {form.processing ? 'Preparing link…' : 'Request reset link'}
                            </button>
                        </form>

                        <a href={routes.home_login} className="mt-5 inline-block text-sm font-semibold text-ink-950/65 underline decoration-brand-coral underline-offset-4 hover:text-ink-950">Back to sign in</a>
                    </div>
                </SiteContainer>
            </section>
        </>
    );
}
