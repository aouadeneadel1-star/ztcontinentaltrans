import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { useAuth } from '../auth';
import { Badge, Plate, PageHead, Field, Msg, Table, Photo, useAsync, q } from '../components/ui';
import { L, fdatetime, fullName } from '../lib/utils';

export default function Incidents() {
  const [status, setStatus] = useState('open');
  const { loading, data, error } = useAsync(() => q(supabase.from('incidents').select('*, trucks(plate), profiles(first_name,last_name)').order('created_at', { ascending: false })), []);
  const rows = (data ?? []).filter((i) => (status === 'open' ? i.status !== 'resolved' : status === 'all' ? true : i.status === status));
  return (
    <>
      <PageHead title="Signalements"><select value={status} onChange={(e) => setStatus(e.target.value)} style={{ width: 'auto' }}>
        <option value="open">Ouverts</option><option value="all">Tous</option>{Object.entries(L.incident_status).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></PageHead>
      <Msg>{error}</Msg>
      {loading ? <p className="muted">Chargement…</p> : <Table rows={rows} empty="Aucun signalement." cols={[
        { h: 'Date', r: (i) => fdatetime(i.created_at) },
        { h: 'Camion', r: (i) => <Link to={`/signalements/${i.id}`}><Plate>{i.trucks.plate}</Plate></Link> },
        { h: 'Problème', r: (i) => <>{L.incident_category[i.category]}{i.safety_risk && <> · <span className="badge red">Sécurité</span></>}</> },
        { h: 'Conducteur', cls: 'hide-sm', r: (i) => fullName(i.profiles) },
        { h: 'Priorité', r: (i) => <Badge kind="priority" value={i.priority} /> },
        { h: 'Statut', r: (i) => <Badge kind="incident_status" value={i.status} /> },
      ]} />}
    </>
  );
}

export function IncidentDetail() {
  const { id } = useParams(); const { profile } = useAuth();
  const [txt, setTxt] = useState(''); const [err, setErr] = useState('');
  const { loading, data, reload } = useAsync(async () => {
    const [inc, photos, comments] = await Promise.all([
      q(supabase.from('incidents').select('*, trucks(plate,brand,model), profiles(first_name,last_name)').eq('id', id).single()),
      q(supabase.from('incident_photos').select('*').eq('incident_id', id)),
      q(supabase.from('incident_comments').select('*, profiles(first_name,last_name)').eq('incident_id', id).order('created_at')),
    ]);
    return { inc, photos, comments };
  }, [id]);
  async function setStatus(status) {
    const { error } = await supabase.from('incidents').update({ status, resolved_at: status === 'resolved' ? new Date().toISOString() : null }).eq('id', id);
    if (error) setErr(error.message); else reload();
  }
  async function comment(e) {
    e.preventDefault(); if (!txt.trim()) return;
    const { error } = await supabase.from('incident_comments').insert({ incident_id: Number(id), author_id: profile.id, body: txt.trim() });
    if (error) setErr(error.message); else { setTxt(''); reload(); }
  }
  if (loading) return <p className="muted">Chargement…</p>;
  const { inc, photos, comments } = data;
  return (
    <>
      <PageHead title={<><Plate>{inc.trucks.plate}</Plate> {L.incident_category[inc.category]}</>}><Link className="btn light" to="/signalements">Retour</Link></PageHead>
      <Msg>{err}</Msg>
      <section className="card">
        <p><Badge kind="priority" value={inc.priority} /> &nbsp; <Badge kind="incident_status" value={inc.status} /> {inc.safety_risk && <> &nbsp; <span className="badge red">Risque de sécurité</span></>}</p>
        <p style={{ whiteSpace: 'pre-wrap' }}>{inc.description}</p>
        <p className="muted small">Signalé par {fullName(inc.profiles)} le {fdatetime(inc.created_at)}</p>
        <div className="photos">{photos.map((p) => <Photo key={p.id} path={p.file_path} />)}</div>
        <div className="actions" style={{ marginTop: 14 }}>{Object.entries(L.incident_status).filter(([k]) => k !== inc.status).map(([k, v]) => <button key={k} className="btn light sm" onClick={() => setStatus(k)}>Passer en « {v} »</button>)}</div>
      </section>
      <section className="card"><h2>Commentaires</h2>
        <ul className="rows">{comments.map((c) => <li key={c.id}><span className="l"><b>{fullName(c.profiles)}</b> · <span className="muted small">{fdatetime(c.created_at)}</span><br />{c.body}</span></li>)}{!comments.length && <li className="muted">Aucun commentaire.</li>}</ul>
        <form onSubmit={comment} style={{ marginTop: 12 }}><Field><textarea rows={2} value={txt} onChange={(e) => setTxt(e.target.value)} placeholder="Ajouter un commentaire" /></Field><button className="btn">Publier</button></form>
      </section>
    </>
  );
}
