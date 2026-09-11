import { Link } from '@inertiajs/react';

export function FieldError({ message }) {
    return message ? <p className="mt-2 text-sm font-semibold text-red-700">{message}</p> : null;
}

export default function AuthPageShell({ eyebrow, title, description, footer, children }) {
    return (
        <section className="catalog-grid min-h-[70vh] border-b border-ink-950/10 px-5 py-14 sm:px-8 lg:py-20">
            <div className="mx-auto max-w-md border border-ink-950/15 bg-white/45 p-7 shadow-[0_12px_32px_rgba(47,58,85,0.08)] sm:p-9">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">{eyebrow}</p>
                <h1 className="mt-3 text-4xl font-bold text-ink-950">{title}</h1>
                <p className="mt-3 leading-7 text-ink-950/65">{description}</p>
                {children}
                <p className="mt-6 text-sm text-ink-950/65">
                    {footer.label}{' '}
                    <Link href={footer.url} className="font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4">
                        {footer.action}
                    </Link>
                </p>
            </div>
        </section>
    );
}
