import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { FormatPreferences, OnboardingProgress, ReadingActionsIntro } from '../../Components/OnboardingSteps';
import OnboardingDiscovery from '../../Components/OnboardingDiscovery';
import SiteContainer from '../../Components/SiteContainer';
import { feedbackVisitOptions } from '../../Support/actionFeedback';

const copy = {
    1: { title: 'What do you enjoy reading?', text: 'Choose your favorite formats to shape your first recommendations. You can pick more than one, or explore everything.' },
    2: { title: 'Find your first read', text: 'Search for a title or an author you love. Open a result to discover the story, its ratings, and what other readers think.' },
    3: { title: 'Make room for your stories', text: 'Your literature pages give you a few simple ways to remember, organize, and share every read.' },
};

export default function Onboarding({ step, preferredFormats, formats, routes, discovery = { query: '', groups: [], results: [] } }) {
    const form = useForm({ formats: preferredFormats });
    const search = useForm({ q: discovery.query });
    const submitting = useRef(false);
    const heading = useRef(null);

    useEffect(() => { heading.current?.focus({ preventScroll: true }); }, [step]);

    const save = (action) => {
        if (submitting.current || form.processing) return;
        submitting.current = true;
        form.clearErrors();
        form.transform((data) => ({ ...data, action }));
        form.patch(routes.update, feedbackVisitOptions({
            preserveScroll: true,
            onFinish: () => { submitting.current = false; },
        }));
    };
    const toggleFormat = (value) => form.setData('formats', form.data.formats.includes(value)
        ? form.data.formats.filter((format) => format !== value)
        : [...form.data.formats, value]);

    return (
        <>
            <Head title="Welcome to Literahaven" />
            <SiteContainer as="section" className="py-8 sm:py-12" aria-labelledby="onboarding-title">
                <div className="mx-auto max-w-xl rounded-xl border border-ink-950/10 bg-brand-cream p-5 shadow-sm shadow-ink-950/5 sm:p-6">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-coral">Welcome to Literahaven</p>
                        <button type="button" disabled={form.processing} onClick={() => save('skip')} className="min-h-10 rounded px-2 text-xs font-semibold text-ink-950/60 hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50">Skip</button>
                    </div>
                    <OnboardingProgress step={step} />
                    <p className="mt-5 text-[10px] font-semibold uppercase tracking-wider text-ink-950/50">Step {step} of 3</p>
                    <h1 ref={heading} tabIndex={-1} id="onboarding-title" className="mt-1 font-serif text-2xl font-bold text-ink-950 outline-none">{copy[step].title}</h1>
                    <p className="mt-2 text-sm leading-6 text-ink-950/65">{copy[step].text}</p>

                    <div className="mt-5">
                        {step === 1 && <FormatPreferences formats={formats} selected={form.data.formats} onChange={toggleFormat} disabled={form.processing} />}
                        {step === 2 && <OnboardingDiscovery discovery={discovery} search={search} disabled={form.processing} browseUrl={routes.browse} onSearch={(event) => {
                            event.preventDefault();
                            if (search.processing || form.processing) return;
                            search.get(routes.discovery, feedbackVisitOptions({ preserveState: true, preserveScroll: true, replace: true }));
                        }} />}
                        {step === 3 && <ReadingActionsIntro />}
                    </div>

                    {Object.keys(form.errors).length > 0 && <p role="alert" className="mt-4 text-xs leading-5 text-red-700">{Object.values(form.errors)[0]}</p>}
                    <div className="mt-6 flex items-center justify-between gap-3 border-t border-ink-950/10 pt-4">
                        <button type="button" disabled={step === 1 || form.processing} onClick={() => save('back')} className="min-h-10 rounded-md px-3 text-xs font-semibold text-ink-950/70 hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-brand-coral disabled:cursor-default disabled:opacity-35">Back</button>
                        <button type="button" disabled={form.processing} onClick={() => save(step === 3 ? 'complete' : 'next')} className="min-h-10 rounded-md bg-ink-950 px-5 text-xs font-bold text-brand-cream transition-colors hover:bg-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50">{form.processing ? 'Saving…' : step === 3 ? 'Finish' : 'Next'}</button>
                    </div>
                </div>
            </SiteContainer>
        </>
    );
}
