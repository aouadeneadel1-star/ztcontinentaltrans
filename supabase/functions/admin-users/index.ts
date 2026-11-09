// Gestion des comptes (création, modification, mot de passe) — réservé aux administrateurs.
import { createClient } from "jsr:@supabase/supabase-js@2";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};
const json = (b: unknown, status = 200) =>
  new Response(JSON.stringify(b), { status, headers: { ...cors, "Content-Type": "application/json" } });

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });
  const admin = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!);
  const jwt = (req.headers.get("Authorization") ?? "").replace("Bearer ", "");
  const { data: u } = await admin.auth.getUser(jwt);
  if (!u?.user) return json({ error: "Non connecté" }, 401);
  const { data: me } = await admin.from("profiles").select("role,is_active").eq("id", u.user.id).single();
  if (!me || me.role !== "admin" || !me.is_active) return json({ error: "Réservé à l'administrateur" }, 403);

  const b = await req.json().catch(() => ({}));
  const roles = ["admin", "manager", "driver"];
  const str = (v: unknown, n: number) => String(v ?? "").trim().slice(0, n);

  if (b.action === "create") {
    const email = str(b.email, 200).toLowerCase();
    const password = String(b.password ?? "");
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return json({ error: "E-mail invalide" }, 400);
    if (password.length < 10) return json({ error: "Mot de passe : 10 caractères minimum" }, 400);
    if (!roles.includes(b.role)) return json({ error: "Rôle invalide" }, 400);
    const { data, error } = await admin.auth.admin.createUser({
      email, password, email_confirm: true,
      user_metadata: { first_name: str(b.first_name, 80), last_name: str(b.last_name, 80) },
    });
    if (error || !data.user) return json({ error: error?.message ?? "Création impossible" }, 400);
    await admin.from("profiles").update({
      role: b.role, is_active: true, must_change_password: true,
      first_name: str(b.first_name, 80), last_name: str(b.last_name, 80), phone: str(b.phone, 30) || null,
    }).eq("id", data.user.id);
    return json({ ok: true, id: data.user.id });
  }

  if (b.action === "update") {
    const id = str(b.id, 40);
    if (b.role !== undefined && !roles.includes(b.role)) return json({ error: "Rôle invalide" }, 400);
    if (id === u.user.id && (b.is_active === false || (b.role && b.role !== "admin")))
      return json({ error: "Vous ne pouvez pas vous retirer vos propres droits" }, 400);
    const patch: Record<string, unknown> = {};
    if (b.first_name !== undefined) patch.first_name = str(b.first_name, 80);
    if (b.last_name !== undefined) patch.last_name = str(b.last_name, 80);
    if (b.phone !== undefined) patch.phone = str(b.phone, 30) || null;
    if (b.role !== undefined) patch.role = b.role;
    if (b.is_active !== undefined) patch.is_active = !!b.is_active;
    if (b.password) {
      if (String(b.password).length < 10) return json({ error: "Mot de passe : 10 caractères minimum" }, 400);
      const { error } = await admin.auth.admin.updateUserById(id, { password: String(b.password) });
      if (error) return json({ error: error.message }, 400);
      patch.must_change_password = true;
    }
    if (Object.keys(patch).length) {
      const { error } = await admin.from("profiles").update(patch).eq("id", id);
      if (error) return json({ error: error.message }, 400);
    }
    return json({ ok: true });
  }
  return json({ error: "Action inconnue" }, 400);
});
