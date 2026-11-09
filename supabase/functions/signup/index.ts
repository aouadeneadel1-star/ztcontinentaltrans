// Création de compte par lien d'invitation (usage unique, 7 jours). Ouvert sans connexion : le jeton fait foi.
import { createClient } from "jsr:@supabase/supabase-js@2";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};
const json = (b: unknown, status = 200) =>
  new Response(JSON.stringify(b), { status, headers: { ...cors, "Content-Type": "application/json" } });
const str = (v: unknown, n: number) => String(v ?? "").trim().slice(0, n);

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });
  const admin = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!);
  const b = await req.json().catch(() => ({}));
  const token = str(b.token, 40);
  if (!/^[0-9a-f-]{36}$/i.test(token)) return json({ valid: false, error: "Lien invalide." }, 400);

  const { data: inv } = await admin.from("invitations").select("*").eq("token", token).maybeSingle();
  const valid = !!inv && !inv.used_at && new Date(inv.expires_at) > new Date();
  if (b.action === "check") {
    return json({ valid, role: inv?.role, first_name: inv?.first_name ?? "", last_name: inv?.last_name ?? "" });
  }
  if (!valid || !inv) return json({ error: "Ce lien d'invitation n'est plus valide. Demandez-en un nouveau à l'administrateur." }, 400);

  const email = str(b.email, 200).toLowerCase();
  const password = String(b.password ?? "");
  const first = str(b.first_name, 80), last = str(b.last_name, 80);
  if (!first || !last) return json({ error: "Le nom et le prénom sont obligatoires." }, 400);
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return json({ error: "E-mail invalide." }, 400);
  if (password.length < 10 || !/[A-Za-z]/.test(password) || !/\d/.test(password))
    return json({ error: "Mot de passe : 10 caractères minimum, avec au moins une lettre et un chiffre." }, 400);

  // On "réserve" l'invitation avant de créer le compte (évite une double utilisation).
  const { data: claimed } = await admin.from("invitations").update({ used_at: new Date().toISOString() })
    .eq("id", inv.id).is("used_at", null).select("id");
  if (!claimed?.length) return json({ error: "Ce lien d'invitation a déjà été utilisé." }, 400);

  const { data, error } = await admin.auth.admin.createUser({
    email, password, email_confirm: true, user_metadata: { first_name: first, last_name: last },
  });
  if (error || !data.user) {
    await admin.from("invitations").update({ used_at: null }).eq("id", inv.id);
    const dup = /already|registered|exists/i.test(error?.message ?? "");
    return json({ error: dup ? "Un compte existe déjà avec cet e-mail." : (error?.message ?? "Création impossible.") }, 400);
  }
  await admin.from("profiles").update({
    role: inv.role, is_active: true, must_change_password: false,
    first_name: first, last_name: last, email, phone: str(b.phone, 30) || null,
  }).eq("id", data.user.id);
  await admin.from("invitations").update({ used_by: data.user.id }).eq("id", inv.id);
  return json({ ok: true });
});
