import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { openLoginPanel } from '../Support/loginPanel';

const inputClassName = 'mt-1.5 h-10 w-full rounded-sm border border-brand-sky/25 bg-brand-cream px-3 text-sm text-ink-950 outline-none transition focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/35';

function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs font-semibold text-red-200" role="alert">{message}</p> : null;
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
            className="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto border border-brand-sky/15 bg-ink-900 p-5 text-brand-plate shadow-[0_20px_55px_rgba(7,17,32,0.28)] backdrop:bg-ink-950/75 sm:p-7"
        >
            <div className="flex items-start justify-between gap-4">
                <h2 id="register-dialog-title" className="pt-1 text-sm font-bold uppercase tracking-[0.16em] text-brand-plate sm:text-base">Create a Literahaven account</h2>
                <button type="button" onClick={dismiss} aria-label="Close create account" className="grid size-8 shrink-0 place-items-center text-2xl leading-none text-brand-plate/60 transition hover:text-brand-plate focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">×</button>
            </div>

            <form onSubmit={submit} className="mt-6 grid gap-4">
                <div>
                    <label htmlFor="register-email" className="text-sm font-semibold text-brand-plate">Email address</label>
                    <input ref={emailRef} id="register-email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoComplete="email" className={inputClassName} />
                    <FieldError message={form.errors.email} />
                </div>
                <div>
                    <label htmlFor="register-name" className="text-sm font-semibold text-brand-plate">Name</label>
                    <input id="register-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoComplete="name" className={inputClassName} />
                    <FieldError message={form.errors.name} />
                </div>
                <div>
                    <label htmlFor="register-username" className="text-sm font-semibold text-brand-plate">Username</label>
                    <div className="mt-1.5 flex h-10 overflow-hidden rounded-sm border border-brand-sky/25 bg-brand-cream transition focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/35">
                        <span className="grid place-items-center border-r border-ink-950/10 px-3 text-sm font-semibold text-ink-950/45">@</span>
                        <input id="register-username" value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} required minLength="3" maxLength="50" pattern="[a-z0-9_]+" autoComplete="username" placeholder="imanuel_reader" className="min-w-0 flex-1 bg-transparent px-3 text-sm text-ink-950 outline-none" />
                    </div>
                    <p className="mt-1 text-xs text-brand-plate/60">Use lowercase letters, numbers, and underscores.</p>
                    <FieldError message={form.errors.username} />
                </div>
                <div>
                    <label htmlFor="register-password" className="text-sm font-semibold text-brand-plate">Password</label>
                    <input id="register-password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="new-password" className={inputClassName} />
                    <FieldError message={form.errors.password} />
                </div>
                <div>
                    <label htmlFor="register-password-confirmation" className="text-sm font-semibold text-brand-plate">Confirm password</label>
                    <input id="register-password-confirmation" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required autoComplete="new-password" className={inputClassName} />
                    <FieldError message={form.errors.password_confirmation} />
                </div>
                <button type="submit" disabled={form.processing} className="mt-1 h-10 rounded-sm bg-brand-coral px-5 text-sm font-bold text-ink-950 transition hover:bg-brand-coral/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-60">
                    {form.processing ? 'Creating account…' : 'Create account'}
                </button>
            </form>

            <p className="mt-5 text-center text-xs text-brand-plate/70 sm:text-sm">
                Already have an account?{' '}
                <button type="button" onClick={openLogin} className="font-bold text-brand-plate underline decoration-brand-coral decoration-2 underline-offset-4 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">Log in</button>
            </p>
        </dialog>
    );
}
