import { Head, router } from '@inertiajs/react';

import LoginPanel from '../../Components/LoginPanel';
import SiteContainer from '../../Components/SiteContainer';

export default function Login({ routes, csrf_token: csrfToken }) {
    return (
        <>
            <Head title="Log in" />
            <section className="catalog-grid min-h-[70vh] border-b border-ink-950/10">
                <LoginPanel routes={routes} csrfToken={csrfToken} onClose={() => router.visit(routes.home)} embedded />
                <SiteContainer className="py-14 text-center sm:py-20">
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader account</p>
                    <h1 className="mt-3 text-3xl font-bold text-ink-950">Welcome back to Literahaven.</h1>
                    <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-ink-950/60">Sign in above to continue your Readlist, ratings, reviews, and reading history.</p>
                </SiteContainer>
            </section>
        </>
    );
}
