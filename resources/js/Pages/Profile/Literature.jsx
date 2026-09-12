import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { StarRating } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

export default function ProfileLiterature({ profile, navigation, completedLiterature }) {
    return (
        <>
            <Head title={`${profile.name} Literature`} />
            <ProfileSubNavigation navigation={navigation} />

            <ProfileContentContainer className="py-7 lg:py-9" aria-labelledby="completed-literature-heading">
                <ProfilePageHeading
                    eyebrow="Completed shelf"
                    title={`${profile.name}'s Literature`}
                    count={`${completedLiterature.total} completed`}
                    id="completed-literature-heading"
                />

                {completedLiterature.data.length === 0 ? (
                    <div className="mt-5 border-y border-dashed border-ink-950/20 px-5 py-10 text-center">
                        <p className="text-lg font-bold text-ink-950">No completed literature yet.</p>
                        <p className="mt-1.5 text-sm text-ink-950/55">Titles marked Completed will appear on this shelf.</p>
                    </div>
                ) : (
                    <>
                        <div className="mt-4 grid grid-cols-3 gap-x-2.5 gap-y-4 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-10" data-profile-literature-grid data-density="compact">
                            {completedLiterature.data.map((item) => (
                                <article key={item.id} className="group min-w-0" data-profile-literature-item>
                                    <Link href={item.literature.url} className="block">
                                        <LiteratureCover literature={item.literature} className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-coral" />
                                        <h2 className="mt-1 truncate text-[0.68rem] font-bold leading-4 text-ink-950 transition group-hover:text-brand-coral" title={item.literature.title}>{item.literature.title}</h2>
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
            </ProfileContentContainer>
        </>
    );
}
