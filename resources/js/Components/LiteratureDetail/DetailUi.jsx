import { Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export function SectionHeading({ as: Tag = 'h2', id, children }) {
    return (
        <Tag id={id} className="font-serif text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">
            {children}
        </Tag>
    );
}

export function StarRating({ rating = 0, size = 'text-lg' }) {
    return (
        <span className={`inline-flex ${size}`} aria-label={`${Number(rating).toFixed(1)} out of 5 stars`}>
            {Array.from({ length: 5 }, (_, index) => {
                const fill = Math.max(0, Math.min(1, Number(rating) - index));

                return (
                    <span key={index} className="relative text-ink-950/20" aria-hidden="true">
                        ★
                        {fill > 0 && (
                            <span className="absolute inset-y-0 left-0 overflow-hidden text-brand-coral" style={{ width: `${fill * 100}%` }}>
                                ★
                            </span>
                        )}
                    </span>
                );
            })}
        </span>
    );
}

export function RatingInput({ value, onChange, compact = false, onCommit }) {
    const [hovered, setHovered] = useState(null);
    const preview = hovered ?? Number(value || 0);

    return (
        <div>
            <div
                className="flex"
                role="radiogroup"
                aria-label="Choose your rating from 0.5 to 5 stars"
                onMouseLeave={() => setHovered(null)}
            >
                {Array.from({ length: 10 }, (_, index) => {
                    const halfStep = index + 1;
                    const rating = halfStep / 2;
                    const selected = preview >= rating;

                    return (
                        <button
                            key={rating}
                            type="button"
                            data-rating-value={rating}
                            className={`${compact ? 'h-10 w-4 text-3xl leading-10' : 'h-14 w-6 text-5xl leading-14'} overflow-hidden text-left transition focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral ${selected ? 'text-brand-coral' : 'text-ink-950/45'}`}
                            role="radio"
                            aria-checked={Number(value) === rating}
                            aria-label={`${rating.toFixed(1)} out of 5 stars`}
                            onMouseEnter={() => setHovered(rating)}
                            onFocus={() => setHovered(rating)}
                            onBlur={() => setHovered(null)}
                            onClick={() => {
                                onChange(rating);
                                onCommit?.(rating);
                            }}
                        >
                            <span className={`block ${compact ? 'w-8' : 'w-12'} ${halfStep % 2 === 0 ? '-translate-x-1/2' : ''}`}>★</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export function TimeLabel({ value, className = '' }) {
    const label = useMemo(() => {
        const date = new Date(value);
        const elapsed = Date.now() - date.getTime();
        const hours = Math.floor(elapsed / 3_600_000);

        if (elapsed >= 0 && hours < 24) {
            if (elapsed < 60_000) return 'Just now';

            const minutes = Math.floor(elapsed / 60_000);
            if (minutes < 60) return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;

            return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
        }

        return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(date);
    }, [value]);

    return <time dateTime={value} className={className}>{label}</time>;
}

export function UserAvatar({ user, className = 'size-8', tone = 'bg-ink-950 text-brand-cream' }) {
    return (
        <Link href={user.url} className={`grid shrink-0 place-items-center overflow-hidden rounded-full font-serif text-[10px] font-bold ${tone} ${className}`}>
            {user.avatar_url ? <img src={user.avatar_url} alt="" className="size-full object-cover" /> : user.initial}
        </Link>
    );
}

export function SpoilerContent({ body, kind, className = '' }) {
    const [revealed, setRevealed] = useState(false);

    if (revealed) {
        return <p className={`whitespace-pre-line ${className}`}>{body}</p>;
    }

    return (
        <button
            type="button"
            className="border border-ink-950/20 px-3 py-1.5 text-xs font-bold text-ink-950 transition hover:border-brand-coral"
            onClick={() => setRevealed(true)}
        >
            Reveal spoiler {kind}
        </button>
    );
}

export function ReportForm({ targetType, targetId, reasons, action, label = 'Report' }) {
    const reasonEntries = Object.entries(reasons);
    const form = useForm({
        target_type: targetType,
        target_id: targetId,
        reason: reasonEntries[0]?.[0] ?? 'other',
        details: '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(action, {
            preserveScroll: true,
            onSuccess: () => form.reset('details'),
        });
    };

    return (
        <details className="relative" data-report-form>
            <summary className="cursor-pointer list-none text-[10px] font-bold uppercase tracking-wider text-ink-950/45 transition hover:text-red-700">
                {label}
            </summary>
            <form onSubmit={submit} className="mt-2 grid gap-3 border border-red-900/15 bg-brand-cream p-3">
                <label className="text-xs font-bold uppercase tracking-wider text-ink-950/60">
                    Reason
                    <select
                        value={form.data.reason}
                        onChange={(event) => form.setData('reason', event.target.value)}
                        required
                        className="mt-1.5 w-full border border-ink-950/20 bg-white/70 px-3 py-2 text-sm font-normal normal-case outline-none focus:border-brand-coral"
                    >
                        {reasonEntries.map(([value, reasonLabel]) => <option key={value} value={value}>{reasonLabel}</option>)}
                    </select>
                </label>
                <label className="text-xs font-bold uppercase tracking-wider text-ink-950/60">
                    Additional details <span className="font-normal normal-case">(optional)</span>
                    <textarea
                        value={form.data.details}
                        onChange={(event) => form.setData('details', event.target.value)}
                        rows="3"
                        maxLength="1000"
                        className="mt-1.5 w-full resize-y border border-ink-950/20 bg-white/70 px-3 py-2 text-sm font-normal normal-case outline-none focus:border-brand-coral"
                        placeholder="Briefly explain the concern."
                    />
                </label>
                {form.errors.reason && <p className="text-xs font-semibold text-red-700">{form.errors.reason}</p>}
                <button disabled={form.processing} className="w-fit bg-red-800 px-3 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-red-700 disabled:opacity-50">
                    Send report
                </button>
            </form>
        </details>
    );
}

export const plural = (count, singular, pluralForm = `${singular}s`) => `${count} ${count === 1 ? singular : pluralForm}`;
