import { Head, Link, useForm } from '@inertiajs/react';

import CatalogPagination from '../../../Components/CatalogPagination';
import SiteContainer from '../../../Components/SiteContainer';

function PersonName({ person, fallback = 'Unavailable' }) {
    if (!person) {
        return fallback;
    }

    return person.username ? `${person.name} (@${person.username})` : person.name;
}

function ModerationActions({ report }) {
    const form = useForm({
        action: '',
        resolution_note: '',
    });

    const submit = (action) => {
        form.transform((data) => ({ ...data, action })).patch(report.update_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    if (report.status !== 'pending') {
        return null;
    }

    return (
        <aside className="grid content-start gap-4 border border-ink-950/10 bg-brand-cream/70 p-5">
            <div>
                <label htmlFor={`resolution-note-${report.id}`} className="text-xs font-bold uppercase tracking-wider text-ink-950/60">
                    Internal resolution note
                </label>
                <textarea
                    id={`resolution-note-${report.id}`}
                    rows="4"
                    maxLength="1000"
                    value={form.data.resolution_note}
                    onChange={(event) => form.setData('resolution_note', event.target.value)}
                    className="mt-2 w-full resize-y border border-ink-950/20 bg-white/65 px-3 py-2 text-sm text-ink-950 outline-none focus:border-brand-coral"
                    placeholder="Optional note for the moderation record"
                />
                {form.errors.resolution_note && <p className="mt-2 text-xs font-semibold text-red-800">{form.errors.resolution_note}</p>}
            </div>

            {report.target_state && (
                <p className="border border-ink-950/10 bg-white/50 p-3 text-sm font-semibold text-ink-950/55">{report.target_state}</p>
            )}

            {report.available_actions.includes('hide') && (
                <button type="button" disabled={form.processing} onClick={() => submit('hide')} className="bg-red-800 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-50">
                    Hide content and resolve
                </button>
            )}

            {report.available_actions.includes('deactivate') && (
                <button type="button" disabled={form.processing} onClick={() => submit('deactivate')} className="bg-red-800 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-50">
                    Deactivate account and resolve
                </button>
            )}

            {report.available_actions.includes('dismiss') && (
                <button type="button" disabled={form.processing} onClick={() => submit('dismiss')} className="border border-ink-950/20 px-4 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral disabled:opacity-50">
                    Dismiss report
                </button>
            )}

            {form.errors.action && <p className="text-xs font-semibold text-red-800">{form.errors.action}</p>}
        </aside>
    );
}

function ReportCard({ report }) {
    return (
        <article className="border border-ink-950/10 bg-white/35 p-5 sm:p-7">
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.14em]">
                        <span className="bg-brand-coral px-2.5 py-1 text-brand-cream">{report.reason_label}</span>
                        <span className="border border-ink-950/15 px-2.5 py-1 text-ink-950/60">{report.target_type}</span>
                        <span className="border border-ink-950/15 px-2.5 py-1 text-ink-950/60">{report.status_label}</span>
                    </div>

                    <p className="mt-5 line-clamp-4 whitespace-pre-line text-lg leading-8 text-ink-950">{report.target_summary}</p>

                    <dl className="mt-5 grid gap-3 border-t border-ink-950/10 pt-5 text-sm text-ink-950/60 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-bold uppercase tracking-wider text-ink-950/45">Reported by</dt>
                            <dd className="mt-1 font-semibold text-ink-950"><PersonName person={report.reporter} /></dd>
                        </div>
                        <div>
                            <dt className="text-xs font-bold uppercase tracking-wider text-ink-950/45">Content owner</dt>
                            <dd className="mt-1 font-semibold text-ink-950"><PersonName person={report.target_owner} /></dd>
                        </div>
                        <div>
                            <dt className="text-xs font-bold uppercase tracking-wider text-ink-950/45">Submitted</dt>
                            <dd className="mt-1"><time dateTime={report.created_at}>{new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(report.created_at))}</time></dd>
                        </div>
                        {report.target_url && (
                            <div>
                                <dt className="text-xs font-bold uppercase tracking-wider text-ink-950/45">Target</dt>
                                <dd className="mt-1"><a href={report.target_url} className="font-bold underline decoration-brand-coral underline-offset-4">Open reported item</a></dd>
                            </div>
                        )}
                    </dl>

                    {report.details && (
                        <div className="mt-5 border-l-4 border-brand-sky bg-brand-sky/15 p-4">
                            <p className="text-xs font-bold uppercase tracking-wider text-ink-950/45">Reporter details</p>
                            <p className="mt-2 leading-7 text-ink-950/70">{report.details}</p>
                        </div>
                    )}

                    {report.status !== 'pending' && (
                        <p className="mt-5 text-sm text-ink-950/55">
                            Reviewed by <strong className="text-ink-950"><PersonName person={report.resolver} fallback="Former administrator" /></strong>
                            {report.resolution_note ? `: ${report.resolution_note}` : ''}
                        </p>
                    )}
                </div>

                <ModerationActions report={report} />
            </div>
        </article>
    );
}

export default function ModerationIndex({ reports, status, statusCounts, statusLabels, routes, viewer, successMessage }) {
    return (
        <>
            <Head title="Moderation" />

            <section className="catalog-grid border-b border-ink-950/10">
                <SiteContainer className="py-12 lg:py-16">
                    <div className="mx-auto max-w-5xl">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h1 className="font-serif text-5xl font-bold text-ink-950 sm:text-6xl">Moderation queue</h1>
                                <p className="mt-4 max-w-2xl leading-7 text-ink-950/65">Review community reports, hide violating content, deactivate an account when necessary, or dismiss reports that do not require action.</p>
                            </div>
                            <div className="border border-ink-950/10 bg-brand-cream/75 px-5 py-4 text-sm text-ink-950/60">
                                Signed in as <span className="font-bold text-ink-950">{viewer.name}</span>
                                <span className="ml-2 bg-ink-950 px-2 py-1 text-xs font-bold uppercase tracking-wider text-brand-cream">Admin</span>
                            </div>
                        </div>

                        <nav className="mt-9 flex gap-2 overflow-x-auto" aria-label="Report status">
                            {Object.entries(statusLabels).map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={`${routes.index}?status=${value}`}
                                    preserveScroll
                                    className={`border px-4 py-2.5 text-sm font-bold transition ${status === value ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/15 bg-brand-cream/65 text-ink-950 hover:border-brand-coral'}`}
                                >
                                    {label} <span className="ml-1 opacity-60">{statusCounts[value] ?? 0}</span>
                                </Link>
                            ))}
                        </nav>
                    </div>
                </SiteContainer>
            </section>

            <SiteContainer as="section" className="py-12 lg:py-16">
                <div className="mx-auto max-w-5xl">
                    {successMessage && <div className="mb-7 border border-emerald-800/25 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{successMessage}</div>}

                    {reports.data.length > 0 ? (
                        <div className="grid gap-5">
                            {reports.data.map((report) => <ReportCard key={report.id} report={report} />)}
                        </div>
                    ) : (
                        <div className="border border-dashed border-ink-950/20 bg-white/25 p-10 text-center">
                            <p className="font-serif text-3xl font-bold text-ink-950">No {statusLabels[status].toLowerCase()} reports</p>
                            <p className="mt-3 text-ink-950/55">This moderation queue is currently clear.</p>
                        </div>
                    )}

                    {reports.last_page > 1 && <CatalogPagination pagination={reports} label="Moderation pagination" />}
                </div>
            </SiteContainer>
        </>
    );
}
