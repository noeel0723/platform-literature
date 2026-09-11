import { Head, useForm } from '@inertiajs/react';

import AuthPageShell, { FieldError } from '../../Components/AuthPageShell';

export default function Login({ routes }) {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(routes.login, {
            onFinish: () => form.reset('password'),
        });
    };

    return (
        <>
            <Head title="Log in" />
            <AuthPageShell
                eyebrow="Reader account"
                title="Log in"
                description="Continue your Readlist, progress, and Personal Diary."
                footer={{ label: 'New to Literahaven?', action: 'Create an account', url: routes.register }}
            >
                <form onSubmit={submit} className="mt-8 grid gap-5">
                    <div>
                        <label htmlFor="email" className="text-sm font-bold text-ink-950">Email</label>
                        <input id="email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoFocus autoComplete="email" className="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral" />
                        <FieldError message={form.errors.email} />
                    </div>
                    <div>
                        <label htmlFor="password" className="text-sm font-bold text-ink-950">Password</label>
                        <input id="password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="current-password" className="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral" />
                        <FieldError message={form.errors.password} />
                    </div>
                    <label className="flex items-center gap-3 text-sm text-ink-950/70">
                        <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} className="size-4 accent-brand-coral" />
                        Remember me
                    </label>
                    <button type="submit" disabled={form.processing} className="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral disabled:cursor-wait disabled:opacity-60">
                        {form.processing ? 'Logging in…' : 'Log in'}
                    </button>
                </form>
            </AuthPageShell>
        </>
    );
}
