import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import SiteContainer from '../../Components/SiteContainer';

export default function VerifyEmail({ email, status, expires_in: expiresIn, routes }) {
    const form = useForm({});
    const logout = useForm({});
    const [cooldown, setCooldown] = useState(0);

    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = window.setTimeout(() => setCooldown((value) => value - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [cooldown]);

    const resend = () => form.post(routes.resend, {
        preserveScroll: true,
        onSuccess: () => setCooldown(60),
        onError: () => setCooldown(60),
    });

    return (
        <>
            <Head title="Verify your email" />
            <section className="catalog-grid min-h-[70vh] border-b border-ink-950/10">
                <SiteContainer className="py-14 sm:py-20">
                    <div className="mx-auto flex max-w-md flex-col gap-5 rounded-xl border border-ink-950/10 bg-white/70 p-6 sm:p-8">
                        <div>
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-brand-coral">Reader account</p>
                            <h1 className="mt-2 font-serif text-2xl font-bold text-ink-950">Verify your email</h1>
                        </div>
                        <p className="text-sm leading-6 text-ink-950/65">
                            {status === 'verification-send-failed' ? 'We could not send a verification link to ' : 'We sent a verification link to '}<strong className="break-all font-semibold text-ink-950">{email}</strong>.
                            {' '}Verify your email before posting reviews, logging your reading, or using other interactive features.
                        </p>
                        <p className="text-xs leading-5 text-ink-950/55">The link expires in {expiresIn} minutes. Check your spam folder if you cannot find it. You can still browse public pages.</p>
                        {status === 'verification-link-sent' && <p role="status" className="rounded-md bg-brand-sky/20 px-3 py-2 text-sm text-ink-950">A new verification link has been sent.</p>}
                        {status === 'verification-send-failed' && <p role="alert" className="text-xs leading-5 text-red-700">Email delivery is temporarily unavailable. Please try resending the verification email.</p>}
                        {form.errors.verification && <p role="alert" className="text-xs text-red-700">{form.errors.verification}</p>}
                        <button type="button" onClick={resend} disabled={form.processing || cooldown > 0} className="min-h-10 rounded-md bg-brand-coral px-4 py-2 text-sm font-bold text-ink-950 transition hover:bg-brand-coral/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-not-allowed disabled:opacity-60">
                            {form.processing ? 'Sending…' : cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend verification email'}
                        </button>
                        <div className="flex flex-wrap justify-between gap-3 text-xs font-semibold text-ink-950/65">
                            <a href={routes.home} className="underline decoration-brand-coral underline-offset-4 hover:text-ink-950">Continue browsing</a>
                            <button type="button" disabled={logout.processing} onClick={() => logout.post(routes.logout)} className="hover:text-ink-950 disabled:opacity-60">Log out</button>
                        </div>
                    </div>
                </SiteContainer>
            </section>
        </>
    );
}
