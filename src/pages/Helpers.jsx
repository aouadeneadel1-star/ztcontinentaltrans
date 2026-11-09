import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { Badge, PageHead, Field, Msg, Table, useAsync, q } from '../components/ui';
import { normalizePhone, prettyPhone } from '../lib/utils';

export default function Helpers() {
  const [edit, setEdit] = useState(null); const [err, setErr] = useState('');
  const { loading, data, reload } = useAsync(() => q(supabase.from('helpers').select('*').order('last_name')), []);
  async function save(e) {
    e.preventDefault(); setErr('');
    const f = edit; const phone = normalizePhone(f.whatsapp);
    if (!f.first_name.trim() || !f.last_name.trim()) return setErr('Le nom et le prénom sont obligatoires.');
    if (!phone) return setErr('Numéro WhatsApp invalide. Exemple : 06 12 34 56 78 ou +33 6 12 34 56 78.');
    const row = { first_name: f.first_name.trim(), last_name: f.last_name.trim(), whatsapp: phone, is_active: f.is_active ?? true };
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
      <PageHead title="Rippeurs"><button className="btn" onClick={() => setEdit({ first_name: '', last_name: '', whatsapp: '', is_active: true })}>Nouveau rippeur</button></PageHead>
      <p className="muted">Un rippeur n'a pas de compte : on enregistre seulement son nom, son prénom et son numéro WhatsApp.</p>
      <Msg>{err}</Msg>
      {edit && <form className="card" onSubmit={save}><div className="grid3">
        <Field label="Nom *"><input value={edit.last_name} onChange={(e) => setEdit({ ...edit, last_name: e.target.value })} /></Field>
        <Field label="Prénom *"><input value={edit.first_name} onChange={(e) => setEdit({ ...edit, first_name: e.target.value })} /></Field>
        <Field label="Numéro WhatsApp *" hint="06 12 34 56 78 ou +33 6 12 34 56 78"><input type="tel" value={edit.whatsapp} onChange={(e) => setEdit({ ...edit, whatsapp: e.target.value })} /></Field></div>
        <div className="actions"><button className="btn">Enregistrer</button><button type="button" className="btn light" onClick={() => setEdit(null)}>Annuler</button></div></form>}
      {loading ? <p className="muted">Chargement…</p> : <Table rows={data} empty="Aucun rippeur pour le moment." cols={[
        { h: 'Nom', r: (h) => <b>{h.last_name} {h.first_name}</b> },
        { h: 'WhatsApp', r: (h) => prettyPhone(h.whatsapp) },
        { h: 'Statut', r: (h) => (h.is_active ? <span className="badge green">Actif</span> : <span className="badge neutral">Désactivé</span>) },
        { h: '', r: (h) => <span className="actions"><button className="btn light sm" onClick={() => setEdit({ ...h, whatsapp: '+' + h.whatsapp })}>Modifier</button>
          <button className="btn light sm" onClick={async () => { await supabase.from('helpers').update({ is_active: !h.is_active }).eq('id', h.id); reload(); }}>{h.is_active ? 'Désactiver' : 'Réactiver'}</button>
          <button className="btn danger sm" onClick={() => del(h)}>Supprimer</button></span> },
      ]} />}
    </>
  );
}
