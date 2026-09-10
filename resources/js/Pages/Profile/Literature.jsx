import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { StarRating } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfilePageHeading } from '../../Components/ProfilePageUi';

export default function ProfileLiterature({ profile, navigation, completedLiterature }) {
    return (
        <>
            <Head title={`${profile.name} Literature`} />
            <ProfileSubNavigation navigation={navigation} />

            <section className="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10" aria-labelledby="completed-literature-heading">
                <ProfilePageHeading
                    eyebrow="Completed shelf"
                    title={`${profile.name}'s Literature`}
                    count={`${completedLiterature.total} completed`}
                    id="completed-literature-heading"
                />

                {completedLiterature.data.length === 0 ? (
                    <div className="mt-6 border border-dashed border-ink-950/20 bg-white/25 px-6 py-12 text-center">
                        <p className="text-xl font-bold text-ink-950">No completed literature yet.</p>
                        <p className="mt-2 text-sm text-ink-950/55">Titles marked Completed will appear on this shelf.</p>
                    </div>
                ) : (
                    <>
                        <div className="mt-5 grid grid-cols-3 gap-x-2.5 gap-y-5 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-10 xl:grid-cols-12" data-profile-literature-grid data-density="compact">
                            {completedLiterature.data.map((item) => (
                                <article key={item.id} className="group min-w-0" data-profile-literature-item>
                                    <Link href={item.literature.url} className="block">
                                        <LiteratureCover literature={item.literature} className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-coral" />
                                        <h2 className="mt-1.5 truncate text-[0.7rem] font-bold leading-4 text-ink-950 transition group-hover:text-brand-coral" title={item.literature.title}>{item.literature.title}</h2>
                                    </Link>
                                    <div className="mt-0.5 flex min-h-4 items-center justify-between gap-1 text-[0.625rem] leading-4 text-ink-950/45">
                                        {item.rating ? <StarRating rating={item.rating} size="text-[0.68rem]" /> : <span className="truncate">Completed</span>}
                                        {item.rating && item.literature.year && <span>{item.literature.year}</span>}
                                    </div>
                                </article>
                            ))}
                        </div>
                        <Pagination paginator={completedLiterature} label="Completed literature pagination" />
                    </>
                )}
            </section>
        </>
    );
}
