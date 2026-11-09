import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { supabase } from './lib/supabase';

const Ctx = createContext(null);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }) {
  const [session, setSession] = useState(undefined); // undefined = chargement
  const [profile, setProfile] = useState(null);

  const loadProfile = useCallback(async (s) => {
    if (!s) { setProfile(null); return; }
    const { data } = await supabase.from('profiles').select('*').eq('id', s.user.id).maybeSingle();
    setProfile(data ?? null);
  }, []);

  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => { setSession(data.session); loadProfile(data.session); });
    const { data: sub } = supabase.auth.onAuthStateChange((_e, s) => { setSession(s); setTimeout(() => loadProfile(s), 0); });
    return () => sub.subscription.unsubscribe();
  }, [loadProfile]);

  const value = {
    session, profile, loading: session === undefined,
    isStaff: profile?.is_active && ['admin', 'manager'].includes(profile.role),
    isAdmin: profile?.is_active && profile.role === 'admin',
    isDriver: profile?.is_active && profile.role === 'driver',
    refresh: () => loadProfile(session),
    signOut: () => supabase.auth.signOut(),
  };
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
