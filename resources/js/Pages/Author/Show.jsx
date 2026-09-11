import { Head, Link } from '@inertiajs/react';

import LiteratureCard from '../../Components/LiteratureCard';

function PaginationLink({ href, relation, children }) {
    const classes = 'inline-flex min-h-10 items-center border px-5 text-sm font-semibold';

    return href ? (
        <Link href={href} rel={relation} preserveScroll className={`${classes} border-ink-950/20 bg-white/45 text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-brand-cream`}>
            {children}
        </Link>
    ) : (
        <span aria-disabled="true" className={`${classes} border-ink-950/10 text-ink-950/30`}>{children}</span>
    );
}

export default function AuthorShow({ author, literatures, profileSourceUrl, imageLicenseUrl }) {
    return (
        <>
            <Head title={author.name} />
            <main className="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14" aria-labelledby="author-heading">
                <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_290px] lg:gap-12 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <section className="min-w-0" aria-labelledby="author-heading">
                        <header className="border-b border-ink-950/15 pb-4">
                            <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Works by</p>
                            <div className="mt-1 flex flex-wrap items-end justify-between gap-3">
                                <h1 id="author-heading" className="text-3xl font-bold tracking-tight text-ink-950 sm:text-4xl">{author.name}</h1>
                                <p className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">{author.literatures_count} {author.literatures_count === 1 ? 'work' : 'works'}</p>
                            </div>
                        </header>

                        {literatures.data.length > 0 ? (
                            <>
                                <div className="mt-5 grid grid-cols-3 gap-x-3 gap-y-6 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-4 xl:grid-cols-5">
                                    {literatures.data.map((literature) => (
                                        <div key={literature.slug} className="min-w-0">
                                            <LiteratureCard literature={literature} compact />
                                            {literature.rating !== null && (
                                                <div className="mt-1 flex items-center gap-1 text-[0.68rem] font-semibold text-ink-950/50" aria-label={`Average rating ${Number(literature.rating).toFixed(1)} out of 5`}>
                                                    <span className="text-brand-coral" aria-hidden="true">★</span>
                                                    <span>{Number(literature.rating).toFixed(1)}</span>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                {literatures.last_page > 1 && (
                                    <nav className="mt-10 flex items-center justify-between border-t border-ink-950/10 pt-6" aria-label="Author works pagination">
                                        <PaginationLink href={literatures.prev_page_url} relation="prev">Previous</PaginationLink>
                                        <span className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">Page {literatures.current_page} of {literatures.last_page}</span>
                                        <PaginationLink href={literatures.next_page_url} relation="next">Next</PaginationLink>
                                    </nav>
                                )}
                            </>
                        ) : (
                            <div className="mt-6 border border-dashed border-ink-950/20 bg-white/25 p-7">
                                <p className="text-xl font-bold text-ink-950">No linked works yet</p>
                                <p className="mt-2 text-sm leading-6 text-ink-950/55">Works will appear here after the catalog connects this author to a literature record.</p>
                            </div>
                        )}
                    </section>

                    <aside className="min-w-0" aria-label={`About ${author.name}`}>
                        <div className="lg:sticky lg:top-24">
                            <div className="mx-auto aspect-[4/5] max-w-[260px] overflow-hidden border border-ink-950/15 bg-linear-to-br from-brand-sky/40 via-brand-yogurt to-brand-coral/25 shadow-[0_12px_30px_rgba(16,47,98,0.10)] lg:max-w-none">
                                {author.image_url ? <img src={author.image_url} alt={`Portrait of ${author.name}`} className="size-full object-cover" decoding="async" /> : <div className="grid size-full place-items-center px-6 text-center"><span className="text-6xl font-bold text-ink-950/75">{author.initials}</span></div>}
                            </div>
                            <div className="mx-auto mt-5 max-w-[420px] border-t border-ink-950/15 pt-4 lg:max-w-none">
                                <h2 className="text-2xl font-bold tracking-tight text-ink-950">{author.name}</h2>
                                <p className="mt-3 text-sm leading-6 text-ink-950/65">{author.biography || 'A biography is not available from the connected metadata sources yet.'}</p>
                                <dl className="mt-5 grid grid-cols-2 border-y border-ink-950/10 text-sm">
                                    <div className="py-3"><dt className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/40">Catalog works</dt><dd className="mt-1 font-semibold text-ink-950">{author.literatures_count}</dd></div>
                                    <div className="border-l border-ink-950/10 py-3 pl-4"><dt className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/40">Identity</dt><dd className="mt-1 font-semibold text-ink-950">Author</dd></div>
                                </dl>
                                {profileSourceUrl && <a href={profileSourceUrl} target="_blank" rel="noopener noreferrer" className="mt-4 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-brand-stem transition hover:text-brand-coral">View metadata source <span aria-hidden="true">↗</span></a>}
                                {imageLicenseUrl && <a href={imageLicenseUrl} target="_blank" rel="noopener noreferrer" className="mt-2 block text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-ink-950/45 hover:text-brand-coral">Portrait license</a>}
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}
