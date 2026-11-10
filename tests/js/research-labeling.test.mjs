import test from 'node:test';
import assert from 'node:assert/strict';
import { editable, decisionError, filterPairs, progress, draftKey, safeUrl } from '../../resources/js/research/labelingState.mjs';
const empty = editable({});
test('new decisions never acquire automatic labels', () => { assert.equal(empty.ground_truth_label, 'UNLABELED'); });
test('definitive decisions require ID, evidence and manual attestation', () => {
    const form = { ...empty, ground_truth_label: 'SAME_WORK', annotator_id: 'human', evidence_url: 'https://example.org/record' };
    assert.ok(decisionError(form, false)); assert.equal(decisionError(form, true), '');
    assert.ok(decisionError({ ...form, annotator_id: '' }, true));
    assert.ok(decisionError({ ...form, evidence_url: '', labeling_notes: 'short' }, true));
    assert.equal(decisionError({ ...form, evidence_url: '', labeling_notes: 'Manually compared the bibliographic identity and edition.' }, true), '');
});
test('clear and uncertain preserve manual notes without verification', () => { for (const label of ['UNLABELED', 'UNCERTAIN']) assert.equal(decisionError({ ...empty, ground_truth_label: label, labeling_notes: 'need more evidence' }, false), ''); });
test('search, frozen subset and unlabeled filters do not consult predictions', () => {
    const a = { pair_id: 'pair-1', selected: true, decision: empty, a: { title: 'Été', author: 'Lewis', source: 'anilist' }, b: { title: 'Tale', author: '', source: 'kitsu' } };
    const b = { ...a, pair_id: 'pair-2', selected: false, decision: { ...empty, ground_truth_label: 'UNCERTAIN' } };
    assert.equal(filterPairs([a,b], 'selected', false, 'lewis').length, 1);
    assert.equal(filterPairs([a,b], 'all', true, 'kitsu').length, 1);
    assert.equal(filterPairs([a,b], 'all', false, 'pair-2')[0].pair_id, 'pair-2');
    assert.deepEqual(progress([a,b]), { total: 2, labeled: 0, uncertain: 1, remaining: 1, percentage: 0 });
});
test('browser recovery keys separate snapshots and pairs; serialization retains handwritten fields', () => {
    assert.notEqual(draftKey('a', '1'), draftKey('b', '1')); assert.notEqual(draftKey('a','1'), draftKey('a','2'));
    const form = { ...empty, labeling_notes: '"Quote", Unicode 春\nSecond line' };
    assert.deepEqual(editable(JSON.parse(JSON.stringify(form))), form);
});
test('unsafe evidence URLs cannot be opened', () => { assert.equal(safeUrl('javascript:alert(1)'), null); assert.equal(safeUrl('https://u:p@example.org'), null); assert.equal(safeUrl('https://example.org'), 'https://example.org'); });
