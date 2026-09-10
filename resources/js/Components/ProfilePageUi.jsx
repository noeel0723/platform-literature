import { Link } from '@inertiajs/react';

export function LiteratureCover({ literature, className = '', loading = 'lazy', alt = true }) {
    return (
        <span className={`grid overflow-hidden border border-ink-950/15 bg-brand-sky/25 ${className}`}>
            {literature.cover_url ? (
                <img
                    src={literature.cover_url}
                    alt={alt ? `Cover of ${literature.title}` : ''}
                    className="size-full object-cover"
                    loading={loading}
                />
            ) : (
                <span className="grid size-full place-items-center p-2 text-center text-sm font-bold text-ink-950">
                    {literature.initials}
                </span>
            )}
        </span>
    );
}

export function ProfilePageHeading({ eyebrow, title, count, id }) {
    return (
        <div className="flex flex-col gap-2 border-b border-ink-950/20 pb-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">{eyebrow}</p>
                <h1 id={id} className="mt-1 text-2xl font-bold text-ink-950">{title}</h1>
            </div>
            <span className="text-sm text-ink-950/50">{count}</span>
        </div>
    );
}

function PaginationLink({ href, relation, children }) {
    const classes = 'inline-flex min-h-9 items-center border px-4 text-xs font-bold uppercase tracking-wider';

    if (!href) {
        return <span aria-disabled="true" className={`${classes} border-ink-950/10 text-ink-950/25`}>{children}</span>;
    }

    return (
        <Link
            href={href}
            rel={relation}
            preserveScroll
            className={`${classes} border-ink-950/20 bg-white/40 text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-white`}
        >
            {children}
        </Link>
    );
}

export function Pagination({ paginator, label }) {
    if (paginator.last_page <= 1) return null;

    return (
        <nav className="mt-8 flex items-center justify-between border-t border-ink-950/15 pt-4" aria-label={label}>
            <PaginationLink href={paginator.prev_page_url} relation="prev">Previous</PaginationLink>
            <span className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/45">
                Page {paginator.current_page} of {paginator.last_page}
            </span>
            <PaginationLink href={paginator.next_page_url} relation="next">Next</PaginationLink>
        </nav>
    );
}
