import { useState } from 'react';
import { Routes, Route, Navigate, NavLink, useLocation } from 'react-router-dom';
import { useAuth } from './auth';
import { supabase } from './lib/supabase';
import { Msg, Field } from './components/ui';
import Dashboard from './pages/Dashboard';
import Trucks from './pages/Trucks';
import TruckDetail from './pages/TruckDetail';
import Checks from './pages/Checks';
import Incidents, { IncidentDetail } from './pages/Incidents';
import History from './pages/History';
import Users from './pages/Users';
import Helpers from './pages/Helpers';
import Dispatches, { DispatchForm, DispatchShow } from './pages/Dispatches';
import DriverHome, { CheckForm, IncidentForm } from './pages/Driver';
import Signup from './pages/Signup';
import ResetPassword from './pages/ResetPassword';

function Login() {
  const [email, setEmail] = useState(''); const [password, setPassword] = useState('');
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const [forgot, setForgot] = useState(false); const [sent, setSent] = useState(false);
  async function sendReset(e) {
    e.preventDefault(); setBusy(true); setErr('');
    await supabase.functions.invoke('reset-password', { body: { email: email.trim() } });
    setSent(true); setBusy(false);
  }
  async function submit(e) {
    e.preventDefault(); setBusy(true); setErr('');
    const { error } = await supabase.auth.signInWithPassword({ email: email.trim(), password });
    if (error) setErr('E-mail ou mot de passe incorrect.');
    setBusy(false);
  }
  if (forgot) return (
    <div className="login"><form className="card" onSubmit={sendReset}>
      <img src="/logo.png" alt="ZT Continental Trans" />
      <h1>Mot de passe oublié</h1>
      {sent ? <Msg type="ok">Si cette adresse correspond à un compte, un e-mail avec un lien de réinitialisation vient d'être envoyé (pensez à vérifier les courriers indésirables).</Msg>
        : <><p className="muted">Saisissez votre e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
          <Field label="E-mail"><input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
          <button className="btn block" disabled={busy}>{busy ? 'Envoi…' : 'Envoyer le lien'}</button></>}
      <p style={{ textAlign: 'center' }}><button type="button" className="btn light sm" onClick={() => { setForgot(false); setSent(false); }}>Retour à la connexion</button></p>
    </form></div>
  );
  return (
    <div className="login"><form className="card" onSubmit={submit}>
      <img src="/logo.png" alt="ZT Continental Trans" />
      <h1>Gestion de flotte</h1>
      <Msg>{err}</Msg>
      <Field label="E-mail"><input type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
      <Field label="Mot de passe"><input type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} /></Field>
      <button className="btn block" disabled={busy}>{busy ? 'Connexion…' : 'Se connecter'}</button>
      <p style={{ textAlign: 'center' }}><button type="button" className="btn light sm" onClick={() => setForgot(true)}>Mot de passe oublié ?</button></p>
    </form></div>
  );
}

function ChangePassword() {
  const { refresh, signOut } = useAuth();
  const [p1, setP1] = useState(''); const [p2, setP2] = useState(''); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  async function submit(e) {
    e.preventDefault(); setErr('');
    if (p1.length < 10 || !/[A-Za-z]/.test(p1) || !/\d/.test(p1)) return setErr('10 caractères minimum, avec au moins une lettre et un chiffre.');
    if (p1 !== p2) return setErr('Les deux mots de passe sont différents.');
    setBusy(true);
    const { error } = await supabase.auth.updateUser({ password: p1 });
    if (error) { setErr(error.message); setBusy(false); return; }
    await supabase.rpc('complete_password_change');
    await refresh(); setBusy(false);
  }
  return (
    <div className="login"><form className="card" onSubmit={submit}>
      <h1>Choisissez votre mot de passe</h1>
      <p className="muted">Pour votre sécurité, remplacez le mot de passe provisoire avant de continuer.</p>
      <Msg>{err}</Msg>
      <Field label="Nouveau mot de passe"><input type="password" autoComplete="new-password" value={p1} onChange={(e) => setP1(e.target.value)} /></Field>
      <Field label="Confirmer"><input type="password" autoComplete="new-password" value={p2} onChange={(e) => setP2(e.target.value)} /></Field>
      <button className="btn block" disabled={busy}>Enregistrer</button>
      <p style={{ textAlign: 'center' }}><button type="button" className="btn light sm" onClick={signOut}>Se déconnecter</button></p>
    </form></div>
  );
}

const STAFF_NAV = [
  ['Pilotage', [['/', 'Tableau de bord', true, 'home'], ['/tournees', 'Tournées', false, 'route'], ['/rippeurs', 'Rippeurs', false, 'users']]],
  ['Flotte', [['/camions', 'Camions', false, 'truck'], ['/controles', 'Photos et contrôles', false, 'camera'], ['/signalements', 'Signalements', false, 'alert'], ['/historique', 'Historique et exports', false, 'history']]],
];
const DRIVER_NAV = [['/', 'Accueil', true, 'home'], ['/signaler', 'Signaler un problème', false, 'alert']];

// Icônes de la barre du bas (SVG simples, couleur = texte)
const ICONS = {
  home: <><path d="M3 11.5 12 4l9 7.5" /><path d="M5.5 10v9.5h13V10" /><path d="M10 19.5v-5h4v5" /></>,
  route: <><circle cx="6" cy="19" r="2.5" /><circle cx="18" cy="5" r="2.5" /><path d="M8.5 19H16a3.5 3.5 0 0 0 0-7H8a3.5 3.5 0 0 1 0-7h7.5" /></>,
  truck: <><path d="M2 6h11v10H2z" /><path d="M13 9h4.5l3 3.5V16H13z" /><circle cx="7" cy="17.5" r="2" /><circle cx="17" cy="17.5" r="2" /></>,
  users: <><circle cx="9" cy="8" r="3.2" /><path d="M3 20c0-3.4 2.7-5.5 6-5.5s6 2.1 6 5.5" /><path d="M16 5a3 3 0 0 1 0 6" /><path d="M18 14.8c1.8.7 3 2.4 3 5.2" /></>,
  camera: <><path d="M4 8h3l1.5-2.5h7L17 8h3v11H4z" /><circle cx="12" cy="13.5" r="3.3" /></>,
  history: <><path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1" /><path d="M3.5 4v4.5H8" /><path d="M12 8v4.5l3 1.8" /></>,
  alert: <><path d="M12 3.5 22 20H2z" /><path d="M12 10v4.5" /><path d="M12 17.3v.2" /></>,
};
export const Icon = ({ name, size = 24 }) => <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{ICONS[name]}</svg>;

function Shell({ children }) {
  const { profile, isStaff, isAdmin, signOut } = useAuth();
  const [open, setOpen] = useState(false);
  const loc = useLocation();
  const groups = isStaff ? [...STAFF_NAV, ...(isAdmin ? [['Administration', [['/utilisateurs', 'Utilisateurs']]]] : [])] : [['', DRIVER_NAV]];
  const bottom = isStaff
    ? [['/', 'Accueil', true, 'home'], ['/tournees', 'Tournées', false, 'route'], ['/camions', 'Camions', false, 'truck'], ['/signalements', 'Problèmes', false, 'alert']]
    : [['/', 'Accueil', true, 'home'], ['/signaler', 'Signaler', false, 'alert']];
  return (
    <div className="app">
      <div className="topbar"><button aria-label="Menu" onClick={() => setOpen(!open)}>☰</button><b style={{ color: 'var(--navy)' }}>ZT Continental</b><span style={{ width: 30 }} /></div>
      <aside className={`side ${open ? 'open' : ''}`} onClick={() => setOpen(false)}>
        <div className="brand"><img src="/logo.png" alt="" /><b>ZT Continental<br />Trans</b></div>
        {groups.map(([title, items]) => (
          <div key={title}>{title && <h3>{title}</h3>}
            {items.map(([to, label, end, icon]) => <NavLink key={to} to={to} end={!!end} className={({ isActive }) => `nav ${isActive ? 'active' : ''}`}><Icon name={icon} size={20} /><span>{label}</span></NavLink>)}</div>
        ))}
        <div className="me"><b>{profile.first_name} {profile.last_name}</b><br /><span className="muted">{{ admin: 'Administrateur', manager: 'Responsable', driver: 'Conducteur' }[profile.role]}</span><br />
          <button className="btn light sm" style={{ marginTop: 8 }} onClick={signOut}>Se déconnecter</button></div>
      </aside>
      <main className="main" key={loc.pathname}>{children}</main>
      <nav className="bottom">{bottom.map(([to, label, end, icon]) => <NavLink key={to} to={to} end={!!end} className={({ isActive }) => (isActive ? 'active' : '')}><Icon name={icon} /><span>{label}</span></NavLink>)}</nav>
    </div>
  );
}

export default function App() {
  const { session, profile, loading, isStaff, isDriver, signOut } = useAuth();
  const { pathname } = useLocation();
  if (pathname === '/inscription') return <Signup />;
  if (pathname === '/reinitialiser') return <ResetPassword />;
  if (loading) return <div className="login muted">Chargement…</div>;
  if (!session) return <Login />;
  if (!profile) return <div className="login muted">Chargement…</div>;
  if (!profile.is_active) return (
    <div className="login"><div className="card"><h1>Compte non activé</h1><p className="muted">Votre compte n'est pas encore activé. Contactez l'administrateur.</p><button className="btn block" onClick={signOut}>Retour</button></div></div>
  );
  if (profile.must_change_password) return <ChangePassword />;
  return (
    <Shell>
      {isStaff ? (
        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/camions" element={<Trucks />} />
          <Route path="/camions/:id" element={<TruckDetail />} />
          <Route path="/controles" element={<Checks />} />
          <Route path="/signalements" element={<Incidents />} />
          <Route path="/signalements/:id" element={<IncidentDetail />} />
          <Route path="/historique" element={<History />} />
          <Route path="/rippeurs" element={<Helpers />} />
          <Route path="/tournees" element={<Dispatches />} />
          <Route path="/tournees/nouveau" element={<DispatchForm />} />
          <Route path="/tournees/:id/modifier" element={<DispatchForm />} />
          <Route path="/tournees/:id" element={<DispatchShow />} />
          <Route path="/utilisateurs" element={<Users />} />
          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      ) : isDriver ? (
        <Routes>
          <Route path="/" element={<DriverHome />} />
          <Route path="/controle/:type" element={<CheckForm />} />
          <Route path="/signaler" element={<IncidentForm />} />
          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      ) : null}
    </Shell>
  );
}
