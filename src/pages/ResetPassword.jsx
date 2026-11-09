import { useEffect, useState } from 'react';
import { supabase } from '../lib/supabase';
import { Field, Msg } from '../components/ui';

// Page ouverte depuis le lien reçu par e-mail : choisir un nouveau mot de passe.
export default function ResetPassword() {
  const hash = new URLSearchParams(window.location.search).get('token_hash') ?? '';
  const [state, setState] = useState('check'); // check | ok | invalid
  const [p1, setP1] = useState(''); const [p2, setP2] = useState(''); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!hash) return setState('invalid');
    supabase.auth.verifyOtp({ token_hash: hash, type: 'recovery' }).then(({ error }) => setState(error ? 'invalid' : 'ok'));
  }, [hash]);

  async function submit(e) {
    e.preventDefault(); setErr('');
    if (p1.length < 10 || !/[A-Za-z]/.test(p1) || !/\d/.test(p1)) return setErr('10 caractères minimum, avec au moins une lettre et un chiffre.');
    if (p1 !== p2) return setErr('Les deux mots de passe sont différents.');
    setBusy(true);
    const { error } = await supabase.auth.updateUser({ password: p1 });
    if (error) { setErr(error.message); setBusy(false); return; }
    await supabase.rpc('complete_password_change');
    window.location.replace('/');
  }

  if (state === 'check') return <div className="login muted">Vérification du lien…</div>;
  if (state === 'invalid') return (
    <div className="login"><div className="card"><img src="/logo.png" alt="ZT Continental Trans" />
      <h1>Lien expiré</h1><p className="muted">Ce lien n'est plus valable. Demandez-en un nouveau depuis la page de connexion (« Mot de passe oublié »).</p>
      <a className="btn block" href="/">Retour à la connexion</a></div></div>
  );
  return (
    <div className="login"><form className="card" onSubmit={submit}>
      <img src="/logo.png" alt="ZT Continental Trans" />
      <h1>Nouveau mot de passe</h1>
      <Msg>{err}</Msg>
      <Field label="Nouveau mot de passe" hint="10 caractères minimum, avec une lettre et un chiffre."><input type="password" autoComplete="new-password" value={p1} onChange={(e) => setP1(e.target.value)} /></Field>
      <Field label="Confirmer"><input type="password" autoComplete="new-password" value={p2} onChange={(e) => setP2(e.target.value)} /></Field>
      <button className="btn block" disabled={busy}>{busy ? 'Enregistrement…' : 'Enregistrer'}</button>
    </form></div>
  );
}
