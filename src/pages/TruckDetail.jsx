import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { useAuth } from '../auth';
import { Badge, Plate, PageHead, Field, Msg, Table, useAsync, q, openFile } from '../components/ui';
import { L, fdate, money, fullName, uploadFile, today } from '../lib/utils';

const EMPTY = { plate: '', brand: '', model: '', year: '', vehicle_type: 'Fourgon', volume_m3: '', mileage: 0, status: 'available', last_service_date: '', next_service_date: '', insurance_info: '', registration_number: '', ownership: 'owned', rental_company: '', rental_contract_no: '', rental_start_date: '', rental_end_date: '', rental_monthly_cost: '' };
const nul = (v) => (v === '' || v === undefined ? null : v);

export default function TruckDetail() {
  const { id } = useParams();
  const isNew = id === 'nouveau';
  const nav = useNavigate();
  const { profile } = useAuth();
  const [f, setF] = useState(EMPTY); const [err, setErr] = useState(''); const [ok, setOk] = useState(''); const [busy, setBusy] = useState(false);
  const { loading, data, reload } = useAsync(async () => {
    if (isNew) return null;
    const [truck, assign, docs, maint, renew, drivers] = await Promise.all([
      q(supabase.from('trucks').select('*').eq('id', id).single()),
      q(supabase.from('truck_assignments').select('id,driver_id,profiles(first_name,last_name)').eq('truck_id', id).is('end_date', null)),
      q(supabase.from('truck_documents').select('*').eq('truck_id', id).order('created_at', { ascending: false })),
      q(supabase.from('maintenance_records').select('*').eq('truck_id', id).order('service_date', { ascending: false })),
      q(supabase.from('rental_renewals').select('*').eq('truck_id', id).order('created_at', { ascending: false })),
      q(supabase.from('profiles').select('id,first_name,last_name').eq('role', 'driver').eq('is_active', true).order('last_name')),
    ]);
    return { truck, assign: assign[0] ?? null, docs, maint, renew, drivers };
  }, [id]);
  useEffect(() => { if (data?.truck) setF(Object.fromEntries(Object.keys(EMPTY).map((k) => [k, data.truck[k] ?? '']))); }, [data]);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  async function save(e) {
    e.preventDefault(); setErr(''); setOk('');
    if (!f.plate.trim()) return setErr('La plaque est obligatoire.');
    if (f.ownership === 'rented' && (!f.rental_company || !f.rental_end_date)) return setErr('Pour une location : indiquez le loueur et la date de fin.');
    setBusy(true);
    const row = { ...f, plate: f.plate.trim().toUpperCase(), year: nul(f.year), volume_m3: nul(f.volume_m3), mileage: Number(f.mileage) || 0,
      last_service_date: nul(f.last_service_date), next_service_date: nul(f.next_service_date),
      rental_start_date: nul(f.rental_start_date), rental_end_date: nul(f.rental_end_date), rental_monthly_cost: nul(f.rental_monthly_cost) };
    if (f.ownership === 'owned') Object.assign(row, { rental_company: null, rental_contract_no: null, rental_start_date: null, rental_end_date: null, rental_monthly_cost: null });
    const res = isNew ? await supabase.from('trucks').insert(row).select('id').single() : await supabase.from('trucks').update(row).eq('id', id);
    setBusy(false);
    if (res.error) return setErr(res.error.code === '23505' ? 'Cette plaque existe déjà.' : res.error.message);
    if (isNew) nav(`/camions/${res.data.id}`); else { setOk('Camion enregistré.'); reload(); }
  }
  async function remove() {
    if (!window.confirm('Supprimer ce camion et tout son historique ?')) return;
    const paths = [...(data.docs ?? []).map((d) => d.file_path)];
    if (paths.length) await supabase.storage.from('documents').remove(paths);
    const { error } = await supabase.from('trucks').delete().eq('id', id);
    if (error) setErr(error.message); else nav('/camions');
  }
  async function assign(driverId) {
    setErr('');
    if (data.assign) await supabase.from('truck_assignments').update({ end_date: new Date().toISOString() }).eq('id', data.assign.id);
    if (driverId) {
      await supabase.from('truck_assignments').update({ end_date: new Date().toISOString() }).eq('driver_id', driverId).is('end_date', null);
      const { error } = await supabase.from('truck_assignments').insert({ truck_id: Number(id), driver_id: driverId });
      if (error) setErr(error.message);
    }
    reload();
  }

  if (!isNew && loading) return <p className="muted">Chargement…</p>;
  const set2 = f.ownership === 'rented';
  return (
    <>
      <PageHead title={isNew ? 'Nouveau camion' : <><Plate>{data.truck.plate}</Plate> {data.truck.brand} {data.truck.model}</>}>
        <Link className="btn light" to="/camions">Retour</Link>
      </PageHead>
      <Msg>{err}</Msg><Msg type="ok">{ok}</Msg>
      <form className="card" onSubmit={save}>
        <div className="grid3">
          <Field label="Plaque *"><input value={f.plate} onChange={set('plate')} required /></Field>
          <Field label="Marque"><input value={f.brand} onChange={set('brand')} /></Field>
          <Field label="Modèle"><input value={f.model} onChange={set('model')} /></Field>
          <Field label="Année"><input type="number" value={f.year} onChange={set('year')} /></Field>
          <Field label="Type"><input value={f.vehicle_type} onChange={set('vehicle_type')} /></Field>
          <Field label="Volume (m³)"><input type="number" step="0.1" value={f.volume_m3} onChange={set('volume_m3')} /></Field>
          <Field label="Kilométrage"><input type="number" value={f.mileage} onChange={set('mileage')} /></Field>
          <Field label="Statut"><select value={f.status} onChange={set('status')}>{Object.entries(L.truck_status).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
          <Field label="N° d'immatriculation"><input value={f.registration_number} onChange={set('registration_number')} /></Field>
          <Field label="Dernier entretien"><input type="date" value={f.last_service_date} onChange={set('last_service_date')} /></Field>
          <Field label="Prochain entretien"><input type="date" value={f.next_service_date} onChange={set('next_service_date')} /></Field>
          <Field label="Assurance"><input value={f.insurance_info} onChange={set('insurance_info')} /></Field>
        </div>
        <Field label="Ce camion est en"><select value={f.ownership} onChange={set('ownership')}>{Object.entries(L.ownership).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
        {set2 && <div className="grid3">
          <Field label="Loueur *"><input value={f.rental_company} onChange={set('rental_company')} /></Field>
          <Field label="N° de contrat"><input value={f.rental_contract_no} onChange={set('rental_contract_no')} /></Field>
          <Field label="Loyer mensuel (€)"><input type="number" step="0.01" value={f.rental_monthly_cost} onChange={set('rental_monthly_cost')} /></Field>
          <Field label="Début du contrat"><input type="date" value={f.rental_start_date} onChange={set('rental_start_date')} /></Field>
          <Field label="Fin du contrat *"><input type="date" value={f.rental_end_date} onChange={set('rental_end_date')} /></Field>
        </div>}
        <div className="actions"><button className="btn" disabled={busy}>Enregistrer</button>{!isNew && profile.role === 'admin' && <button type="button" className="btn danger" onClick={remove}>Supprimer</button>}</div>
      </form>

      {!isNew && <>
        <section className="card"><h2>Conducteur affecté</h2>
          <div className="actions"><select style={{ maxWidth: 320 }} value={data.assign?.driver_id ?? ''} onChange={(e) => assign(e.target.value || null)}>
            <option value="">— Non affecté —</option>{data.drivers.map((d) => <option key={d.id} value={d.id}>{fullName(d)}</option>)}</select></div></section>
        {set2 && <Renewal truck={data.truck} renewals={data.renew} reload={reload} />}
        <Documents truckId={Number(id)} docs={data.docs} reload={reload} />
        <Maintenance truck={data.truck} rows={data.maint} reload={reload} />
      </>}
    </>
  );
}

function Renewal({ truck, renewals, reload }) {
  const [d, setD] = useState(''); const [cost, setCost] = useState(''); const [note, setNote] = useState(''); const [err, setErr] = useState('');
  async function renew(e) {
    e.preventDefault(); setErr('');
    if (!d || d <= (truck.rental_end_date ?? '')) return setErr('La nouvelle date doit être après la fin actuelle.');
    const a = await supabase.from('rental_renewals').insert({ truck_id: truck.id, previous_end_date: truck.rental_end_date, new_end_date: d, monthly_cost: nul(cost), note: nul(note) });
    if (a.error) return setErr(a.error.message);
    const b = await supabase.from('trucks').update({ rental_end_date: d, ...(cost ? { rental_monthly_cost: cost } : {}) }).eq('id', truck.id);
    if (b.error) return setErr(b.error.message);
    setD(''); setCost(''); setNote(''); reload();
  }
  return (
    <section className="card"><h2>Renouveler le contrat de location</h2>
      <p className="muted">Fin actuelle : <b>{fdate(truck.rental_end_date)}</b></p><Msg>{err}</Msg>
      <form onSubmit={renew}><div className="grid3">
        <Field label="Nouvelle date de fin"><input type="date" value={d} onChange={(e) => setD(e.target.value)} required /></Field>
        <Field label="Nouveau loyer (facultatif)"><input type="number" step="0.01" value={cost} onChange={(e) => setCost(e.target.value)} /></Field>
        <Field label="Remarque"><input value={note} onChange={(e) => setNote(e.target.value)} /></Field></div>
        <button className="btn">Renouveler</button></form>
      {renewals.length > 0 && <ul className="rows" style={{ marginTop: 14 }}>{renewals.map((r) => <li key={r.id}><span>{fdate(r.previous_end_date)} → <b>{fdate(r.new_end_date)}</b> {r.note && <span className="muted">· {r.note}</span>}</span><span className="muted small">{fdate(r.created_at)}</span></li>)}</ul>}
    </section>
  );
}

function Documents({ truckId, docs, reload }) {
  const { profile } = useAuth();
  const [cat, setCat] = useState('registration'); const [title, setTitle] = useState(''); const [exp, setExp] = useState(''); const [file, setFile] = useState(null); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  async function add(e) {
    e.preventDefault(); setErr('');
    if (!file) return setErr('Choisissez un fichier.');
    if (file.size > 10 * 1048576) return setErr('Fichier trop lourd (10 Mo maximum).');
    setBusy(true);
    try {
      const path = await uploadFile('documents', `truck-${truckId}`, file);
      const { error } = await supabase.from('truck_documents').insert({ truck_id: truckId, category: cat, title: title || L.doc_category[cat], file_path: path, original_name: file.name, expires_on: nul(exp), uploaded_by: profile.id });
      if (error) { await supabase.storage.from('documents').remove([path]); throw error; }
      setTitle(''); setExp(''); setFile(null); e.target.reset(); reload();
    } catch (x) { setErr(x.message); }
    setBusy(false);
  }
  async function del(d) {
    if (!window.confirm('Supprimer ce document ?')) return;
    await supabase.storage.from('documents').remove([d.file_path]);
    await supabase.from('truck_documents').delete().eq('id', d.id); reload();
  }
  return (
    <section className="card"><h2>Documents</h2><Msg>{err}</Msg>
      <ul className="rows">{docs.map((d) => <li key={d.id}><span className="l"><b>{d.title}</b> <span className="muted small">· {L.doc_category[d.category]}{d.expires_on ? ` · expire le ${fdate(d.expires_on)}` : ''}</span></span>
        <span className="actions"><button className="btn light sm" onClick={() => openFile('documents', d.file_path, d.original_name || 'document')}>Télécharger</button><button className="btn danger sm" onClick={() => del(d)}>Supprimer</button></span></li>)}
        {!docs.length && <li className="muted">Aucun document.</li>}</ul>
      <form onSubmit={add} style={{ marginTop: 14 }}><div className="grid3">
        <Field label="Type"><select value={cat} onChange={(e) => setCat(e.target.value)}>{Object.entries(L.doc_category).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
        <Field label="Titre"><input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="facultatif" /></Field>
        <Field label="Date d'expiration"><input type="date" value={exp} onChange={(e) => setExp(e.target.value)} /></Field></div>
        <Field label="Fichier (PDF ou image, 10 Mo max)"><input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(e) => setFile(e.target.files[0])} /></Field>
        <button className="btn" disabled={busy}>Ajouter le document</button></form>
    </section>
  );
}

function Maintenance({ truck, rows, reload }) {
  const [f, setF] = useState({ service_date: today(), service_type: '', garage: '', cost: '', mileage: '', next_service_date: '', description: '' }); const [err, setErr] = useState('');
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  async function add(e) {
    e.preventDefault(); setErr('');
    const { error } = await supabase.from('maintenance_records').insert({ truck_id: truck.id, ...f, cost: nul(f.cost), mileage: nul(f.mileage), next_service_date: nul(f.next_service_date), description: nul(f.description), garage: nul(f.garage) });
    if (error) return setErr(error.message);
    await supabase.from('trucks').update({ last_service_date: f.service_date, ...(f.next_service_date ? { next_service_date: f.next_service_date } : {}), ...(f.mileage ? { mileage: Number(f.mileage) } : {}) }).eq('id', truck.id);
    setF({ ...f, service_type: '', garage: '', cost: '', mileage: '', next_service_date: '', description: '' }); reload();
  }
  return (
    <section className="card"><h2>Entretiens</h2><Msg>{err}</Msg>
      <ul className="rows">{rows.map((m) => <li key={m.id}><span className="l"><b>{m.service_type}</b> · {fdate(m.service_date)} <span className="muted small">{m.garage} {m.mileage ? `· ${m.mileage} km` : ''}</span></span><span>{money(m.cost)}</span></li>)}
        {!rows.length && <li className="muted">Aucun entretien enregistré.</li>}</ul>
      <form onSubmit={add} style={{ marginTop: 14 }}><div className="grid3">
        <Field label="Date"><input type="date" required value={f.service_date} onChange={set('service_date')} /></Field>
        <Field label="Type *"><input required value={f.service_type} onChange={set('service_type')} placeholder="Révision, pneus…" /></Field>
        <Field label="Garage"><input value={f.garage} onChange={set('garage')} /></Field>
        <Field label="Coût (€)"><input type="number" step="0.01" value={f.cost} onChange={set('cost')} /></Field>
        <Field label="Kilométrage"><input type="number" value={f.mileage} onChange={set('mileage')} /></Field>
        <Field label="Prochain entretien"><input type="date" value={f.next_service_date} onChange={set('next_service_date')} /></Field></div>
        <button className="btn">Ajouter l'entretien</button></form>
    </section>
  );
}
