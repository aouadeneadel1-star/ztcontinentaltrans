// Mot de passe oublié : envoie par e-mail un lien de réinitialisation (valable 1 h). Ouvert sans connexion.
import { createClient } from "jsr:@supabase/supabase-js@2";
import { SMTPClient } from "https://deno.land/x/denomailer@1.6.0/mod.ts";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};
// Même réponse dans tous les cas : on ne révèle pas si une adresse existe.
const done = () => new Response(JSON.stringify({ ok: true }), { headers: { ...cors, "Content-Type": "application/json" } });

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });
  const admin = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!);
  const b = await req.json().catch(() => ({}));
  const email = String(b.email ?? "").trim().toLowerCase().slice(0, 200);
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return done();

  const { data: p } = await admin.from("profiles").select("id,first_name,is_active").eq("email", email).maybeSingle();
  if (!p || !p.is_active) return done();
  const { data: au } = await admin.auth.admin.getUserById(p.id);
  const last = au?.user?.recovery_sent_at ? new Date(au.user.recovery_sent_at).getTime() : 0;
  if (Date.now() - last < 60_000) return done(); // un e-mail par minute au maximum

  const { data: link, error } = await admin.auth.admin.generateLink({ type: "recovery", email });
  const hashed = link?.properties?.hashed_token;
  if (error || !hashed) return done();

  let pass = Deno.env.get("SMTP_PASSWORD") ?? "";
  if (!pass) {
    const { data } = await admin.from("app_secrets").select("value").eq("name", "SMTP_PASSWORD").maybeSingle();
    pass = data?.value ?? "";
  }
  if (!pass) return done();
  const app = (Deno.env.get("APP_URL") ?? "https://zt-flotte.vercel.app").replace(/\/$/, "");
  const url = `${app}/reinitialiser?token_hash=${encodeURIComponent(hashed)}`;
  const user = Deno.env.get("SMTP_USER") ?? "admin@ztcontinentaltrans.com";
  const first = (p.first_name ?? "").replace(/[<>&"]/g, "");
  try {
    const client = new SMTPClient({ connection: { hostname: Deno.env.get("SMTP_HOST") ?? "carambole.o2switch.net", port: 465, tls: true, auth: { username: user, password: pass } } });
    await client.send({
      from: `ZT Continental Trans <${user}>`, to: email, subject: "Réinitialiser votre mot de passe",
      content: `Bonjour ${first},\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :\n${url}\n\nSi vous n'avez rien demandé, ignorez ce message.\n\nZT Continental Trans`,
      html: `<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1b2333"><div style="background:#0B2A5E;color:#fff;padding:16px 20px;border-radius:10px 10px 0 0;font-weight:700">ZT Continental Trans</div><div style="border:1px solid #e4e7ee;border-top:0;padding:20px;border-radius:0 0 10px 10px"><p>Bonjour ${first},</p><p>Pour choisir un nouveau mot de passe, cliquez sur le bouton (valable 1 heure) :</p><p><a href="${url}" style="background:#0B2A5E;color:#fff;text-decoration:none;padding:11px 18px;border-radius:8px;font-weight:600;display:inline-block">Choisir un nouveau mot de passe</a></p><p style="color:#6b7385;font-size:13px">Si vous n'avez rien demandé, ignorez ce message.</p></div></div>`,
    });
    await client.close();
  } catch { /* silencieux */ }
  return done();
});
