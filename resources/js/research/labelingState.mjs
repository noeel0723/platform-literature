export const labels = ['SAME_WORK', 'DIFFERENT_WORK', 'UNCERTAIN', 'UNLABELED'];
export const fields = ['ground_truth_label', 'evidence_url', 'labeling_notes', 'annotator_id'];
export function editable(decision) {
    return Object.fromEntries(fields.map(key => [key, decision[key] ?? (key === 'ground_truth_label' ? 'UNLABELED' : '')]));
}
export function decisionError(form, attested) {
    if (!labels.includes(form.ground_truth_label)) return 'Choose a valid label.';
    if (form.evidence_url.trim()) {
        try { const url = new URL(form.evidence_url); if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return 'Use an HTTP(S) evidence URL without credentials.'; }
        catch { return 'Use a valid evidence URL.'; }
    }
    if (['SAME_WORK', 'DIFFERENT_WORK'].includes(form.ground_truth_label)) {
        if (!form.annotator_id.trim()) return 'Enter your annotator ID.';
        if (!form.evidence_url.trim() && [...form.labeling_notes.trim()].length < 30) return 'Add evidence or a verification rationale of at least 30 characters.';
        if (!attested) return 'Confirm that you personally verified the pair.';
    }
    return '';
}
export function filterPairs(pairs, scope, unlabeled, query) {
    const text = query.toLocaleLowerCase().trim();
    return pairs.filter(p => (scope === 'all' || p.selected) && (!unlabeled || p.decision.ground_truth_label === 'UNLABELED') && (!text || [p.pair_id, ...['a', 'b'].flatMap(s => [p[s].title, p[s].original_title, p[s].author, p[s].source])].join(' ').toLocaleLowerCase().includes(text)));
}
export function progress(pairs) {
    const definite = pairs.filter(p => ['SAME_WORK', 'DIFFERENT_WORK'].includes(p.decision.ground_truth_label)).length;
    const uncertain = pairs.filter(p => p.decision.ground_truth_label === 'UNCERTAIN').length;
    return { total: pairs.length, labeled: definite, uncertain, remaining: pairs.length - definite - uncertain, percentage: pairs.length ? Math.round(definite / pairs.length * 100) : 0 };
}
export function draftKey(checksum, id) { return `literahaven-manual-study:${checksum}:${id}`; }
export function safeUrl(value) { try { const u = new URL(value); return ['http:', 'https:'].includes(u.protocol) && !u.username && !u.password ? value : null; } catch { return null; } }
