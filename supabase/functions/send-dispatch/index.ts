// Validation d'un planning de tournées + envoi WhatsApp (API WhatsApp Business Cloud de Meta).
import { createClient } from "jsr:@supabase/supabase-js@2";

const cors = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};
const json = (b: unknown, status = 200) =>
  new Response(JSON.stringify(b), { status, headers: { ...cors, "Content-Type": "application/json" } });

const DEFAULT_COUNTRY = Deno.env.get("WHATSAPP_DEFAULT_COUNTRY") ?? "33";
function normalizePhone(raw: string | null): string | null {
  const s = (raw ?? "").trim();
  if (!s) return null;
  const plus = s.startsWith("+");
  let d = s.replace(/\D+/g, "");
  if (!plus && d.startsWith("00")) d = d.slice(2);
  else if (!plus && d.startsWith("0")) d = DEFAULT_COUNTRY + d.slice(1);
  return /^[1-9][0-9]{8,14}$/.test(d) ? d : null;
}
function longDate(iso: string): string {
  return new Intl.DateTimeFormat("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: "UTC" })
    .format(new Date(iso + "T12:00:00Z"));
}
const oneLine = (s: string) => s.replace(/\s+/g, " ").trim();

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
    .select("*, trucks(plate,brand,model), driver:profiles!dispatches_driver_id_fkey(first_name,last_name,phone), h1:helpers!dispatches_helper1_id_fkey(first_name,last_name,whatsapp), h2:helpers!dispatches_helper2_id_fkey(first_name,last_name,whatsapp)")
    .eq("id", dispatch_id).maybeSingle();
  if (!d) return json({ error: "Planning introuvable" }, 404);

  const token = Deno.env.get("WHATSAPP_TOKEN") ?? "";
  const phoneId = Deno.env.get("WHATSAPP_PHONE_NUMBER_ID") ?? "";
  const auto = !!(token && phoneId);

  // 1) Première validation : on prépare les messages.
  if (d.status === "draft") {
    const { data: tours } = await admin.from("dispatch_tours").select("*").eq("dispatch_id", d.id).order("position");
    if (!tours?.length) return json({ error: "Aucune mission" }, 400);
    const date = longDate(d.work_date);
    const truck = `${d.trucks.plate} (${`${d.trucks.brand} ${d.trucks.model}`.trim()})`;
    const missions: string[] = [], lines: string[] = [], links: string[] = [];
    tours.forEach((t, i) => {
      const label = `${t.kind === "run" ? "Course" : "Tournée"} ${i + 1} : ${t.client} – ${t.city}`;
      missions.push(label); lines.push("• " + label);
      if (t.file_path) links.push(`${i + 1} (${t.client}) : ${url}/functions/v1/sheet?t=${t.token}`);
    });
    const drvName = `${d.driver.first_name} ${d.driver.last_name}`.trim();
    const helpers = [d.h1, d.h2].filter(Boolean).map((h: any) => ({
      name: `${h.first_name} ${h.last_name}`.trim(), first: h.first_name, phone: normalizePhone(h.whatsapp) }));
    const people = [{ type: "driver", name: drvName, first: d.driver.first_name, phone: normalizePhone(d.driver.phone) },
      ...helpers.map((h) => ({ type: "helper", ...h }))];
    const rows = [];
    for (const p of people) {
      if (!p.phone) return json({ error: `Numéro WhatsApp manquant ou invalide pour ${p.name}` }, 400);
      const team = p.type === "driver"
        ? (helpers.length ? `rippeur${helpers.length > 1 ? "s" : ""} ${helpers.map((h) => h.name).join(" et ")}` : "aucun rippeur")
        : `conducteur ${drvName}` + (helpers.filter((h) => h.name !== p.name).length ? `, avec ${helpers.filter((h) => h.name !== p.name).map((h) => h.name).join(" et ")}` : "");
      let body = `Bonjour ${p.first},\nVotre planning du ${date} :\n\nCamion : ${truck}\nÉquipe : ${team}\n\nMissions :\n${lines.join("\n")}`;
      if (links.length) body += `\n\nTélécharger la feuille :\n${links.map((l) => "• " + l).join("\n")}`;
      if (d.note) body += `\n\nRemarque : ${oneLine(d.note)}`;
      body += "\n\nZT Continental Trans";
      const detail = (links.length ? "Télécharger " + links.join(" ; ") : "aucune feuille jointe") + (d.note ? ` — Remarque : ${oneLine(d.note)}` : "");
      rows.push({ dispatch_id: d.id, recipient_type: p.type, recipient_name: p.name, phone: p.phone, body,
        params: [p.first, date, truck, team, missions.join(" | "), detail] });
    }
    await admin.from("dispatch_messages").delete().eq("dispatch_id", d.id);
    await admin.from("dispatch_messages").insert(rows);
    await admin.from("dispatches").update({ status: "validated", validated_at: new Date().toISOString() }).eq("id", d.id);
  }

  // 2) Envoi automatique des messages non envoyés.
  const { data: msgs } = await admin.from("dispatch_messages").select("*").eq("dispatch_id", d.id).order("id");
  if (auto) {
    const lang = Deno.env.get("WHATSAPP_TEMPLATE_LANG") ?? "fr";
    const tpl = Deno.env.get("WHATSAPP_TEMPLATE_NAME") ?? "planning_tournee";
    const textMode = Deno.env.get("WHATSAPP_MODE") === "text";
    for (const m of msgs ?? []) {
      if (m.status === "sent") continue;
      const payload = textMode
        ? { messaging_product: "whatsapp", to: m.phone, type: "text", text: { preview_url: true, body: m.body } }
        : { messaging_product: "whatsapp", to: m.phone, type: "template", template: { name: tpl, language: { code: lang },
            components: [{ type: "body", parameters: (m.params as string[]).map((t) => ({ type: "text", text: String(t) })) }] } };
      let ok = false, pid: string | null = null, err: string | null = null;
      try {
        const r = await fetch(`https://graph.facebook.com/v21.0/${phoneId}/messages`, {
          method: "POST", headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
          body: JSON.stringify(payload), signal: AbortSignal.timeout(15000) });
        const j = await r.json().catch(() => ({}));
        pid = j?.messages?.[0]?.id ?? null;
        ok = r.ok && !!pid;
        if (!ok) err = String(j?.error?.message ?? `Réponse inattendue (HTTP ${r.status})`).slice(0, 450);
      } catch (e) { err = `Connexion impossible : ${String((e as Error).message).slice(0, 200)}`; }
      await admin.from("dispatch_messages").update({
        status: ok ? "sent" : "failed", provider_id: pid, error_message: err,
        attempts: (m.attempts ?? 0) + 1, sent_at: ok ? new Date().toISOString() : null }).eq("id", m.id);
    }
  }
  const { data: after } = await admin.from("dispatch_messages").select("status").eq("dispatch_id", d.id);
  const n = after?.length ?? 0, s = after?.filter((x) => x.status === "sent").length ?? 0;
  const failed = after?.some((x) => x.status === "failed");
  const status = n && s === n ? "sent" : s > 0 ? "partial" : failed ? "failed" : "validated";
  await admin.from("dispatches").update({ status }).eq("id", d.id);
  return json({ ok: true, auto, status });
});
