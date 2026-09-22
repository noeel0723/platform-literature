import { Link } from '@inertiajs/react';

export function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs font-semibold text-red-200" role="alert">{message}</p> : null;
}

export default function AuthPageShell({ title, closeUrl, footer, children }) {
    return (
        <section className="catalog-grid relative isolate flex min-h-[calc(100vh-8rem)] items-center justify-center overflow-hidden px-4 py-8 sm:px-8 sm:py-12">
            <div className="absolute inset-0 -z-10 bg-ink-950/85" aria-hidden="true" />
            <div className="w-full max-w-lg border border-brand-sky/15 bg-ink-900 p-5 text-brand-plate shadow-[0_20px_55px_rgba(7,17,32,0.28)] sm:p-7">
                <div className="flex items-start justify-between gap-4">
                    <h1 className="pt-1 text-sm font-bold uppercase tracking-[0.16em] text-brand-plate sm:text-base">{title}</h1>
                    <Link href={closeUrl} aria-label="Close create account" className="grid size-8 shrink-0 place-items-center text-2xl leading-none text-brand-plate/60 transition hover:text-brand-plate focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">×</Link>
                </div>
                {children}
                <p className="mt-5 text-center text-xs text-brand-plate/70 sm:text-sm">
                    {footer.label}{' '}
                    <Link href={footer.url} className="font-bold text-brand-plate underline decoration-brand-coral decoration-2 underline-offset-4 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                        {footer.action}
                    </Link>
                </p>
            </div>
        </section>
    );
}
