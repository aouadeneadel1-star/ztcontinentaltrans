import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { Badge, PageHead, Field, Msg, Table, useAsync, q } from '../components/ui';
const validEmail = (e) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e ?? '');

export default function Helpers() {
  const [edit, setEdit] = useState(null); const [err, setErr] = useState('');
  const { loading, data, reload } = useAsync(() => q(supabase.from('helpers').select('*').order('last_name')), []);
  async function save(e) {
    e.preventDefault(); setErr('');
    const f = edit; const email = f.email.trim().toLowerCase();
    if (!f.first_name.trim() || !f.last_name.trim()) return setErr('Le nom et le prénom sont obligatoires.');
    if (!validEmail(email)) return setErr('E-mail invalide.');
    const row = { first_name: f.first_name.trim(), last_name: f.last_name.trim(), email, is_active: f.is_active ?? true };
    const { error } = f.id ? await supabase.from('helpers').update(row).eq('id', f.id) : await supabase.from('helpers').insert(row);
    if (error) return setErr(error.message);
    setEdit(null); reload();
  }
  async function del(h) {
    if (!window.confirm('Supprimer ce rippeur ?')) return;
    const { error } = await supabase.from('helpers').delete().eq('id', h.id);
    if (error) setErr(error.message); else reload();
  }
  return (
    <>
      <PageHead title="Rippeurs"><button className="btn" onClick={() => setEdit({ first_name: '', last_name: '', email: '', is_active: true })}>Nouveau rippeur</button></PageHead>
      <p className="muted">Un rippeur n'a pas de compte : on enregistre seulement son nom, son prénom et son adresse e-mail (c'est là que son planning est envoyé).</p>
      <Msg>{err}</Msg>
      {edit && <form className="card" onSubmit={save}><div className="grid3">
        <Field label="Nom *"><input value={edit.last_name} onChange={(e) => setEdit({ ...edit, last_name: e.target.value })} /></Field>
        <Field label="Prénom *"><input value={edit.first_name} onChange={(e) => setEdit({ ...edit, first_name: e.target.value })} /></Field>
        <Field label="E-mail *"><input type="email" value={edit.email ?? ''} onChange={(e) => setEdit({ ...edit, email: e.target.value })} /></Field></div>
        <div className="actions"><button className="btn">Enregistrer</button><button type="button" className="btn light" onClick={() => setEdit(null)}>Annuler</button></div></form>}
      {loading ? <p className="muted">Chargement…</p> : <Table rows={data} empty="Aucun rippeur pour le moment." cols={[
        { h: 'Nom', r: (h) => <b>{h.last_name} {h.first_name}</b> },
        { h: 'E-mail', r: (h) => h.email || <span className="badge orange">À renseigner</span> },
        { h: 'Statut', r: (h) => (h.is_active ? <span className="badge green">Actif</span> : <span className="badge neutral">Désactivé</span>) },
        { h: '', r: (h) => <span className="actions"><button className="btn light sm" onClick={() => setEdit({ ...h, email: h.email ?? '' })}>Modifier</button>
          <button className="btn light sm" onClick={async () => { await supabase.from('helpers').update({ is_active: !h.is_active }).eq('id', h.id); reload(); }}>{h.is_active ? 'Désactiver' : 'Réactiver'}</button>
          <button className="btn danger sm" onClick={() => del(h)}>Supprimer</button></span> },
      ]} />}
    </>
  );
}
