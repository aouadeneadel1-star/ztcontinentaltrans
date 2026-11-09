import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { useAuth } from '../auth';
import { PageHead, Field, Msg, Table, useAsync, q } from '../components/ui';
import { L, fullName, fdatetime } from '../lib/utils';

async function call(body) {
  const { data, error } = await supabase.functions.invoke('admin-users', { body });
  if (error) { let m = error.message; try { m = (await error.context.json()).error ?? m; } catch { /* ignore */ } throw new Error(m); }
  if (data?.error) throw new Error(data.error);
  return data;
}
const linkOf = (token) => `${window.location.origin}/inscription?token=${token}`;

function InviteLink({ token, name }) {
  const [copied, setCopied] = useState(false);
  const url = linkOf(token);
  const text = `Bonjour${name ? ' ' + name : ''}, voici le lien pour créer votre compte ZT Continental Trans : ${url}`;
  async function copy() {
    try { await navigator.clipboard.writeText(url); setCopied(true); setTimeout(() => setCopied(false), 2000); }
    catch { window.prompt('Copiez ce lien :', url); }
  }
  return (
    <span className="actions">
      <button className="btn sm" onClick={copy}>{copied ? 'Lien copié ✓' : 'Copier le lien'}</button>
      {navigator.share && <button className="btn light sm" onClick={() => navigator.share({ text }).catch(() => {})}>Partager…</button>}
    </span>
  );
}

export default function Users() {
  const { isAdmin, profile } = useAuth();
  const [f, setF] = useState(null); const [inv, setInv] = useState(null);
  const [err, setErr] = useState(''); const [ok, setOk] = useState(''); const [busy, setBusy] = useState(false);
  const users = useAsync(() => q(supabase.from('profiles').select('*').order('last_name')), []);
  const invites = useAsync(() => q(supabase.from('invitations').select('*').is('used_at', null).gt('expires_at', new Date().toISOString()).order('created_at', { ascending: false })), []);
  if (!isAdmin) return <Msg>Cette page est réservée à l'administrateur.</Msg>;

  async function createInvite(e) {
    e.preventDefault(); setErr(''); setBusy(true);
    const { data, error } = await supabase.from('invitations').insert({ role: inv.role, first_name: inv.first_name.trim() || null, last_name: inv.last_name.trim() || null, created_by: profile.id }).select('*').single();
    setBusy(false);
    if (error) return setErr(error.message);
    setInv({ ...inv, created: data }); invites.reload();
  }
  async function revoke(i) {
    if (!window.confirm('Annuler cette invitation ? Le lien ne fonctionnera plus.')) return;
    await supabase.from('invitations').delete().eq('id', i.id); invites.reload();
  }
  async function save(e) {
    e.preventDefault(); setErr(''); setOk(''); setBusy(true);
    try {
      await call({ action: 'update', id: f.id, first_name: f.first_name, last_name: f.last_name, phone: f.phone, role: f.role });
      setOk('Compte enregistré.'); setF(null); users.reload();
    } catch (x) { setErr(x.message); }
    setBusy(false);
  }
  async function toggle(u) {
    setErr('');
    try { await call({ action: 'update', id: u.id, is_active: !u.is_active }); users.reload(); } catch (x) { setErr(x.message); }
  }
  return (
    <>
      <PageHead title="Utilisateurs"><button className="btn" onClick={() => { setF(null); setInv({ role: 'driver', first_name: '', last_name: '', created: null }); }}>Inviter un conducteur</button></PageHead>
      <Msg>{err}</Msg><Msg type="ok">{ok}</Msg>

      {inv && (
        <section className="card">
          {!inv.created ? (
            <form onSubmit={createInvite}><h2>Nouvelle invitation</h2>
              <p className="muted">Vous obtenez un lien à envoyer à la personne : elle crée elle-même son compte (valable 7 jours, une seule utilisation).</p>
              <div className="grid3">
                <Field label="Prénom (facultatif)"><input value={inv.first_name} onChange={(e) => setInv({ ...inv, first_name: e.target.value })} /></Field>
                <Field label="Nom (facultatif)"><input value={inv.last_name} onChange={(e) => setInv({ ...inv, last_name: e.target.value })} /></Field>
                <Field label="Rôle"><select value={inv.role} onChange={(e) => setInv({ ...inv, role: e.target.value })}><option value="driver">Conducteur</option><option value="manager">Responsable de flotte</option></select></Field></div>
              <div className="actions"><button className="btn" disabled={busy}>Créer le lien</button><button type="button" className="btn light" onClick={() => setInv(null)}>Annuler</button></div></form>
          ) : (
            <div><h2>Lien prêt</h2>
              <p className="muted">Envoyez ce lien à la personne (message, e-mail…). Valable 7 jours, à usage unique.</p>
              <input readOnly value={linkOf(inv.created.token)} onFocus={(e) => e.target.select()} style={{ marginBottom: 10 }} />
              <div className="actions"><InviteLink token={inv.created.token} name={inv.first_name} /><button className="btn light sm" onClick={() => setInv(null)}>Fermer</button></div></div>
          )}
        </section>
      )}

      {invites.data?.length > 0 && (
        <section className="card"><h2>Invitations en attente</h2>
          <ul className="rows">{invites.data.map((i) => (
            <li key={i.id}><span><b>{[i.first_name, i.last_name].filter(Boolean).join(' ') || 'Sans nom'}</b> <span className="muted small">· {L.roles[i.role]} · expire le {fdatetime(i.expires_at)}</span></span>
              <span className="actions"><InviteLink token={i.token} name={i.first_name} /><button className="btn danger sm" onClick={() => revoke(i)}>Annuler</button></span></li>))}</ul></section>
      )}

      {f && <form className="card" onSubmit={save}><h2>Modifier</h2><div className="grid3">
        <Field label="Prénom"><input required value={f.first_name} onChange={(e) => setF({ ...f, first_name: e.target.value })} /></Field>
        <Field label="Nom"><input required value={f.last_name} onChange={(e) => setF({ ...f, last_name: e.target.value })} /></Field>
        <Field label="Téléphone"><input type="tel" value={f.phone ?? ''} onChange={(e) => setF({ ...f, phone: e.target.value })} /></Field>
        <Field label="Rôle"><select value={f.role} onChange={(e) => setF({ ...f, role: e.target.value })}>{Object.entries(L.roles).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></Field></div>
        <div className="actions"><button className="btn" disabled={busy}>Enregistrer</button><button type="button" className="btn light" onClick={() => setF(null)}>Annuler</button></div></form>}

      {users.loading ? <p className="muted">Chargement…</p> : <Table rows={users.data} cols={[
        { h: 'Nom', r: (u) => <b>{fullName(u)}</b> }, { h: 'Rôle', r: (u) => L.roles[u.role] },
        { h: 'E-mail', cls: 'hide-sm', r: (u) => u.email ?? '—' },
        { h: 'Statut', r: (u) => (u.is_active ? <span className="badge green">Actif</span> : <span className="badge neutral">Désactivé</span>) },
        { h: '', r: (u) => <span className="actions"><button className="btn light sm" onClick={() => { setInv(null); setF({ ...u }); }}>Modifier</button>
          {u.id !== profile.id && <button className="btn light sm" onClick={() => toggle(u)}>{u.is_active ? 'Désactiver' : 'Réactiver'}</button>}</span> },
      ]} />}
    </>
  );
}
