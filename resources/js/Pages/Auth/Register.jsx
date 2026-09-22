import { Head, useForm } from '@inertiajs/react';

import AuthPageShell, { FieldError } from '../../Components/AuthPageShell';

const inputClassName = 'mt-1.5 h-10 w-full rounded-sm border border-brand-sky/25 bg-brand-cream px-3 text-sm text-ink-950 outline-none transition focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/35';

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
                title="Create a Literahaven account"
                closeUrl={routes.home ?? routes.login}
                footer={{ label: 'Already have an account?', action: 'Log in', url: routes.login }}
            >
                <form onSubmit={submit} className="mt-6 grid gap-4">
                    <div>
                        <label htmlFor="email" className="text-sm font-semibold text-brand-plate">Email address</label>
                        <input id="email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoFocus autoComplete="email" className={inputClassName} />
                        <FieldError message={form.errors.email} />
                    </div>
                    <div>
                        <label htmlFor="name" className="text-sm font-semibold text-brand-plate">Name</label>
                        <input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoComplete="name" className={inputClassName} />
                        <FieldError message={form.errors.name} />
                    </div>
                    <div>
                        <label htmlFor="username" className="text-sm font-semibold text-brand-plate">Username</label>
                        <div className="mt-1.5 flex h-10 overflow-hidden rounded-sm border border-brand-sky/25 bg-brand-cream transition focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/35">
                            <span className="grid place-items-center border-r border-ink-950/10 px-3 text-sm font-semibold text-ink-950/45">@</span>
                            <input id="username" value={form.data.username} onChange={(event) => form.setData('username', event.target.value.toLowerCase())} required minLength="3" maxLength="50" pattern="[a-z0-9_]+" autoComplete="username" placeholder="imanuel_reader" className="min-w-0 flex-1 bg-transparent px-3 text-sm text-ink-950 outline-none" />
                        </div>
                        <p className="mt-1 text-xs text-brand-plate/60">Use lowercase letters, numbers, and underscores.</p>
                        <FieldError message={form.errors.username} />
                    </div>
                    <div>
                        <label htmlFor="password" className="text-sm font-semibold text-brand-plate">Password</label>
                        <input id="password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="new-password" className={inputClassName} />
                        <FieldError message={form.errors.password} />
                    </div>
                    <div>
                        <label htmlFor="password_confirmation" className="text-sm font-semibold text-brand-plate">Confirm password</label>
                        <input id="password_confirmation" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required autoComplete="new-password" className={inputClassName} />
                        <FieldError message={form.errors.password_confirmation} />
                    </div>
                    <button type="submit" disabled={form.processing} className="mt-1 h-10 rounded-sm bg-brand-coral px-5 text-sm font-bold text-ink-950 transition hover:bg-brand-coral/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-60">
                        {form.processing ? 'Creating account…' : 'Create account'}
                    </button>
                </form>
            </AuthPageShell>
        </>
    );
}
