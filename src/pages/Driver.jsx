import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { supabase, sheetUrl } from '../lib/supabase';
import { useAuth } from '../auth';
import { Badge, Plate, PageHead, Field, Msg, useAsync, q } from '../components/ui';
import { L, today, fdateLong, fdatetime, uploadFile, compressImage } from '../lib/utils';

export default function DriverHome() {
  const { profile } = useAuth();
  const { loading, data, error } = useAsync(async () => {
    const [assign, checks, disp, inc] = await Promise.all([
      q(supabase.from('truck_assignments').select('truck_id, trucks(plate,brand,model)').is('end_date', null)),
      q(supabase.from('daily_checks').select('check_type').eq('check_date', today())),
      q(supabase.from('dispatches').select('id,work_date,trucks(plate),dispatch_tours(*),h1:helpers!dispatches_helper1_id_fkey(first_name,last_name),h2:helpers!dispatches_helper2_id_fkey(first_name,last_name)').gte('work_date', today()).order('work_date')),
      q(supabase.from('incidents').select('id,category,status,created_at').neq('status', 'resolved').order('created_at', { ascending: false })),
    ]);
    return { truck: assign[0]?.trucks ?? null, checks, disp, inc };
  }, []);
  if (loading) return <p className="muted">Chargement…</p>;
  if (error) return <Msg>{error}</Msg>;
  const done = (t) => data.checks.some((c) => c.check_type === t);
  return (
    <>
      <PageHead title={`Bonjour ${profile.first_name}`} />
      {data.truck ? <div className="card"><span className="muted small">Mon camion</span><h2 style={{ margin: '4px 0 0' }}><Plate>{data.truck.plate}</Plate> {data.truck.brand} {data.truck.model}</h2></div>
        : <Msg type="info">Aucun camion ne vous est affecté pour le moment.</Msg>}
      {data.truck && <>
        {done('morning') ? <div className="big-btn done">✓ Contrôle du matin effectué</div> : <Link className="big-btn" to="/controle/morning">📷 Contrôle du matin</Link>}
        {done('evening') ? <div className="big-btn done">✓ Contrôle du soir effectué</div> : <Link className="big-btn" to="/controle/evening">📷 Contrôle du soir</Link>}
        <Link className="big-btn red" to="/signaler">⚠ Signaler un problème</Link></>}
      {data.disp.length > 0 && <section className="card"><h2>Mes tournées</h2>
        {data.disp.map((d) => (
          <div key={d.id} style={{ marginBottom: 14 }}><b>{fdateLong(d.work_date)}</b><br /><span className="muted small">Camion {d.trucks.plate}{[d.h1, d.h2].filter(Boolean).length ? ' · avec ' + [d.h1, d.h2].filter(Boolean).map((h) => `${h.first_name} ${h.last_name}`).join(' et ') : ''}</span>
            <ul className="rows">{[...d.dispatch_tours].sort((a, b) => a.position - b.position).map((t) => <li key={t.id}><span>{L.tour_kind[t.kind]} · {t.client} – {t.city}</span>{t.file_path && <a className="btn sm" href={sheetUrl(t.token)}>Télécharger</a>}</li>)}</ul></div>))}</section>}
      {data.inc.length > 0 && <section className="card"><h2>Mes signalements en cours</h2><ul className="rows">{data.inc.map((i) => <li key={i.id}><span>{L.incident_category[i.category]} <span className="muted small">· {fdatetime(i.created_at)}</span></span><Badge kind="incident_status" value={i.status} /></li>)}</ul></section>}
    </>
  );
}

function PhotoPicker({ files, setFiles, max }) {
  const add = (e) => { setFiles([...files, ...Array.from(e.target.files)].slice(0, max)); e.target.value = ''; };
  return (
    <div className="field"><label>Photos (max {max})</label>
      <div className="actions">
        <label className="btn light">📷 Appareil photo<input type="file" accept="image/*" capture="environment" hidden onChange={add} /></label>
        <label className="btn light">🖼 Galerie<input type="file" accept="image/*" multiple hidden onChange={add} /></label></div>
      {files.length > 0 && <div className="photos" style={{ marginTop: 8 }}>{files.map((f, i) => <div key={i} style={{ position: 'relative' }}><img src={URL.createObjectURL(f)} alt="" /><button type="button" className="btn red sm" style={{ position: 'absolute', top: 4, right: 4 }} onClick={() => setFiles(files.filter((_, j) => j !== i))}>✕</button></div>)}</div>}
    </div>
  );
}

export function CheckForm() {
  const { type } = useParams(); const nav = useNavigate(); const { profile } = useAuth();
  const [files, setFiles] = useState([]); const [km, setKm] = useState(''); const [comment, setComment] = useState(''); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const truck = useAsync(async () => (await q(supabase.from('truck_assignments').select('truck_id').is('end_date', null)))[0]?.truck_id ?? null, []);
  if (!['morning', 'evening'].includes(type)) return <Msg>Contrôle inconnu.</Msg>;
  async function submit(e) {
    e.preventDefault(); setErr('');
    if (!truck.data) return setErr("Aucun camion ne vous est affecté.");
    if (!files.length) return setErr('Ajoutez au moins une photo.');
    setBusy(true); const up = [];
    try {
      const { data: c, error } = await supabase.from('daily_checks').insert({ truck_id: truck.data, driver_id: profile.id, check_type: type, mileage: km ? Number(km) : null, comment: comment.trim() || null }).select('id').single();
      if (error) throw new Error(error.code === '23505' ? 'Ce contrôle a déjà été fait aujourd\'hui.' : error.message);
      for (const f of files) { const path = await uploadFile('photos', `${profile.id}/checks`, await compressImage(f)); up.push(path); }
      const { error: e2 } = await supabase.from('truck_photos').insert(up.map((file_path) => ({ check_id: c.id, file_path })));
      if (e2) throw e2;
      nav('/');
    } catch (x) { setErr(x.message); setBusy(false); }
  }
  return (
    <>
      <PageHead title={`Contrôle du ${type === 'morning' ? 'matin' : 'soir'}`}><Link className="btn light" to="/">Retour</Link></PageHead>
      <form className="card" onSubmit={submit}><Msg>{err}</Msg>
        <PhotoPicker files={files} setFiles={setFiles} max={10} />
        <Field label="Kilométrage"><input type="number" inputMode="numeric" value={km} onChange={(e) => setKm(e.target.value)} /></Field>
        <Field label="Remarque"><textarea rows={2} value={comment} onChange={(e) => setComment(e.target.value)} /></Field>
        <button className="btn block" disabled={busy}>{busy ? 'Envoi des photos…' : 'Envoyer le contrôle'}</button></form>
    </>
  );
}

export function IncidentForm() {
  const nav = useNavigate(); const { profile } = useAuth();
  const [f, setF] = useState({ category: 'other', priority: 'medium', description: '', safety_risk: false });
  const [files, setFiles] = useState([]); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const truck = useAsync(async () => (await q(supabase.from('truck_assignments').select('truck_id').is('end_date', null)))[0]?.truck_id ?? null, []);
  async function submit(e) {
    e.preventDefault(); setErr('');
    if (!truck.data) return setErr("Aucun camion ne vous est affecté.");
    if (f.description.trim().length < 5) return setErr('Décrivez le problème.');
    setBusy(true);
    try {
      const { data: i, error } = await supabase.from('incidents').insert({ truck_id: truck.data, driver_id: profile.id, category: f.category, priority: f.priority, description: f.description.trim(), safety_risk: f.safety_risk }).select('id').single();
      if (error) throw error;
      const up = [];
      for (const file of files) up.push(await uploadFile('photos', `${profile.id}/incidents`, await compressImage(file)));
      if (up.length) { const { error: e2 } = await supabase.from('incident_photos').insert(up.map((file_path) => ({ incident_id: i.id, file_path }))); if (e2) throw e2; }
      nav('/');
    } catch (x) { setErr(x.message); setBusy(false); }
  }
  return (
    <>
      <PageHead title="Signaler un problème"><Link className="btn light" to="/">Retour</Link></PageHead>
      <form className="card" onSubmit={submit}><Msg>{err}</Msg>
        <div className="grid2">
          <Field label="Catégorie"><select value={f.category} onChange={(e) => setF({ ...f, category: e.target.value })}>{Object.entries(L.incident_category).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
          <Field label="Priorité"><select value={f.priority} onChange={(e) => setF({ ...f, priority: e.target.value })}>{Object.entries(L.priority).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field></div>
        <Field label="Description *"><textarea rows={4} value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} /></Field>
        <label className="check" style={{ marginBottom: 12 }}><input type="checkbox" checked={f.safety_risk} onChange={(e) => setF({ ...f, safety_risk: e.target.checked })} /> Risque pour la sécurité</label>
        <PhotoPicker files={files} setFiles={setFiles} max={5} />
        <button className="btn block red" disabled={busy}>{busy ? 'Envoi…' : 'Envoyer le signalement'}</button></form>
    </>
  );
}
