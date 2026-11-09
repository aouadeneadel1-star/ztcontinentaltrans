// Lien secret de téléchargement d'une feuille de tournée (sans connexion).
import { createClient } from "jsr:@supabase/supabase-js@2";

Deno.serve(async (req) => {
  const t = new URL(req.url).searchParams.get("t") ?? "";
  if (!/^[0-9a-f-]{36}$/i.test(t)) return new Response("Lien invalide", { status: 404 });
  const admin = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!);
  const { data: tour } = await admin
    .from("dispatch_tours")
    .select("file_path, original_name, client, city, dispatches!inner(status)")
    .eq("token", t)
    .neq("dispatches.status", "draft")
    .maybeSingle();
  if (!tour || !tour.file_path) return new Response("Feuille introuvable", { status: 404 });
  const ext = tour.file_path.split(".").pop() ?? "pdf";
  const name = `tournee-${tour.client}-${tour.city}.${ext}`.replace(/[^\w.\-]+/g, "_");
  const { data, error } = await admin.storage.from("sheets").createSignedUrl(tour.file_path, 300, { download: name });
  if (error || !data) return new Response("Fichier indisponible", { status: 404 });
  return Response.redirect(data.signedUrl, 302);
});
