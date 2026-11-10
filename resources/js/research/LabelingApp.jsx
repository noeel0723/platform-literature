import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { decisionError, draftKey, editable, filterPairs, progress, safeUrl } from './labelingState.mjs';
import '../../css/research.css';

const token = document.querySelector('meta[name="research-token"]').content;
async function api(path, body) {
    const response = await fetch(path, { method: body === undefined ? 'GET' : 'POST', headers: { 'X-Research-Token': token, 'Content-Type': 'application/json' }, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.message || `Request failed (${response.status}).`);
    }
    return response;
}
function Record({ data, side }) {
    const url = safeUrl(data.evidence_url);
    const cover = safeUrl(data.cover_url);
    const rows = [['Original title', 'original_title'], ['Author', 'author'], ['Provider', 'source'], ['Publication year', 'year'], ['Literature type', 'type'], ['ISBN / catalog identifier', 'identifier'], ['External ID', 'external_id']];
    return <article className="record" aria-labelledby={`record-${side}`}>
        <p className="eyebrow">Record {side}</p>
        <div className="record-title">{cover && <img src={cover} alt="" referrerPolicy="no-referrer" loading="lazy" onError={e => { e.currentTarget.hidden = true; }} />}
            <h2 id={`record-${side}`}>{data.title || 'Not available'}</h2></div>
        <dl>{rows.map(([label, key]) => <div key={key}><dt>{label}</dt><dd>{data[key] || 'Not available'}</dd></div>)}</dl>
        {url ? <a className="button secondary" href={url} target="_blank" rel="noopener noreferrer">Open source {side} ↗</a> : <p className="muted">Source evidence link: Not available</p>}
        {data.source === 'hardcover' && <p className="hint">Provider search link only. Find the original record and document the verified URL below.</p>}
    </article>;
}
function PairEditor({ pair, checksum, revisionRef, onSaved, previous, next, onNavigate, onExport, registerGuard }) {
    const initial = editable(pair.decision);
    const [form, setForm] = useState(initial);
    const [attested, setAttested] = useState(Boolean(pair.decision.human_verified_at));
    const [recovery, setRecovery] = useState(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const latest = useRef({ form, attested });
    latest.current = { form, attested };
    const saved = useRef(JSON.stringify(initial));
    const inFlight = useRef(null);
    const key = draftKey(checksum, pair.pair_id);
    const dirty = JSON.stringify(form) !== saved.current;
    useEffect(() => {
        try {
            const draft = JSON.parse(localStorage.getItem(key) || 'null');
            if (draft && JSON.stringify(draft.form) !== saved.current) setRecovery(draft);
        } catch { setMessage('Browser backup is unavailable. Server saves still work.'); }
    }, [key]);
    useEffect(() => {
        if (JSON.stringify(form) !== saved.current) {
            try { localStorage.setItem(key, JSON.stringify({ form, attested, savedAt: new Date().toISOString() })); }
            catch { setError('Browser backup failed. Save progress before leaving.'); }
        }
    }, [key, form, attested]);
    const persist = useCallback(async (silent = false, override) => {
        if (inFlight.current) { await inFlight.current; }
        const current = override || latest.current;
        if (JSON.stringify(current.form) === saved.current) return true;
        const issue = decisionError(current.form, current.attested);
        if (issue) { if (!silent) setError(issue); return false; }
        setBusy(true); setError('');
        const job = (async () => {
            try {
                const response = await api('/api/save', { pair_id: pair.pair_id, decision: current.form, revision: revisionRef.current, attested: current.attested });
                const data = await response.json();
                revisionRef.current = data.revision;
                saved.current = JSON.stringify(current.form);
                onSaved(pair.pair_id, data.decision);
                setMessage('Progress saved locally.');
                if (JSON.stringify(latest.current.form) === saved.current) { try { localStorage.removeItem(key); } catch { /* Server save has succeeded even if browser storage is blocked. */ } }
                return true;
            } catch (e) { setError(e.message + ' Your edits remain in this browser.'); return false; }
            finally { setBusy(false); }
        })();
        inFlight.current = job;
        const result = await job;
        inFlight.current = null;
        return result;
    }, [key, pair.pair_id, revisionRef, onSaved]);
    useEffect(() => { registerGuard(persist); return () => registerGuard(null); }, [persist, registerGuard]);
    useEffect(() => {
        const timer = setTimeout(() => { if (dirty) void persist(true); }, 800);
        return () => clearTimeout(timer);
    }, [dirty, form, attested, persist]);
    useEffect(() => {
        const guard = event => { if (JSON.stringify(latest.current.form) !== saved.current) { event.preventDefault(); event.returnValue = ''; } };
        window.addEventListener('beforeunload', guard);
        return () => window.removeEventListener('beforeunload', guard);
    }, []);
    async function move(id, skip = false) {
        if (!id || busy) return;
        if (skip && dirty) {
            if (!window.confirm('Keep these unsaved edits in browser recovery and skip without changing the saved label?')) return;
        } else if (!(await persist())) return;
        onNavigate(id);
    }
    async function choose(label) {
        const updated = { ...form, ground_truth_label: label };
        const confirmed = label === 'UNLABELED' ? false : attested;
        setForm(updated); setAttested(confirmed); setMessage('');
        await persist(false, { form: updated, attested: confirmed });
    }
    return <>
        <div className="pair-bar"><span className="pair-id">{pair.pair_id}</span><span className="muted">{pair.selected ? 'Frozen initial subset' : 'Candidate pool'}</span></div>
        <div className="records"><Record data={pair.a} side="A" /><Record data={pair.b} side="B" /></div>
        <section className="decision" aria-labelledby="decision-heading">
            <h2 id="decision-heading">Your verification</h2>
            {recovery && <div className="notice">Unsaved manual work from this browser is available. <button onClick={() => { setForm(editable(recovery.form)); setAttested(Boolean(recovery.attested)); setRecovery(null); }}>Restore browser draft</button> <button onClick={() => { localStorage.removeItem(key); setRecovery(null); }}>Discard browser draft</button></div>}
            <div className="evidence"><label>Annotator ID<input value={form.annotator_id} maxLength={200} onChange={e => setForm({ ...form, annotator_id: e.target.value })} autoComplete="off" /></label>
                <label>Evidence URL<input type="url" value={form.evidence_url} onChange={e => setForm({ ...form, evidence_url: e.target.value })} placeholder="https://…" /></label></div>
            <label>Labeling notes<textarea rows={3} value={form.labeling_notes} maxLength={10000} onChange={e => setForm({ ...form, labeling_notes: e.target.value })} placeholder="Record the bibliographic evidence and your reasoning; explain uncertainty where relevant." /></label>
            <label className="attest"><input type="checkbox" checked={attested} onChange={e => setAttested(e.target.checked)} /> I personally checked both records and the evidence. This decision is my manual assessment.</label>
            <div className="labels" role="group" aria-label="Ground truth label">
                {['SAME_WORK', 'DIFFERENT_WORK', 'UNCERTAIN'].map(label => <button key={label} disabled={busy} aria-pressed={form.ground_truth_label === label} onClick={() => void choose(label)}>{label.replaceAll('_', ' ')}</button>)}
                <button disabled={busy} className="secondary" onClick={() => void choose('UNLABELED')}>Clear label</button>
            </div>
            <p className="hint">Definitive decisions require your ID and a verified HTTP(S) URL or a rationale of at least 30 characters. Uncertain and unlabeled pairs are excluded from metrics.</p>
            <p role="status" aria-live="polite" className="saved">{busy ? 'Saving…' : dirty ? 'Unsaved changes · browser recovery enabled' : message || 'No unsaved changes'}</p>
            {error && <p className="error" role="alert">{error}</p>}
            <div className="actions"><button className="secondary" disabled={!previous || busy} onClick={() => void move(previous)}>Previous</button><button className="secondary" disabled={!next || busy} onClick={() => void move(next, true)}>Skip</button><button disabled={!next || busy} onClick={() => void move(next)}>Next</button><span className="spacer" /><button disabled={busy} onClick={() => void persist().then(ok => { if (ok) setMessage('Progress saved locally.'); })}>Save progress</button><button className="secondary" disabled={busy} onClick={async () => { if (await persist()) await onExport(); }}>Export labels CSV</button></div>
        </section>
    </>;
}
function App() {
    const [study, setStudy] = useState(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [id, setId] = useState('');
    const [scope, setScope] = useState('selected');
    const [unlabeled, setUnlabeled] = useState(false);
    const [query, setQuery] = useState('');
    const revisionRef = useRef(0);
    const guardRef = useRef(null);
    const registerGuard = useCallback(fn => { guardRef.current = fn; }, []);
    useEffect(() => { api('/api/study').then(r => r.json()).then(data => { setStudy(data); revisionRef.current = data.revision; setId(data.pairs.find(p => p.selected)?.pair_id || data.pairs[0]?.pair_id); }).catch(e => setError(e.message)); }, []);
    const onSaved = useCallback((pairId, decision) => { setStudy(s => ({ ...s, pairs: s.pairs.map(p => p.pair_id === pairId ? { ...p, decision } : p) })); }, []);
    async function changeFilter(fn) { if (!guardRef.current || await guardRef.current()) fn(); }
    if (!study) return <main><h1>Ground Truth Labeling</h1><p role="status">{error || 'Loading local dataset…'}</p></main>;
    const filtered = filterPairs(study.pairs, scope, unlabeled, query);
    const pair = filtered.find(p => p.pair_id === id) || filtered[0];
    const index = pair ? filtered.findIndex(p => p.pair_id === pair.pair_id) : -1;
    const stats = progress(study.pairs);
    async function exportLabels() {
        try { const response = await api('/api/export', {}); const blob = await response.blob(); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = 'labels.csv'; link.click(); URL.revokeObjectURL(url); setNotice('Exported labels.csv; a copy is also saved in the study directory.'); }
        catch (e) { setError(e.message); }
    }
    return <main>
        <header><p className="eyebrow">Literahaven · local research</p><h1>Ground Truth Labeling</h1><p>{study.name}</p><div className="stats">{[['Candidate pairs', stats.total], ['Labeled', stats.labeled], ['Uncertain', stats.uncertain], ['Unlabeled', stats.remaining], ['Verified progress', `${stats.percentage}%`]].map(([label, value]) => <div key={label}><strong>{value}</strong><span>{label}</span></div>)}</div><progress value={stats.labeled} max={stats.total} aria-label="Manually verified pairs" /></header>
        <section className="toolbar" aria-label="Find pairs"><label>Scope<select value={scope} onChange={e => void changeFilter(() => { setScope(e.target.value); setId(''); })}><option value="selected">Initial subset ({study.pairs.filter(p => p.selected).length})</option><option value="all">All candidates ({stats.total})</option></select></label>
            <label className="search">Search pairs<input type="search" value={query} onChange={e => { const value = e.target.value; void changeFilter(() => { setQuery(value); setId(''); }); }} placeholder="Title, author, provider, pair ID" /></label>
            <label className="attest"><input type="checkbox" checked={unlabeled} onChange={e => void changeFilter(() => { setUnlabeled(e.target.checked); setId(''); })} /> Filter unlabeled</label><span className="muted">{index + 1} / {filtered.length}</span></section>
        {error && <p className="error" role="alert">{error}</p>}{notice && <p className="notice" role="status">{notice}</p>}
        {pair ? <PairEditor key={pair.pair_id} pair={pair} checksum={study.checksum} revisionRef={revisionRef} onSaved={onSaved} previous={filtered[index - 1]?.pair_id} next={filtered[index + 1]?.pair_id} onNavigate={setId} onExport={exportLabels} registerGuard={registerGuard} /> : <section className="decision"><h2>No matching pairs</h2><p>Change the filters to continue.</p><button onClick={() => void exportLabels()}>Export labels CSV</button></section>}
        <footer>Manual bibliographic assessment only. No resolver predictions or AI label suggestions are shown. Original candidates and snapshot remain read-only.</footer>
    </main>;
}
createRoot(document.getElementById('research-labeling')).render(<App />);
