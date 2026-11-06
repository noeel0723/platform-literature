import { Head, Link } from '@inertiajs/react';

import EmptyState from '../../Components/EmptyState';
import { Pagination } from '../../Components/ProfilePageUi';
import PublicListCard from '../../Components/PublicListCard';
import SiteContainer from '../../Components/SiteContainer';
import { openLoginPanel } from '../../Support/loginPanel';

export function PublicListSection({ id, title, lists, emptyDescription }) {
    return (
        <section id={id} className="scroll-mt-24" aria-labelledby={`${id}-heading`}>
            <div className="mb-5 flex items-center justify-between gap-3 border-b border-ink-950/15 pb-3">
                <h2 id={`${id}-heading`} className="text-[11px] font-bold uppercase tracking-[0.18em] text-brand-coral">{title}</h2>
                <span className="text-xs text-ink-950/55">{lists.total} {lists.total === 1 ? 'list' : 'lists'}</span>
            </div>
            {lists.data.length > 0 ? (
                <div className="grid grid-cols-1 gap-x-7 gap-y-8 min-[480px]:grid-cols-2 lg:grid-cols-3">
                    {lists.data.map((list) => <PublicListCard key={list.id} list={list} />)}
                </div>
            ) : <EmptyState title={`No ${title.toLowerCase()} yet.`} description={emptyDescription} />}
            <Pagination paginator={lists} label={`${title} pagination`} />
        </section>
    );
}

export default function Lists({ officialLists, readerLists, routes }) {
    const actionClass = 'inline-flex min-h-9 items-center justify-center rounded-md bg-ink-950 px-5 text-xs font-semibold text-brand-cream transition-colors hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral';

    return (
        <>
            <Head title="Lists" />
            <SiteContainer className="py-8 sm:py-10 lg:py-12">
                <div className="mx-auto max-w-5xl">
                    <header className="mb-9 text-center sm:mb-11">
                        <h1 className="font-serif text-2xl font-semibold leading-tight text-ink-950 sm:text-3xl">Collect, curate, and share your reading.</h1>
                        <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-ink-950/60">Find your next read in lists curated by Literahaven and its readers.</p>
                        <div className="mt-4">
                            {routes.create
                                ? <Link href={routes.create} className={actionClass}>Create a list</Link>
                                : routes.verification
                                    ? <Link href={routes.verification} className={actionClass}>Verify email to create a list</Link>
                                    : <button type="button" onClick={openLoginPanel} className={actionClass}>Log in to create a list</button>}
                        </div>
                    </header>
                    <div className="space-y-10 sm:space-y-12">
                        <PublicListSection id="official-lists" title="Official Lists" lists={officialLists} emptyDescription="Public lists curated by the Literahaven team will appear here." />
                        <PublicListSection id="readers-lists" title="Readers’ Lists" lists={readerLists} emptyDescription="Lists shared by readers will appear here. Create a public list to share your own collection." />
                    </div>
                </div>
            </SiteContainer>
        </>
    );
}
