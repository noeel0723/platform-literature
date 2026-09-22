import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { openLoginPanel } from '../Support/loginPanel';

const inputClassName = 'mt-1 h-10 w-full rounded-md border border-ink-950/15 bg-white px-3 text-sm text-ink-950 outline-none transition placeholder:text-ink-950/35 focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20';

function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs font-medium leading-4 text-red-700" role="alert">{message}</p> : null;
}

export default function RegisterDialog({ open, onClose, registerUrl, returnFocusRef }) {
    const dialogRef = useRef(null);
    const emailRef = useRef(null);
    const skipReturnFocusRef = useRef(false);
    const form = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        if (!open) return;

        const dialog = dialogRef.current;
        const previousOverflow = document.body.style.overflow;
        dialog.showModal();
        document.body.style.overflow = 'hidden';
        emailRef.current?.focus();

        return () => {
            dialog.close();
            document.body.style.overflow = previousOverflow;
            if (!skipReturnFocusRef.current) returnFocusRef.current?.focus();
            skipReturnFocusRef.current = false;
        };
    }, [open, returnFocusRef]);

    const dismiss = () => {
        form.clearErrors();
        onClose();
    };

    const openLogin = () => {
        skipReturnFocusRef.current = true;
        dismiss();
        requestAnimationFrame(openLoginPanel);
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(registerUrl, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby="register-dialog-title"
            onCancel={(event) => {
                event.preventDefault();
                dismiss();
            }}
            onClick={(event) => {
                if (event.target === dialogRef.current) dismiss();
            }}
            className="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-[420px] overflow-y-auto rounded-xl border border-ink-950/10 bg-brand-cream p-5 text-ink-950 shadow-xl shadow-ink-950/15 backdrop:bg-black/45 backdrop:backdrop-blur-sm sm:p-6"
        >
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-coral">Create account</p>
                    <h2 id="register-dialog-title" className="mt-1 font-serif text-2xl font-semibold leading-tight text-ink-950">Join Literahaven</h2>
                </div>
                <button type="button" onClick={dismiss} aria-label="Close create account" className="grid size-7 shrink-0 place-items-center text-xl leading-none text-ink-950/45 transition hover:text-ink-950 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">×</button>
            </div>

            <form onSubmit={submit} className="mt-5 grid gap-3.5">
                <div>
                    <label htmlFor="register-email" className="text-xs font-semibold text-ink-950">Email address</label>
                    <input ref={emailRef} id="register-email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoComplete="email" placeholder="you@example.com" className={inputClassName} />
                    <FieldError message={form.errors.email} />
                </div>
                <div>
                    <label htmlFor="register-name" className="text-xs font-semibold text-ink-950">Name</label>
                    <input id="register-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoComplete="name" placeholder="Imanuel Reader" className={inputClassName} />
                    <FieldError message={form.errors.name} />
                </div>
                <div>
                    <label htmlFor="register-username" className="text-xs font-semibold text-ink-950">Username</label>
                    <div className="mt-1 flex h-10 overflow-hidden rounded-md border border-ink-950/15 bg-white transition focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/20">
                        <span className="grid place-items-center border-r border-ink-950/10 px-3 text-sm font-semibold text-ink-950/45">@</span>
                        <input id="register-username" value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} required minLength="3" maxLength="50" pattern="[a-z0-9_]+" autoComplete="username" placeholder="imanuel_reader" className="min-w-0 flex-1 bg-transparent px-3 text-sm text-ink-950 outline-none placeholder:text-ink-950/35" />
                    </div>
                    <p className="mt-1 text-[11px] leading-4 text-ink-950/55">Use lowercase letters, numbers, and underscores.</p>
                    <FieldError message={form.errors.username} />
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="min-w-0">
                        <label htmlFor="register-password" className="text-xs font-semibold text-ink-950">Password</label>
                        <input id="register-password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="new-password" className={inputClassName} />
                        <FieldError message={form.errors.password} />
                    </div>
                    <div className="min-w-0">
                        <label htmlFor="register-password-confirmation" className="text-xs font-semibold text-ink-950">Confirm password</label>
                        <input id="register-password-confirmation" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required autoComplete="new-password" className={inputClassName} />
                        <FieldError message={form.errors.password_confirmation} />
                    </div>
                </div>
                <button type="submit" disabled={form.processing} className="mt-1 h-10 w-full rounded-md bg-brand-coral px-5 text-sm font-bold text-ink-950 transition hover:bg-brand-coral/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-60">
                    {form.processing ? 'Creating account…' : 'Create account'}
                </button>
            </form>

            <p className="mt-4 text-center text-xs text-ink-950/60">
                Already have an account?{' '}
                <button type="button" onClick={openLogin} className="font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">Sign in</button>
            </p>
        </dialog>
    );
}
