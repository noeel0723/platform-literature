import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import EmptyState from '../../Components/EmptyState';
import CustomListRows from '../../Components/CustomListRows';
import { Pagination, ProfileContentContainer } from '../../Components/ProfilePageUi';

export default function ProfileLists({ profile, navigation, lists, isOwner, createUrl }) {
    return (
        <>
            <Head title={`${profile.name} Lists`} />
            <ProfileSubNavigation navigation={navigation} />
            <ProfileContentContainer className="py-7 lg:py-9">
                <div className="mx-auto max-w-5xl">
                    <div className="flex items-center justify-between gap-4">
                        <h1 className="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">My lists</h1>
                        {isOwner && (
                            <Link href={createUrl} className="inline-flex min-h-9 items-center justify-center rounded-md bg-ink-950 px-4 text-xs font-bold text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">
                                New list
                            </Link>
                        )}
                    </div>

                    {lists.data.length === 0 ? (
                        <EmptyState
                            className="mt-5"
                            title="No custom lists yet."
                            description={isOwner ? 'Create your first list to organize literature your way.' : 'This reader has not shared any public lists yet.'}
                            action={isOwner ? { href: createUrl, label: 'New list' } : null}
                        />
                    ) : (
                        <CustomListRows lists={lists.data} />
                    )}

                    <Pagination paginator={lists} label="Custom lists pagination" />
                </div>
            </ProfileContentContainer>
        </>
    );
}
