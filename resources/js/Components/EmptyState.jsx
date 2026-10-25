import { Link } from '@inertiajs/react';

export default function EmptyState({ as: Element = 'div', title, description, action, compact = false, className = '', children, ...props }) {
    return (
        <Element
            data-empty-state
            className={`flex min-w-0 flex-col items-center gap-1 text-center [overflow-wrap:anywhere] ${compact ? 'px-3 py-4' : 'rounded-lg border border-ink-950/10 bg-white/40 px-4 py-6 sm:px-6'} ${className}`}
            {...props}
        >
            <p className="max-w-lg text-sm font-semibold leading-6 text-ink-950">{title ?? children}</p>
            {description && <p className="max-w-lg text-[13px] leading-5 text-ink-950/70">{description}</p>}
            {action?.href && action?.label && (
                <Link href={action.href} className="mt-3 inline-flex min-h-9 max-w-full items-center justify-center rounded-md bg-ink-950 px-4 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                    {action.label}
                </Link>
            )}
        </Element>
    );
}
