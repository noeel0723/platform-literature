@props([
    'targetType',
    'targetId',
    'label' => 'Report',
])

@php($reportFormId = 'report-'.$targetType.'-'.$targetId)

<details class="relative" data-report-form>
    <summary class="cursor-pointer list-none text-xs font-bold uppercase tracking-wider text-ink-950/45 transition hover:text-red-700">{{ $label }}</summary>
    <form action="{{ route('reports.store') }}" method="POST" class="mt-3 grid gap-3 border border-red-900/15 bg-brand-cream p-4">
        @csrf
        <input type="hidden" name="target_type" value="{{ $targetType }}">
        <input type="hidden" name="target_id" value="{{ $targetId }}">

        <div>
            <label for="{{ $reportFormId }}-reason" class="text-xs font-bold uppercase tracking-wider text-ink-950/60">Reason</label>
            <select id="{{ $reportFormId }}-reason" name="reason" required class="mt-1.5 w-full border border-ink-950/20 bg-white/70 px-3 py-2 text-sm outline-none focus:border-brand-coral">
                @foreach (\App\Models\Report::REASON_LABELS as $value => $reasonLabel)
                    <option value="{{ $value }}">{{ $reasonLabel }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="{{ $reportFormId }}-details" class="text-xs font-bold uppercase tracking-wider text-ink-950/60">Additional details <span class="font-normal normal-case">(optional)</span></label>
            <textarea id="{{ $reportFormId }}-details" name="details" rows="3" maxlength="1000" class="mt-1.5 w-full resize-y border border-ink-950/20 bg-white/70 px-3 py-2 text-sm outline-none focus:border-brand-coral" placeholder="Briefly explain the concern."></textarea>
        </div>

        <button class="w-fit bg-red-800 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-red-700">Send report</button>
    </form>
</details>
