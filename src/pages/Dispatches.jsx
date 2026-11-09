import { useState, useEffect } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { supabase, sheetUrl } from '../lib/supabase';
import { useAuth } from '../auth';
import { Badge, Plate, PageHead, Field, Msg, Table, useAsync, q } from '../components/ui';
import { L, fdate, fdateLong, fullName, uploadFile, today, normalizePhone, prettyPhone, waLink } from '../lib/utils';

const SELECT = '*, trucks(plate,brand,model), driver:profiles!dispatches_driver_id_fkey(first_name,last_name,phone), h1:helpers!dispatches_helper1_id_fkey(first_name,last_name,whatsapp), h2:helpers!dispatches_helper2_id_fkey(first_name,last_name,whatsapp), dispatch_tours(*)';
const team = (d) => [d.h1, d.h2].filter(Boolean).map(fullName);

export default function Dispatches() {
  const [date, setDate] = useState(''); const [status, setStatus] = useState('');
  const { loading, data, error } = useAsync(() => {
    let r = supabase.from('dispatches').select(SELECT).order('work_date', { ascending: false }).order('id', { ascending: false }).limit(100);
    if (date) r = r.eq('work_date', date); if (status) r = r.eq('status', status);
    return q(r);
  }, [date, status]);
  return (
    <>
      <PageHead title="Tournées"><Link className="btn" to="/tournees/nouveau">Nouveau planning</Link></PageHead>
      <div className="card"><div className="grid3">
        <Field label="Date"><input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
        <Field label="Statut"><select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">Tous</option>{Object.entries(L.dispatch_status).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field></div></div>
      <Msg>{error}</Msg>
      {loading ? <p className="muted">Chargement…</p> : <Table rows={data} empty="Aucun planning." cols={[
        { h: 'Date', r: (d) => <Link to={`/tournees/${d.id}`}><b>{fdate(d.work_date)}</b></Link> },
        { h: 'Équipe', r: (d) => <>{fullName(d.driver)}<br /><span className="muted small">{team(d).join(', ') || 'Sans rippeur'}</span></> },
        { h: 'Camion', r: (d) => <Plate>{d.trucks.plate}</Plate> },
        { h: 'Missions', r: (d) => [...d.dispatch_tours].sort((a, b) => a.position - b.position).map((t) => <div key={t.id}>{t.client} – {t.city}</div>) },
        { h: 'Statut', r: (d) => <Badge kind="dispatch_status" value={d.status} /> },
      ]} />}
    </>
  );
}

const blank = () => ({ key: crypto.randomUUID(), id: null, kind: 'tour', client: '', city: '', file: null, original_name: '', file_path: null });

export function DispatchForm() {
  const { id } = useParams(); const nav = useNavigate(); const { profile } = useAuth();
  const [f, setF] = useState({ work_date: new Date(Date.now() + 864e5).toISOString().slice(0, 10), driver_id: '', truck_id: '', helper1_id: '', helper2_id: '', note: '' });
  const [tours, setTours] = useState([blank()]); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const ref = useAsync(async () => {
    const [drivers, helpers, trucks, assigns, d] = await Promise.all([
      q(supabase.from('profiles').select('id,first_name,last_name,phone').eq('role', 'driver').eq('is_active', true).order('last_name')),
      q(supabase.from('helpers').select('id,first_name,last_name').eq('is_active', true).order('last_name')),
      q(supabase.from('trucks').select('id,plate,brand,model').order('plate')),
      q(supabase.from('truck_assignments').select('truck_id,driver_id').is('end_date', null)),
      id ? q(supabase.from('dispatches').select('*, dispatch_tours(*)').eq('id', id).single()) : null,
    ]);
    return { drivers, helpers, trucks, assigns, d };
  }, [id]);
  useEffect(() => {
    const d = ref.data?.d; if (!d) return;
    if (d.status !== 'draft') { nav(`/tournees/${d.id}`); return; }
    setF({ work_date: d.work_date, driver_id: d.driver_id, truck_id: d.truck_id, helper1_id: d.helper1_id ?? '', helper2_id: d.helper2_id ?? '', note: d.note ?? '' });
    setTours([...d.dispatch_tours].sort((a, b) => a.position - b.position).map((t) => ({ key: t.id, id: t.id, kind: t.kind, client: t.client, city: t.city, file: null, original_name: t.original_name ?? '', file_path: t.file_path })));
  }, [ref.data]); // eslint-disable-line
  if (ref.loading) return <p className="muted">Chargement…</p>;
  const { drivers, helpers, trucks, assigns } = ref.data;
  const upd = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const setTour = (key, patch) => setTours(tours.map((t) => (t.key === key ? { ...t, ...patch } : t)));
  function pickDriver(e) {
    const driver_id = e.target.value; const a = assigns.find((x) => x.driver_id === driver_id);
    setF({ ...f, driver_id, truck_id: f.truck_id || (a ? a.truck_id : '') });
  }

  async function save(e) {
    e.preventDefault(); setErr('');
    const drv = drivers.find((d) => d.id === f.driver_id);
    if (!f.work_date) return setErr('La date est obligatoire.');
    if (!drv) return setErr('Choisissez un conducteur.');
    if (!normalizePhone(drv.phone)) return setErr("Le conducteur n'a pas de numéro de téléphone valide : renseignez-le dans Utilisateurs.");
    if (!f.truck_id) return setErr('Choisissez un camion.');
    if (f.helper1_id && f.helper1_id === f.helper2_id) return setErr('Les deux rippeurs doivent être différents.');
    const list = tours.filter((t) => t.client.trim() || t.city.trim());
    if (!list.length) return setErr('Ajoutez au moins une tournée ou une course.');
    if (list.some((t) => !t.client.trim() || !t.city.trim())) return setErr('Chaque tournée ou course doit avoir un client et une ville.');
    if (list.some((t) => t.file && t.file.size > 10 * 1048576)) return setErr('Une feuille dépasse 10 Mo.');
    setBusy(true);
    const uploaded = [];
    try {
      const row = { work_date: f.work_date, driver_id: f.driver_id, truck_id: Number(f.truck_id), helper1_id: f.helper1_id ? Number(f.helper1_id) : null, helper2_id: f.helper2_id ? Number(f.helper2_id) : null, note: f.note.trim() || null };
      let did = id ? Number(id) : null;
      if (did) { const { error } = await supabase.from('dispatches').update(row).eq('id', did); if (error) throw error; }
      else { const { data, error } = await supabase.from('dispatches').insert({ ...row, status: 'draft', created_by: profile.id }).select('id').single(); if (error) throw error; did = data.id; }
      const old = ref.data.d?.dispatch_tours ?? []; const toRemove = [];
      const keep = new Set(list.filter((t) => t.id).map((t) => t.id));
      for (const o of old) if (!keep.has(o.id)) { await supabase.from('dispatch_tours').delete().eq('id', o.id); if (o.file_path) toRemove.push(o.file_path); }
      for (const [i, t] of list.entries()) {
        const r = { dispatch_id: did, position: i + 1, kind: t.kind, client: t.client.trim(), city: t.city.trim() };
        if (t.file) {
          const path = await uploadFile('sheets', `dispatch-${did}`, t.file); uploaded.push(path);
          if (t.file_path) toRemove.push(t.file_path);
          Object.assign(r, { file_path: path, original_name: t.file.name });
        }
        const { error } = t.id ? await supabase.from('dispatch_tours').update(r).eq('id', t.id) : await supabase.from('dispatch_tours').insert(r);
        if (error) throw error;
      }
      if (toRemove.length) await supabase.storage.from('sheets').remove(toRemove);
      nav(`/tournees/${did}`);
    } catch (x) { if (uploaded.length) await supabase.storage.from('sheets').remove(uploaded); setErr(x.message ?? String(x)); }
    setBusy(false);
  }
  return (
    <>
      <PageHead title={id ? 'Modifier le planning' : 'Nouveau planning'}><Link className="btn light" to="/tournees">Annuler</Link></PageHead>
      <Msg>{err}</Msg>
      <form className="card" onSubmit={save}>
        <div className="grid2">
          <Field label="Date *"><input type="date" required value={f.work_date} onChange={upd('work_date')} /></Field>
          <Field label="Conducteur *"><select required value={f.driver_id} onChange={pickDriver}><option value="">— Choisir —</option>{drivers.map((d) => <option key={d.id} value={d.id}>{fullName(d)}</option>)}</select></Field>
          <Field label="Camion *" hint="Le camion habituel du conducteur est proposé automatiquement."><select required value={f.truck_id} onChange={upd('truck_id')}><option value="">— Choisir —</option>{trucks.map((t) => <option key={t.id} value={t.id}>{t.plate} – {t.brand} {t.model}</option>)}</select></Field>
          <div />
          <Field label="Rippeur 1"><select value={f.helper1_id} onChange={upd('helper1_id')}><option value="">— Aucun —</option>{helpers.map((h) => <option key={h.id} value={h.id}>{h.last_name} {h.first_name}</option>)}</select></Field>
          <Field label="Rippeur 2"><select value={f.helper2_id} onChange={upd('helper2_id')}><option value="">— Aucun —</option>{helpers.map((h) => <option key={h.id} value={h.id}>{h.last_name} {h.first_name}</option>)}</select></Field>
        </div>
        <h2 style={{ marginTop: 18 }}>Missions du jour</h2>
        {tours.map((t) => (
          <div className="tour-row" key={t.key}>
            <div className="grid3">
              <Field label="Type"><select value={t.kind} onChange={(e) => setTour(t.key, { kind: e.target.value })}>{Object.entries(L.tour_kind).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
              <Field label="Client *"><input value={t.client} maxLength={120} placeholder="IKEA, Leroy Merlin…" onChange={(e) => setTour(t.key, { client: e.target.value })} /></Field>
              <Field label="Ville *"><input value={t.city} maxLength={120} onChange={(e) => setTour(t.key, { city: e.target.value })} /></Field>
            </div>
            <Field label="Feuille de tournée (PDF ou photo)" hint={t.original_name && !t.file ? `Actuelle : ${t.original_name}. Choisir un fichier pour la remplacer.` : undefined}>
              <input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(e) => setTour(t.key, { file: e.target.files[0] ?? null })} /></Field>
            {tours.length > 1 && <button type="button" className="btn light sm" onClick={() => setTours(tours.filter((x) => x.key !== t.key))}>Retirer</button>}
          </div>
        ))}
        {tours.length < 6 && <button type="button" className="btn light" onClick={() => setTours([...tours, blank()])}>+ Ajouter une tournée ou une course</button>}
        <Field label="Remarque (facultatif)" className="" ><textarea rows={2} maxLength={500} value={f.note} onChange={upd('note')} style={{ marginTop: 14 }} /></Field>
        <div className="actions"><button className="btn" disabled={busy}>{busy ? 'Enregistrement…' : "Enregistrer et voir l'aperçu"}</button></div>
      </form>
    </>
  );
}

// Aperçu du message (même texte que celui construit par la fonction d'envoi)
function preview(d, tours) {
  const date = fdateLong(d.work_date);
  const truck = `${d.trucks.plate} (${`${d.trucks.brand} ${d.trucks.model}`.trim()})`;
  const lines = tours.map((t, i) => `• ${L.tour_kind[t.kind]} ${i + 1} : ${t.client} – ${t.city}`);
  const links = tours.map((t, i) => (t.file_path ? `• ${i + 1} (${t.client}) : ${sheetUrl(t.token)}` : null)).filter(Boolean);
  const drvName = fullName(d.driver); const hs = [d.h1, d.h2].filter(Boolean);
  const people = [{ type: 'driver', name: drvName, first: d.driver.first_name, phone: normalizePhone(d.driver.phone) }, ...hs.map((h) => ({ type: 'helper', name: fullName(h), first: h.first_name, phone: normalizePhone(h.whatsapp) }))];
  return people.map((p) => {
    const others = hs.map(fullName).filter((n) => n !== p.name);
    const tm = p.type === 'driver' ? (hs.length ? `rippeur${hs.length > 1 ? 's' : ''} ${hs.map(fullName).join(' et ')}` : 'aucun rippeur') : `conducteur ${drvName}${others.length ? `, avec ${others.join(' et ')}` : ''}`;
    let body = `Bonjour ${p.first},\nVotre planning du ${date} :\n\nCamion : ${truck}\nÉquipe : ${tm}\n\nMissions :\n${lines.join('\n')}`;
    if (links.length) body += `\n\nTélécharger la feuille :\n${links.join('\n')}`;
    if (d.note) body += `\n\nRemarque : ${d.note.replace(/\s+/g, ' ')}`;
    return { ...p, body: body + '\n\nZT Continental Trans' };
  });
}

export function DispatchShow() {
  const { id } = useParams(); const nav = useNavigate();
  const [err, setErr] = useState(''); const [ok, setOk] = useState(''); const [busy, setBusy] = useState(false);
  const { loading, data, reload } = useAsync(async () => {
    const [d, msgs, conflicts] = await Promise.all([
      q(supabase.from('dispatches').select(SELECT).eq('id', id).single()),
      q(supabase.from('dispatch_messages').select('*').eq('dispatch_id', id).order('id')),
      q(supabase.from('dispatches').select('id,truck_id,driver_id,helper1_id,helper2_id,work_date').neq('id', id)),
    ]);
    return { d, msgs, conflicts: conflicts.filter((o) => o.work_date === d.work_date && (o.truck_id === d.truck_id || o.driver_id === d.driver_id || [d.helper1_id, d.helper2_id].filter(Boolean).some((h) => h === o.helper1_id || h === o.helper2_id))) };
  }, [id]);
  if (loading) return <p className="muted">Chargement…</p>;
  const { d, msgs, conflicts } = data; const tours = [...d.dispatch_tours].sort((a, b) => a.position - b.position);
  const draft = d.status === 'draft';

  async function send() {
    setErr(''); setOk(''); setBusy(true);
    const { data: r, error } = await supabase.functions.invoke('send-dispatch', { body: { dispatch_id: Number(id) } });
    let msg = error?.message;
    if (error) { try { msg = (await error.context.json()).error ?? msg; } catch { /* ignore */ } }
    setBusy(false);
    if (msg || r?.error) setErr(msg ?? r.error);
    else setOk(r.auto ? (r.status === 'sent' ? 'Messages WhatsApp envoyés.' : "Certains messages n'ont pas pu être envoyés. Voir le détail ci-dessous.") : "Envoi automatique non configuré : utilisez les boutons WhatsApp ci-dessous pour envoyer chaque message.");
    reload();
  }
  async function mark(m) {
    await supabase.from('dispatch_messages').update({ status: 'sent', sent_at: new Date().toISOString(), error_message: null }).eq('id', m.id);
    const sent = msgs.filter((x) => x.id === m.id || x.status === 'sent').length;
    await supabase.from('dispatches').update({ status: sent === msgs.length ? 'sent' : 'partial' }).eq('id', d.id); reload();
  }
  async function del() {
    if (!window.confirm('Supprimer ce planning et ses fichiers ?')) return;
    const paths = tours.map((t) => t.file_path).filter(Boolean);
    if (paths.length) await supabase.storage.from('sheets').remove(paths);
    const { error } = await supabase.from('dispatches').delete().eq('id', d.id);
    if (error) setErr(error.message); else nav('/tournees');
  }
  return (
    <>
      <PageHead title={`Planning du ${fdateLong(d.work_date)}`}><Badge kind="dispatch_status" value={d.status} /></PageHead>
      <Msg>{err}</Msg><Msg type="ok">{ok}</Msg>
      {conflicts.length > 0 && <Msg type="info">Attention : le camion, le conducteur ou un rippeur est déjà prévu dans un autre planning ce jour-là.</Msg>}
      <section className="card"><ul className="rows">
        <li><span className="muted">Conducteur</span><b>{fullName(d.driver)}</b></li>
        <li><span className="muted">Rippeurs</span><b>{team(d).join(', ') || '—'}</b></li>
        <li><span className="muted">Camion</span><span><Plate>{d.trucks.plate}</Plate> {d.trucks.brand} {d.trucks.model}</span></li>
        {d.note && <li><span className="muted">Remarque</span><span>{d.note}</span></li>}</ul></section>
      <section className="card"><h2>Missions</h2><ul className="rows">{tours.map((t, i) => <li key={t.id}><span><b>{L.tour_kind[t.kind]} {i + 1}</b> · {t.client} – {t.city}</span><span className="muted small">{t.file_path ? t.original_name : 'Sans feuille'}</span></li>)}</ul></section>

      {draft ? (
        <section className="card"><h2>Aperçu des messages</h2>
          {preview(d, tours).map((m) => <div className="wa" key={m.name}><b>{m.name}</b> · {m.phone ? prettyPhone(m.phone) : <span className="badge red">Numéro invalide</span>}<pre>{m.body}</pre></div>)}
          <div className="actions"><button className="btn" disabled={busy} onClick={send}>{busy ? 'Envoi…' : 'Valider et envoyer par WhatsApp'}</button><Link className="btn light" to={`/tournees/${d.id}/modifier`}>Modifier</Link></div>
          <p className="hint">Sans configuration WhatsApp, un bouton « Ouvrir WhatsApp » sera proposé pour chaque personne après validation.</p></section>
      ) : (
        <section className="card"><h2>Envois</h2>
          <ul className="rows">{msgs.map((m) => (
            <li key={m.id}><span className="l"><b>{m.recipient_name}</b> <span className="muted small">{prettyPhone(m.phone)}</span>{m.error_message && <><br /><span className="small" style={{ color: 'var(--red)' }}>{m.error_message}</span></>}</span>
              <span className="actions"><span className={`badge ${{ sent: 'green', failed: 'red', pending: 'orange' }[m.status]}`}>{{ sent: 'Envoyé', failed: 'Échec', pending: 'À envoyer' }[m.status]}</span>
                {m.status !== 'sent' && <><a className="btn sm" target="_blank" rel="noreferrer" href={waLink(m.phone, m.body)}>Ouvrir WhatsApp</a><button className="btn light sm" onClick={() => mark(m)}>C'est envoyé</button></>}</span></li>))}</ul>
          {d.status !== 'sent' && <div className="actions" style={{ marginTop: 12 }}><button className="btn" disabled={busy} onClick={send}>Renvoyer automatiquement les messages non envoyés</button></div>}
        </section>
      )}
      <button className="btn danger" onClick={del}>Supprimer ce planning</button>
    </>
  );
}
