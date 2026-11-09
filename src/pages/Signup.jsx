import { useEffect, useState } from 'react';
import { supabase } from '../lib/supabase';
import { Field, Msg } from '../components/ui';

async function call(body) {
  const { data, error } = await supabase.functions.invoke('signup', { body });
  if (error) { let m = error.message; try { m = (await error.context.json()).error ?? m; } catch { /* ignore */ } throw new Error(m); }
  return data;
}

// Page ouverte depuis le lien d'invitation : le conducteur crée lui-même son compte.
export default function Signup() {
  const token = new URLSearchParams(window.location.search).get('token') ?? '';
  const [state, setState] = useState('check'); // check | ok | invalid | done
  const [f, setF] = useState({ first_name: '', last_name: '', phone: '', email: '', password: '', password2: '' });
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);

  useEffect(() => {
    call({ action: 'check', token }).then((r) => {
      if (!r?.valid) return setState('invalid');
      setF((x) => ({ ...x, first_name: r.first_name ?? '', last_name: r.last_name ?? '' })); setState('ok');
    }).catch(() => setState('invalid'));
  }, [token]);

  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  async function submit(e) {
    e.preventDefault(); setErr('');
    if (f.password.length < 10 || !/[A-Za-z]/.test(f.password) || !/\d/.test(f.password)) return setErr('Mot de passe : 10 caractères minimum, avec au moins une lettre et un chiffre.');
    if (f.password !== f.password2) return setErr('Les deux mots de passe sont différents.');
    setBusy(true);
    try {
      await call({ token, first_name: f.first_name, last_name: f.last_name, phone: f.phone, email: f.email, password: f.password });
      const { error } = await supabase.auth.signInWithPassword({ email: f.email.trim().toLowerCase(), password: f.password });
      if (error) throw error;
      window.location.replace('/');
    } catch (x) { setErr(x.message); setBusy(false); }
  }

  if (state === 'check') return <div className="login muted">Vérification du lien…</div>;
  if (state === 'invalid') return (
    <div className="login"><div className="card"><img src="/logo.png" alt="ZT Continental Trans" />
      <h1>Lien non valide</h1><p className="muted">Ce lien d'invitation a expiré ou a déjà été utilisé. Demandez-en un nouveau à l'administrateur.</p>
      <a className="btn block" href="/">Aller à la connexion</a></div></div>
  );
  return (
    <div className="login"><form className="card" onSubmit={submit}>
      <img src="/logo.png" alt="ZT Continental Trans" />
      <h1>Créer mon compte</h1>
      <p className="muted">Bienvenue chez ZT Continental Trans. Remplissez ce formulaire pour accéder à votre espace.</p>
      <Msg>{err}</Msg>
      <Field label="Prénom"><input required value={f.first_name} onChange={set('first_name')} autoComplete="given-name" /></Field>
      <Field label="Nom"><input required value={f.last_name} onChange={set('last_name')} autoComplete="family-name" /></Field>
      <Field label="Téléphone"><input type="tel" value={f.phone} onChange={set('phone')} autoComplete="tel" /></Field>
      <Field label="E-mail" hint="Vous recevrez vos plannings à cette adresse."><input type="email" required value={f.email} onChange={set('email')} autoComplete="email" /></Field>
      <Field label="Mot de passe" hint="10 caractères minimum, avec une lettre et un chiffre."><input type="password" required value={f.password} onChange={set('password')} autoComplete="new-password" /></Field>
      <Field label="Confirmer le mot de passe"><input type="password" required value={f.password2} onChange={set('password2')} autoComplete="new-password" /></Field>
      <button className="btn block" disabled={busy}>{busy ? 'Création…' : 'Créer mon compte'}</button>
    </form></div>
  );
}
