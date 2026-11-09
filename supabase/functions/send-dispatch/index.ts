// Validation d'un planning de tournées + envoi par e-mail (SMTP) au conducteur et aux rippeurs.
import { createClient } from "jsr:@supabase/supabase-js@2";
import { SMTPClient } from "https://deno.land/x/denomailer@1.6.0/mod.ts";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};
const json = (b: unknown, status = 200) =>
  new Response(JSON.stringify(b), { status, headers: { ...cors, "Content-Type": "application/json" } });

const longDate = (iso: string) =>
  new Intl.DateTimeFormat("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: "UTC" })
    .format(new Date(iso + "T12:00:00Z"));
const oneLine = (s: string) => s.replace(/\s+/g, " ").trim();
const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]!));
const validEmail = (e: string | null) => !!e && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e);

function htmlMail(p: { first: string; date: string; truck: string; team: string; missions: { label: string; link: string | null }[]; note: string | null }) {
  const items = p.missions.map((m) => `<tr><td style="padding:10px 0;border-bottom:1px solid #e4e7ee">${esc(m.label)}</td>
    <td style="padding:10px 0;border-bottom:1px solid #e4e7ee;text-align:right">${m.link
      ? `<a href="${esc(m.link)}" style="background:#0B2A5E;color:#fff;text-decoration:none;padding:9px 14px;border-radius:8px;font-weight:600;display:inline-block">Télécharger</a>`
      : '<span style="color:#888">Sans feuille</span>'}</td></tr>`).join("");
  return `<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#1b2333">
  <div style="background:#0B2A5E;color:#fff;padding:16px 20px;border-radius:10px 10px 0 0;font-weight:700">ZT Continental Trans</div>
  <div style="border:1px solid #e4e7ee;border-top:0;padding:20px;border-radius:0 0 10px 10px">
    <p>Bonjour ${esc(p.first)},</p><p>Votre planning du <b>${esc(p.date)}</b> :</p>
    <p><b>Camion :</b> ${esc(p.truck)}<br><b>Équipe :</b> ${esc(p.team)}</p>
    <table style="width:100%;border-collapse:collapse">${items}</table>
    ${p.note ? `<p style="margin-top:16px"><b>Remarque :</b> ${esc(p.note)}</p>` : ""}
    <p style="color:#6b7385;font-size:13px;margin-top:20px">Bonne journée,<br>ZT Continental Trans</p></div></div>`;
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: cors });
  const url = Deno.env.get("SUPABASE_URL")!;
  const admin = createClient(url, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!);
  const jwt = (req.headers.get("Authorization") ?? "").replace("Bearer ", "");
  const { data: u } = await admin.auth.getUser(jwt);
  if (!u?.user) return json({ error: "Non connecté" }, 401);
  const { data: me } = await admin.from("profiles").select("role,is_active").eq("id", u.user.id).single();
  if (!me || !me.is_active || !["admin", "manager"].includes(me.role)) return json({ error: "Accès refusé" }, 403);

  const { dispatch_id } = await req.json().catch(() => ({}));
  const { data: d } = await admin.from("dispatches")
    .select("*, trucks(plate,brand,model), driver:profiles!dispatches_driver_id_fkey(first_name,last_name,email), h1:helpers!dispatches_helper1_id_fkey(first_name,last_name,email), h2:helpers!dispatches_helper2_id_fkey(first_name,last_name,email)")
    .eq("id", dispatch_id).maybeSingle();
  if (!d) return json({ error: "Planning introuvable" }, 404);

  // Mot de passe SMTP : secret de la fonction, sinon table privée app_secrets (accessible uniquement par cette fonction).
  let smtpPass = Deno.env.get("SMTP_PASSWORD") ?? "";
  if (!smtpPass) {
    const { data: sec } = await admin.from("app_secrets").select("value").eq("name", "SMTP_PASSWORD").maybeSingle();
    smtpPass = sec?.value ?? "";
  }
  const auto = !!smtpPass;

  // 1) Première validation : préparation des e-mails.
  if (d.status === "draft") {
    const { data: tours } = await admin.from("dispatch_tours").select("*").eq("dispatch_id", d.id).order("position");
    if (!tours?.length) return json({ error: "Aucune mission" }, 400);
    const date = longDate(d.work_date);
    const truck = `${d.trucks.plate} (${`${d.trucks.brand} ${d.trucks.model}`.trim()})`;
    const missions = tours.map((t, i) => ({
      label: `${t.kind === "run" ? "Course" : "Tournée"} ${i + 1} : ${t.client} – ${t.city}`,
      link: t.file_path ? `${url}/functions/v1/sheet?t=${t.token}` : null,
    }));
    const drvName = `${d.driver.first_name} ${d.driver.last_name}`.trim();
    const helpers = [d.h1, d.h2].filter(Boolean).map((h: any) => ({
      name: `${h.first_name} ${h.last_name}`.trim(), first: h.first_name, email: (h.email ?? "").trim() }));
    const people = [{ type: "driver", name: drvName, first: d.driver.first_name, email: (d.driver.email ?? "").trim() },
      ...helpers.map((h) => ({ type: "helper", ...h }))];
    const rows = [];
    for (const p of people) {
      if (!validEmail(p.email)) return json({ error: `E-mail manquant ou invalide pour ${p.name}` }, 400);
      const team = p.type === "driver"
        ? (helpers.length ? `rippeur${helpers.length > 1 ? "s" : ""} ${helpers.map((h) => h.name).join(" et ")}` : "aucun rippeur")
        : `conducteur ${drvName}` + (helpers.filter((h) => h.name !== p.name).length ? `, avec ${helpers.filter((h) => h.name !== p.name).map((h) => h.name).join(" et ")}` : "");
      let body = `Bonjour ${p.first},\nVotre planning du ${date} :\n\nCamion : ${truck}\nÉquipe : ${team}\n\nMissions :\n` +
        missions.map((m) => `• ${m.label}${m.link ? `\n  Télécharger la feuille : ${m.link}` : ""}`).join("\n");
      if (d.note) body += `\n\nRemarque : ${oneLine(d.note)}`;
      body += "\n\nZT Continental Trans";
      rows.push({ dispatch_id: d.id, recipient_type: p.type, recipient_name: p.name, email: p.email,
        subject: `Planning du ${date} – ${d.trucks.plate}`, body,
        params: { first: p.first, date, truck, team, missions, note: d.note ? oneLine(d.note) : null } });
    }
    await admin.from("dispatch_messages").delete().eq("dispatch_id", d.id);
    await admin.from("dispatch_messages").insert(rows);
    await admin.from("dispatches").update({ status: "validated", validated_at: new Date().toISOString() }).eq("id", d.id);
  }

  // 2) Envoi des e-mails non envoyés.
  const { data: msgs } = await admin.from("dispatch_messages").select("*").eq("dispatch_id", d.id).order("id");
  if (auto) {
    const port = Number(Deno.env.get("SMTP_PORT") ?? "465");
    const user = Deno.env.get("SMTP_USER") ?? "admin@ztcontinentaltrans.com";
    const from = Deno.env.get("SMTP_FROM") ?? `ZT Continental Trans <${user}>`;
    for (const m of msgs ?? []) {
      if (m.status === "sent") continue;
      let err: string | null = null;
      try {
        const client = new SMTPClient({ connection: { hostname: Deno.env.get("SMTP_HOST") ?? "carambole.o2switch.net", port, tls: port === 465, auth: { username: user, password: smtpPass } } });
        await client.send({ from, to: m.email, subject: m.subject ?? "Planning", content: m.body, html: htmlMail(m.params) });
        await client.close();
      } catch (e) { err = `Envoi impossible : ${String((e as Error).message).slice(0, 300)}`; }
      await admin.from("dispatch_messages").update({
        status: err ? "failed" : "sent", error_message: err,
        attempts: (m.attempts ?? 0) + 1, sent_at: err ? null : new Date().toISOString() }).eq("id", m.id);
    }
  }
  const { data: after } = await admin.from("dispatch_messages").select("status").eq("dispatch_id", d.id);
  const n = after?.length ?? 0, s = after?.filter((x) => x.status === "sent").length ?? 0;
  const failed = after?.some((x) => x.status === "failed");
  const status = n && s === n ? "sent" : s > 0 ? "partial" : failed ? "failed" : "validated";
  await admin.from("dispatches").update({ status }).eq("id", d.id);
  return json({ ok: true, auto, status });
});
