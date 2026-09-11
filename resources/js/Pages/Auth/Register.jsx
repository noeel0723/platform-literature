import { Head, useForm } from '@inertiajs/react';

import AuthPageShell, { FieldError } from '../../Components/AuthPageShell';

export default function Register({ routes }) {
    const form = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(routes.register, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Create account" />
            <AuthPageShell
                eyebrow="Join Literahaven"
                title="Create account"
                description="One account keeps your Readlist, reading progress, and activity history together."
                footer={{ label: 'Already have an account?', action: 'Log in', url: routes.login }}
            >
                <form onSubmit={submit} className="mt-8 grid gap-5">
                    <div>
                        <label htmlFor="name" className="text-sm font-bold text-ink-950">Name</label>
                        <input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoFocus autoComplete="name" className="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral" />
                        <FieldError message={form.errors.name} />
                    </div>
                    <div>
                        <label htmlFor="username" className="text-sm font-bold text-ink-950">Username</label>
                        <div className="mt-2 flex border border-ink-950/20 bg-brand-cream/40 focus-within:border-brand-coral">
                            <span className="grid place-items-center border-r border-ink-950/10 px-3 font-semibold text-ink-950/45">@</span>
                            <input id="username" value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} required minLength="3" maxLength="50" pattern="[a-z0-9_]+" autoComplete="username" placeholder="imanuel_reader" className="min-w-0 flex-1 bg-transparent px-4 py-3 outline-none" />
                        </div>
                        <p className="mt-2 text-xs text-ink-950/50">Use lowercase letters, numbers, and underscores.</p>
                        <FieldError message={form.errors.username} />
                    </div>
                    <div>
                        <label htmlFor="email" className="text-sm font-bold text-ink-950">Email</label>
                        <input id="email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoComplete="email" className="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral" />
                        <FieldError message={form.errors.email} />
                    </div>
                    <div>
                        <label htmlFor="password" className="text-sm font-bold text-ink-950">Password</label>
                        <input id="password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="new-password" className="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral" />
                        <FieldError message={form.errors.password} />
                    </div>
                    <div>
                        <label htmlFor="password_confirmation" className="text-sm font-bold text-ink-950">Confirm password</label>
                        <input id="password_confirmation" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required autoComplete="new-password" className="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral" />
                    </div>
                    <button type="submit" disabled={form.processing} className="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral disabled:cursor-wait disabled:opacity-60">
                        {form.processing ? 'Creating account…' : 'Create account'}
                    </button>
                </form>
            </AuthPageShell>
        </>
    );
}
