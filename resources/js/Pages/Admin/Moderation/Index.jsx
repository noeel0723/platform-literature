import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

import CatalogPagination from '../../../Components/CatalogPagination';
import SiteContainer from '../../../Components/SiteContainer';

function PersonName({ person, fallback = 'Unavailable' }) {
    if (!person) {
        return fallback;
    }

    return person.username ? `${person.name} (@${person.username})` : person.name;
}

function PersonHandle({ person, fallback = 'unavailable' }) {
    if (!person) {
        return fallback;
    }

    return person.username ? `@${person.username}` : person.name;
}

function relativeTime(value) {
    const timestamp = new Date(value).getTime();
    const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
}

function ReportIcon({ type }) {
    const normalizedType = type.toLowerCase();

    if (normalizedType === 'review') {
        return <path d="m12 3 2.2 4.45 4.91.72-3.56 3.46.84 4.89L12 14.21l-4.39 2.31.84-4.89-3.56-3.46 4.91-.72L12 3Z" />;
    }

    if (normalizedType === 'user') {
        return <><circle cx="12" cy="8" r="3" /><path d="M6.5 19c.65-3.15 2.48-5 5.5-5s4.85 1.85 5.5 5" /></>;
    }

    if (normalizedType === 'comment') {
        return <><path d="M5 6.5h14v9H9l-4 3v-12Z" /><path d="M8 10h8M8 13h5" /></>;
    }

    return <><path d="M4.5 5.5h15v11h-9l-4.5 3v-3h-1.5v-11Z" /><path d="M8 9h8M8 12h6" /></>;
}

function TargetIcon({ type }) {
    return (
        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-coral/10 text-brand-coral" aria-hidden="true">
            <svg viewBox="0 0 24 24" className="size-4.5" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
                <ReportIcon type={type} />
            </svg>
        </span>
    );
}

function displayTargetType(type) {
    return type.toLowerCase() === 'user' ? 'Profile' : type;
}

function ModerationActions({ report }) {
    const [noteOpen, setNoteOpen] = useState(false);
    const form = useForm({
        action: '',
        resolution_note: '',
    });

    const submit = (action) => {
        form.transform((data) => ({ ...data, action })).patch(report.update_url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setNoteOpen(false);
            },
        });
    };

    if (report.status !== 'pending') {
        return null;
    }

    return (
        <div className="mt-4 border-t border-ink-950/10 pt-3">
            {report.target_state && (
                <p className="mb-3 inline-flex rounded-md border border-ink-950/10 bg-brand-sky/10 px-2.5 py-1 text-xs font-semibold text-ink-950/55">{report.target_state}</p>
            )}

            <details className="group mb-3" open={noteOpen} onToggle={(event) => setNoteOpen(event.currentTarget.open)}>
                <summary className="w-fit cursor-pointer list-none text-xs font-semibold text-ink-950/50 transition hover:text-ink-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                    <span aria-hidden="true">{noteOpen ? '−' : '+'}</span> Add internal note
                </summary>
                <div className="mt-2 max-w-2xl">
                    <label htmlFor={`resolution-note-${report.id}`} className="sr-only">Internal resolution note</label>
                    <textarea
                        id={`resolution-note-${report.id}`}
                        rows="3"
                        maxLength="1000"
                        value={form.data.resolution_note}
                        onChange={(event) => form.setData('resolution_note', event.target.value)}
                        className="w-full resize-y rounded-md border border-ink-950/15 bg-white/70 px-3 py-2 text-sm text-ink-950 outline-none transition focus:border-brand-coral focus-visible:ring-2 focus-visible:ring-brand-coral/20"
                        placeholder="Optional note for the moderation record"
                    />
                    {form.errors.resolution_note && <p className="mt-1.5 text-xs font-semibold text-red-800">{form.errors.resolution_note}</p>}
                </div>
            </details>

            <div className="flex flex-wrap gap-2">
                {report.available_actions.includes('hide') && (
                    <button type="button" disabled={form.processing} onClick={() => submit('hide')} className="rounded-md bg-ink-950 px-3.5 py-2 text-xs font-bold text-brand-cream transition hover:bg-ink-950/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:opacity-50">
                        Hide content
                    </button>
                )}

                {report.available_actions.includes('deactivate') && (
                    <button type="button" disabled={form.processing} onClick={() => submit('deactivate')} className="rounded-md border border-brand-coral px-3.5 py-2 text-xs font-bold text-brand-coral transition hover:bg-brand-coral hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:opacity-50">
                        Deactivate account
                    </button>
                )}

                {report.available_actions.includes('dismiss') && (
                    <button type="button" disabled={form.processing} onClick={() => submit('dismiss')} className="rounded-md border border-ink-950/15 bg-white/45 px-3.5 py-2 text-xs font-bold text-ink-950 transition hover:border-ink-950/35 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:opacity-50">
                        Dismiss
                    </button>
                )}
            </div>

            {form.errors.action && <p className="mt-2 text-xs font-semibold text-red-800">{form.errors.action}</p>}
        </div>
    );
}

function ReportCard({ report }) {
    const targetType = displayTargetType(report.target_type);
    const showContext = report.target_context && !report.target_title.includes(report.target_context);

    return (
        <article className="rounded-xl border border-ink-950/10 bg-white/45 px-4 py-4 shadow-[0_3px_12px_rgba(16,47,98,0.03)] sm:px-5">
            <header className="flex gap-3">
                <TargetIcon type={report.target_type} />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.62rem] font-bold uppercase tracking-[0.12em]">
                        <span className="rounded-full border border-brand-coral/35 bg-brand-coral/10 px-2 py-0.5 text-brand-coral">{report.reason_label}</span>
                        <span className="text-ink-950/40">{targetType}</span>
                        <span className="text-ink-950/25" aria-hidden="true">·</span>
                        <time dateTime={report.created_at} title={new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(report.created_at))} className="normal-case tracking-normal text-ink-950/40">
                            {relativeTime(report.created_at)}
                        </time>
                    </div>
                    <h2 className="mt-1.5 truncate text-sm font-bold text-ink-950 sm:text-base" title={report.target_title}>{report.target_title}</h2>
                    <p className="mt-1 text-xs text-ink-950/50">
                        by <span className="font-semibold text-ink-950/70"><PersonHandle person={report.target_owner} /></span>
                        <span className="mx-1.5 text-ink-950/20">·</span>
                        reported by <span className="font-semibold text-ink-950/70"><PersonHandle person={report.reporter} /></span>
                        {showContext && <span className="hidden sm:inline"> · {report.target_context}</span>}
                    </p>
                </div>
            </header>

            <blockquote className="mt-3 line-clamp-3 whitespace-pre-line rounded-md border-l-2 border-brand-coral bg-ink-950/[0.035] px-3 py-2.5 text-sm leading-6 text-ink-950/70">
                {report.target_summary}
            </blockquote>

            {report.details && (
                <p className="mt-2 line-clamp-2 text-xs leading-5 text-ink-950/45"><span className="font-bold text-ink-950/60">Reporter note:</span> {report.details}</p>
            )}

            {report.target_url && (
                <a href={report.target_url} className="mt-2 inline-flex text-xs font-bold text-ink-950/60 underline decoration-brand-coral underline-offset-4 transition hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                    Open reported item
                </a>
            )}

            <ModerationActions report={report} />

            {report.status !== 'pending' && (
                <footer className="mt-3 border-t border-ink-950/10 pt-3 text-xs leading-5 text-ink-950/50">
                    {report.status_label} by <strong className="text-ink-950/75"><PersonName person={report.resolver} fallback="Former administrator" /></strong>
                    {report.resolution_note ? ` · ${report.resolution_note}` : ''}
                </footer>
            )}
        </article>
    );
}

export default function ModerationIndex({ reports, status, statusCounts, statusLabels, routes, viewer, successMessage }) {
    return (
        <>
            <Head title="Moderation" />

            <section className="catalog-grid border-b border-ink-950/10">
                <SiteContainer className="py-8 lg:py-10">
                    <div className="mx-auto max-w-5xl">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h1 className="font-serif text-3xl font-bold tracking-tight text-ink-950">Moderation queue</h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-ink-950/60">Review community reports, hide violating content, deactivate an account when necessary, or dismiss reports that do not require action.</p>
                            </div>
                            <div className="shrink-0 rounded-lg border border-ink-950/10 bg-white/45 px-3.5 py-2.5 text-xs text-ink-950/45">
                                <span className="block">Signed in as</span>
                                <span className="mt-1 flex items-center gap-2 font-bold text-ink-950">
                                    {viewer.name}
                                    <span className="rounded-full bg-ink-950 px-2 py-0.5 text-[0.58rem] uppercase tracking-wider text-brand-cream">Admin</span>
                                </span>
                            </div>
                        </div>

                        <nav className="mt-6 flex gap-1.5 overflow-x-auto border-b border-ink-950/10 pb-3" aria-label="Report status">
                            {Object.entries(statusLabels).map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={`${routes.index}?status=${value}`}
                                    preserveScroll
                                    aria-current={status === value ? 'page' : undefined}
                                    className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral ${status === value ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-transparent bg-transparent text-ink-950/50 hover:border-ink-950/10 hover:bg-white/55 hover:text-ink-950'}`}
                                >
                                    {label} <span className={`ml-1 rounded-full px-1.5 py-0.5 text-[0.58rem] ${status === value ? 'bg-white/15' : 'bg-brand-sky/25'}`}>{statusCounts[value] ?? 0}</span>
                                </Link>
                            ))}
                        </nav>
                    </div>
                </SiteContainer>
            </section>

            <SiteContainer as="section" className="py-7 lg:py-9">
                <div className="mx-auto max-w-5xl">
                    {successMessage && <div className="mb-4 rounded-lg border border-emerald-800/20 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{successMessage}</div>}

                    {reports.data.length > 0 ? (
                        <div className="grid gap-3">
                            {reports.data.map((report) => <ReportCard key={report.id} report={report} />)}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-ink-950/15 bg-white/30 px-5 py-8 text-center">
                            <p className="text-lg font-bold text-ink-950">No {statusLabels[status].toLowerCase()} reports</p>
                            <p className="mt-1 text-sm text-ink-950/50">This moderation queue is currently clear.</p>
                        </div>
                    )}

                    {reports.last_page > 1 && <CatalogPagination pagination={reports} label="Moderation pagination" />}
                </div>
            </SiteContainer>
        </>
    );
}
