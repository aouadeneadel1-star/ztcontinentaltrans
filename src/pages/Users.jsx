import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { useAuth } from '../auth';
import { PageHead, Field, Msg, Table, useAsync, q } from '../components/ui';
import { L, fullName } from '../lib/utils';

async function call(body) {
  const { data, error } = await supabase.functions.invoke('admin-users', { body });
  if (error) { let m = error.message; try { m = (await error.context.json()).error ?? m; } catch { /* ignore */ } throw new Error(m); }
  if (data?.error) throw new Error(data.error);
  return data;
}

export default function Users() {
  const { isAdmin, profile } = useAuth();
  const [f, setF] = useState(null); const [err, setErr] = useState(''); const [ok, setOk] = useState(''); const [busy, setBusy] = useState(false);
  const { loading, data, reload } = useAsync(() => q(supabase.from('profiles').select('*').order('last_name')), []);
  const [emails] = useState({});
  if (!isAdmin) return <Msg>Cette page est réservée à l'administrateur.</Msg>;
  async function save(e) {
    e.preventDefault(); setErr(''); setOk(''); setBusy(true);
    try {
      if (f.id) await call({ action: 'update', id: f.id, first_name: f.first_name, last_name: f.last_name, phone: f.phone, role: f.role, ...(f.password ? { password: f.password } : {}) });
      else await call({ action: 'create', ...f });
      setOk('Compte enregistré.'); setF(null); reload();
    } catch (x) { setErr(x.message); }
    setBusy(false);
  }
  async function toggle(u) {
    setErr('');
    try { await call({ action: 'update', id: u.id, is_active: !u.is_active }); reload(); } catch (x) { setErr(x.message); }
  }
  return (
    <>
      <PageHead title="Utilisateurs"><button className="btn" onClick={() => setF({ role: 'driver', first_name: '', last_name: '', email: '', phone: '', password: '' })}>Nouvel utilisateur</button></PageHead>
      <Msg>{err}</Msg><Msg type="ok">{ok}</Msg>
      {f && <form className="card" onSubmit={save}><h2>{f.id ? 'Modifier' : 'Nouveau compte'}</h2><div className="grid3">
        <Field label="Prénom"><input required value={f.first_name} onChange={(e) => setF({ ...f, first_name: e.target.value })} /></Field>
        <Field label="Nom"><input required value={f.last_name} onChange={(e) => setF({ ...f, last_name: e.target.value })} /></Field>
        <Field label="Téléphone / WhatsApp"><input type="tel" value={f.phone ?? ''} onChange={(e) => setF({ ...f, phone: e.target.value })} /></Field>
        {!f.id && <Field label="E-mail"><input type="email" required value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></Field>}
        <Field label="Rôle"><select value={f.role} onChange={(e) => setF({ ...f, role: e.target.value })}>{Object.entries(L.roles).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field>
        <Field label={f.id ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe provisoire'} hint="10 caractères minimum, à changer à la première connexion"><input type="text" required={!f.id} minLength={f.id ? 0 : 10} value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} autoComplete="off" /></Field></div>
        <div className="actions"><button className="btn" disabled={busy}>Enregistrer</button><button type="button" className="btn light" onClick={() => setF(null)}>Annuler</button></div></form>}
      {loading ? <p className="muted">Chargement…</p> : <Table rows={data} cols={[
        { h: 'Nom', r: (u) => <b>{fullName(u)}</b> }, { h: 'Rôle', r: (u) => L.roles[u.role] }, { h: 'Téléphone', cls: 'hide-sm', r: (u) => u.phone ?? '—' },
        { h: 'Statut', r: (u) => (u.is_active ? <span className="badge green">Actif</span> : <span className="badge neutral">Désactivé</span>) },
        { h: '', r: (u) => <span className="actions"><button className="btn light sm" onClick={() => setF({ ...u, password: '' })}>Modifier</button>
          {u.id !== profile.id && <button className="btn light sm" onClick={() => toggle(u)}>{u.is_active ? 'Désactiver' : 'Réactiver'}</button>}</span> },
      ]} />}
    </>
  );
}
