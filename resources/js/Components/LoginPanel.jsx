import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import SiteContainer from './SiteContainer';

function FieldError({ message }) {
    return message ? <p className="mt-1 text-[0.68rem] font-semibold text-red-300">{message}</p> : null;
}

export default function LoginPanel({ routes, csrfToken, onClose, embedded = false, inline = false }) {
    const panelRef = useRef(null);
    const identifierRef = useRef(null);
    const form = useForm({
        _token: csrfToken ?? '',
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        const previousFocus = document.activeElement;
        identifierRef.current?.focus();

        const handleKeyDown = (event) => {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            onClose?.();
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            previousFocus?.focus?.();
        };
    }, [onClose]);

    const submit = (event) => {
        event.preventDefault();
        form.post(routes.login, {
            preserveScroll: true,
            onSuccess: (page) => window.location.assign(page.url),
            onFinish: () => form.reset('password'),
        });
    };

    const formContent = (
        <div ref={panelRef} role="dialog" aria-modal="false" aria-label="Sign in to Literahaven" className={inline ? 'min-w-0 flex-1 border-y border-white/8 bg-[#172333] px-3 py-2.5 lg:border-0 lg:bg-transparent lg:p-0' : `w-full border-y border-white/8 bg-[#172333] px-3 py-2.5 md:w-fit md:max-w-[820px] ${embedded ? '' : 'shadow-[0_8px_18px_rgba(7,17,32,0.18)]'}`}>
            <form onSubmit={submit} className={inline ? 'relative grid min-w-0 gap-2 lg:grid-cols-[2rem_minmax(140px,1fr)_minmax(140px,1fr)_auto_auto] lg:items-end' : 'relative grid gap-2 md:grid-cols-[2rem_240px_220px_auto_auto] md:items-end'}>
                <button type="button" onClick={onClose} className={inline ? 'absolute right-0 top-0 grid size-8 place-items-center text-xl text-brand-plate/55 transition hover:bg-white/8 hover:text-white lg:static lg:mb-0.5' : 'absolute right-0 top-0 grid size-8 place-items-center text-xl text-brand-plate/55 transition hover:bg-white/8 hover:text-white md:static md:mb-0.5'} aria-label="Close sign in panel">×</button>

                <div className={`min-w-0 ${inline ? '' : 'pr-10 md:pr-0'}`}>
                    <label htmlFor={`${embedded ? 'page' : 'header'}-login-identifier`} className="block text-[0.64rem] font-semibold uppercase tracking-[0.12em] text-brand-plate/60">Username/Email</label>
                    <input ref={identifierRef} id={`${embedded ? 'page' : 'header'}-login-identifier`} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoComplete="username" className="mt-1 h-9 w-full rounded-sm border border-white/10 bg-brand-cream px-3 text-sm text-ink-950 outline-none transition focus:border-[#00c030]" />
                    <FieldError message={form.errors.email} />
                </div>

                <div className="min-w-0">
                    <div className="flex items-center justify-between gap-3">
                        <label htmlFor={`${embedded ? 'page' : 'header'}-login-password`} className="text-[0.64rem] font-semibold uppercase tracking-[0.12em] text-brand-plate/60">Password</label>
                        <span className="whitespace-nowrap text-[0.64rem] font-semibold text-[#00c030]" aria-disabled="true" title="Password recovery is not configured yet">Forgot Password?</span>
                    </div>
                    <input id={`${embedded ? 'page' : 'header'}-login-password`} type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="current-password" className="mt-1 h-9 w-full rounded-sm border border-white/10 bg-brand-cream px-3 text-sm text-ink-950 outline-none transition focus:border-[#00c030]" />
                    <FieldError message={form.errors.password} />
                </div>

                <label className="mb-1.5 flex min-h-8 items-center gap-2 whitespace-nowrap text-xs text-brand-plate/65">
                    <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} className="size-4 accent-[#00c030]" />
                    Remember me
                </label>

                <button type="submit" disabled={form.processing} className="h-9 rounded-sm bg-[#00c030] px-4 text-[0.7rem] font-extrabold uppercase tracking-[0.08em] text-white transition-colors hover:bg-[#00a628] disabled:cursor-wait disabled:opacity-55">
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </div>
    );

    if (inline) {
        return <section className="flex min-w-0 flex-1 text-brand-plate">{formContent}</section>;
    }

    return (
        <section className="text-brand-plate">
            <SiteContainer className="flex justify-end px-0 sm:px-8">
                {formContent}
            </SiteContainer>
        </section>
    );
}
