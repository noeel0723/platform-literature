const stepLabels = ['Your formats', 'Find a read', 'Your reading life'];

export function OnboardingProgress({ step }) {
    return (
        <ol aria-label="Onboarding progress" className="grid grid-cols-3 gap-2 border-b border-ink-950/10 pb-4">
            {stepLabels.map((label, index) => (
                <li key={label} aria-current={index + 1 === step ? 'step' : undefined} className={`flex min-w-0 items-center gap-2 text-[10px] font-semibold leading-4 sm:text-xs ${index + 1 === step ? 'text-ink-950' : 'text-ink-950/50'}`}>
                    <span aria-hidden="true" className={`grid size-5 shrink-0 place-items-center rounded-full text-[10px] ${index + 1 <= step ? 'bg-ink-950 text-brand-cream' : 'bg-brand-sky/25 text-ink-950/60'}`}>{index + 1}</span>
                    <span>{label}</span>
                </li>
            ))}
        </ol>
    );
}

export function FormatPreferences({ formats, selected, onChange, disabled }) {
    return (
        <fieldset disabled={disabled}>
            <legend className="sr-only">Favorite literature formats</legend>
            <div className="grid grid-cols-2 gap-2.5">
                {formats.map(({ value, label }) => (
                    <label key={value} className={`flex min-h-16 cursor-pointer items-center gap-2 rounded-lg border px-3 py-3 text-xs font-semibold text-ink-950 transition-colors focus-within:ring-2 focus-within:ring-brand-coral/30 ${selected.includes(value) ? 'border-brand-coral bg-brand-coral/5' : 'border-ink-950/15 bg-white/60 hover:border-brand-coral/60'} ${disabled ? 'cursor-wait opacity-60' : ''}`}>
                        <input type="checkbox" name="formats[]" value={value} checked={selected.includes(value)} onChange={() => onChange(value)} className="size-4 shrink-0 accent-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral" />
                        <span>{label}</span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

const actions = [
    { name: 'Readlist', text: 'Save titles you want to read later.', symbol: 'bookmark' },
    { name: 'Completed', text: 'Keep track of the stories you finish.', symbol: 'check' },
    { name: 'Rating', text: 'Give each read a rating out of five stars.', symbol: 'star' },
    { name: 'Review', text: 'Share your thoughts with other readers.', symbol: 'review' },
];

export function ReadingActionsIntro() {
    return (
        <ul className="grid gap-3 sm:grid-cols-2">
            {actions.map(({ name, text, symbol }) => (
                <li key={name} className="flex items-start gap-3 rounded-lg border border-ink-950/10 bg-brand-sky/10 p-3">
                    <svg aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-ink-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
                        {symbol === 'bookmark' ? <path d="M6 3h12v18l-6-4-6 4V3Z" />
                            : symbol === 'check' ? <><circle cx="12" cy="12" r="9" /><path d="m7 12 3 3 7-7" /></>
                                : symbol === 'star' ? <path d="m12 3 2.8 5.8 6.4.9-4.6 4.5 1.1 6.4-5.7-3-5.7 3 1.1-6.4-4.6-4.5 6.4-.9Z" />
                                    : <><path d="M5 4h14v16H5z" /><path d="M8 8h8M8 12h8M8 16h5" /></>}
                    </svg>
                    <div><h2 className="text-xs font-bold text-ink-950">{name}</h2><p className="mt-1 text-xs leading-5 text-ink-950/65">{text}</p></div>
                </li>
            ))}
        </ul>
    );
}
