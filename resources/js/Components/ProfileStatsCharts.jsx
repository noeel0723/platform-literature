import EmptyState from './EmptyState';

export function StatsSection({ title, id, aside, children, className = '' }) {
    return (
        <section aria-labelledby={id} className={`min-w-0 ${className}`}>
            <header className="flex flex-wrap items-center gap-x-4 gap-y-3">
                <h2 id={id} className="text-[11px] font-bold uppercase tracking-[0.15em] text-ink-950">{title}</h2>
                <span aria-hidden="true" className="hidden h-px min-w-4 flex-1 bg-ink-950/15 sm:block" />
                {aside}
            </header>
            <div className="mt-5">{children}</div>
        </section>
    );
}

export function ChartTabs({ items, active, onChange, label }) {
    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2" role="group" aria-label={label}>
            {items.map((item) => (
                <button key={item.key} type="button" aria-pressed={item.key === active} onClick={() => onChange(item.key)} className={`border-b-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.1em] transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-coral ${item.key === active ? 'border-brand-coral text-ink-950' : 'border-transparent text-ink-950/50 hover:text-ink-950'}`}>
                    {item.label}
                </button>
            ))}
        </div>
    );
}

export function VerticalBarChart({ items, label, unit = 'completed', tone = 'bg-brand-coral', compact = false, emptyTitle = 'No completed literature for this period.' }) {
    const maximum = Math.max(1, ...items.map((item) => item.value));
    if (!items.some((item) => item.value > 0)) return <EmptyState compact title={emptyTitle} />;

    return (
        <div role="group" aria-label={label}>
            <ol className={`grid items-end gap-1.5 border-b border-ink-950/20 px-1 sm:gap-3 ${compact ? 'h-28' : 'h-44 sm:h-48'}`} style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))`, backgroundImage: 'linear-gradient(to top, rgb(16 47 98 / 0.05) 1px, transparent 1px)', backgroundSize: '100% 25%' }}>
                {items.map((item) => (
                    <li key={item.label} className="flex h-full min-w-0 items-end justify-center">
                        <span role="img" tabIndex={item.value > 0 ? 0 : undefined} aria-label={`${item.label}: ${item.value} ${unit}`} title={`${item.label}: ${item.value} ${unit}`} className={`group relative block w-full max-w-10 rounded-t-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink-950 ${tone}`} style={{ height: `${item.value > 0 ? Math.max(2, (item.value / maximum) * 85) : 0}%` }}>
                            <span aria-hidden="true" className="pointer-events-none absolute -top-6 left-1/2 -translate-x-1/2 rounded-sm bg-ink-950 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-brand-cream opacity-0 transition-opacity group-hover:opacity-100 group-focus:opacity-100">{item.value}</span>
                        </span>
                    </li>
                ))}
            </ol>
            <div aria-hidden="true" className="mt-2 grid gap-1.5 px-1 text-center text-[9px] font-medium tabular-nums text-ink-950/55 sm:gap-3 sm:text-[10px]" style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }}>
                {items.map((item, index) => <span key={item.label} className="min-w-0 truncate">{items.length <= 16 || index === 0 || index === items.length - 1 ? item.label : ''}</span>)}
            </div>
        </div>
    );
}

export function HorizontalBarList({ items, tone = 'bg-brand-sky/30', emptyTitle = 'No completed literature yet.' }) {
    const maximum = Math.max(1, ...items.map((item) => Number(item.count)));
    if (!items.some((item) => item.count > 0)) return <EmptyState compact title={emptyTitle} />;

    return (
        <ol className="grid gap-1.5">
            {items.map((item) => (
                <li key={item.label} className="relative flex min-h-8 min-w-0 items-center justify-between gap-2 px-2.5 py-1.5 text-xs">
                    <span aria-hidden="true" className={`absolute inset-y-0 left-0 rounded-sm ${tone}`} style={{ width: `${(item.count / maximum) * 100}%` }} />
                    <span className="relative min-w-0 truncate font-medium text-ink-950/80" title={item.label}>{item.label}</span>
                    <span className="relative shrink-0 text-[11px] font-semibold tabular-nums text-ink-950/65">{item.count}<span className="sr-only"> completed literature</span></span>
                </li>
            ))}
        </ol>
    );
}
