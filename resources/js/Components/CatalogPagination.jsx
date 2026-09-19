import { Link } from '@inertiajs/react';

function PaginationLink({ href, relation, children }) {
    const sharedClasses = 'inline-flex min-h-10 items-center border px-5 text-sm font-semibold';

    if (!href) {
        return (
            <span
                className={`${sharedClasses} border-ink-950/10 text-ink-950/30`}
                aria-disabled="true"
            >
                {children}
            </span>
        );
    }

    return (
        <Link
            href={href}
            rel={relation}
            preserveScroll
            className={`${sharedClasses} border-ink-950/20 bg-white/45 text-ink-950 transition hover:border-brand-blue hover:bg-brand-blue hover:text-brand-cream`}
        >
            {children}
        </Link>
    );
}

export default function CatalogPagination({ pagination, label = 'Literature pagination' }) {
    return (
        <nav className="mt-9 flex items-center justify-between border-t border-ink-950/15 pt-5" aria-label={label}>
            <PaginationLink href={pagination.prev_page_url} relation="prev">Previous</PaginationLink>
            <span className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">
                Page {pagination.current_page} of {pagination.last_page}
            </span>
            <PaginationLink href={pagination.next_page_url} relation="next">Next</PaginationLink>
        </nav>
    );
}
