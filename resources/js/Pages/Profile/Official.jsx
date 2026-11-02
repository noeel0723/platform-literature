import { Head, Link } from '@inertiajs/react';

import CustomListRows from '../../Components/CustomListRows';
import EmptyState from '../../Components/EmptyState';
import ProfileBanner from '../../Components/ProfileBanner';
import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { Pagination } from '../../Components/ProfilePageUi';
import SiteContainer from '../../Components/SiteContainer';
import { ProfileOverview } from './Show';

export function OfficialListUpdates({ lists, routes }) {
    return (
        <SiteContainer as="section" id="official-updates" className="scroll-mt-24 py-7 lg:py-9" aria-labelledby="official-updates-heading">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-950/15 pb-3">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-coral">Official lists</p>
                    <h2 id="official-updates-heading" className="mt-1 font-serif text-xl font-bold text-ink-950">Latest list updates</h2>
                </div>
                <div className="flex items-center gap-4">
                    {routes.create_list && <Link href={routes.create_list} className="rounded-md bg-ink-950 px-3 py-2 text-xs font-semibold text-brand-cream hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">New list</Link>}
                    <Link href={routes.lists} className="text-[11px] font-bold uppercase tracking-wider text-ink-950/65 hover:text-brand-coral focus-visible:underline">All lists <span aria-hidden="true">→</span></Link>
                </div>
            </div>
            {lists.data.length ? <CustomListRows lists={lists.data} showUpdated /> : <EmptyState className="mt-5" title="No official lists yet." description="Curated lists will appear here when they are published." action={routes.create_list ? { href: routes.create_list, label: 'Create a list' } : null} />}
            <Pagination paginator={lists} label="Official list updates pagination" />
        </SiteContainer>
    );
}

export default function OfficialProfile({ profile, navigation, lists, reportReasons, routes }) {
    return (
        <>
            <Head title={`${profile.name} · Official`} />
            <ProfileBanner profile={profile} />
            <ProfileOverview profile={profile} routes={routes} reportReasons={reportReasons} />
            <ProfileSubNavigation navigation={navigation} />
            <OfficialListUpdates lists={lists} routes={routes} />
        </>
    );
}
