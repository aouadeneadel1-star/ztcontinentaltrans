import { supabase } from './supabase';

export const L = {
  roles: { admin: 'Administrateur', manager: 'Responsable de flotte', driver: 'Conducteur' },
  truck_status: { available: 'Disponible', in_service: 'En circulation', maintenance: 'En maintenance', immobilized: 'Immobilisé' },
  ownership: { owned: 'Propriété', rented: 'Location' },
  check_type: { morning: 'Matin', evening: 'Soir' },
  incident_category: { engine: 'Moteur', tires: 'Pneus', brakes: 'Freinage', lights: 'Éclairage', bodywork: 'Carrosserie', electrical: 'Électrique', documents: 'Documents administratifs', other: 'Autre' },
  priority: { low: 'Faible', medium: 'Moyenne', high: 'Élevée', critical: 'Critique' },
  incident_status: { new: 'Nouveau', in_progress: 'En cours', pending: 'En attente', resolved: 'Résolu' },
  doc_category: { registration: 'Carte grise', insurance: 'Assurance', inspection: 'Contrôle technique', transport_auth: 'Autorisation de transport', rental_contract: 'Contrat de location', maintenance: "Document d'entretien", other: 'Autre document' },
  dispatch_status: { draft: 'Brouillon', validated: 'Validé, à envoyer', sent: 'Envoyé', partial: 'Envoi partiel', failed: "Échec d'envoi" },
  tour_kind: { tour: 'Tournée', run: 'Course' },
};
export const TONE = {
  available: 'green', in_service: 'blue', maintenance: 'orange', immobilized: 'red',
  low: 'neutral', medium: 'blue', high: 'orange', critical: 'red',
  new: 'red', in_progress: 'blue', pending: 'orange', resolved: 'green',
  draft: 'neutral', validated: 'orange', sent: 'green', partial: 'orange', failed: 'red',
};

export const today = () => new Date().toISOString().slice(0, 10);
export const addDays = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
export const daysUntil = (iso) => Math.ceil((new Date(iso + 'T12:00:00') - new Date()) / 86400000);
export const fdate = (iso) => (iso ? new Date(iso.length === 10 ? iso + 'T12:00:00' : iso).toLocaleDateString('fr-FR') : '—');
export const fdatetime = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
export const fdateLong = (iso) => new Date(iso + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
export const money = (n) => (n == null || n === '' ? '—' : Number(n).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }));
export const fullName = (p) => (p ? `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim() : '—');

// Numéro WhatsApp : 06 12 34 56 78 -> 33612345678
export function normalizePhone(raw, country = '33') {
  const s = String(raw ?? '').trim();
  if (!s) return null;
  const plus = s.startsWith('+');
  let d = s.replace(/\D+/g, '');
  if (!plus && d.startsWith('00')) d = d.slice(2);
  else if (!plus && d.startsWith('0')) d = country + d.slice(1);
  return /^[1-9][0-9]{8,14}$/.test(d) ? d : null;
}
export function prettyPhone(d) {
  if (!d) return '—';
  if (d.startsWith('33') && d.length === 11) return `+33 ${d[2]} ${d.slice(3).match(/../g).join(' ')}`;
  return '+' + d;
}
export const waLink = (phone, body) => `https://wa.me/${phone}?text=${encodeURIComponent(body)}`;

// Réduit une photo (1920 px max, JPEG) avant l'envoi
export async function compressImage(file, max = 1920, quality = 0.82) {
  if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
  try {
    const bmp = await createImageBitmap(file);
    const k = Math.min(1, max / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', quality));
    return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
  } catch { return file; }
}

const rand = () => crypto.randomUUID().replace(/-/g, '');
export async function uploadFile(bucket, folder, file) {
  const ext = (file.name.split('.').pop() || 'bin').toLowerCase().replace(/[^a-z0-9]/g, '');
  const path = `${folder}/${rand()}.${ext}`;
  const { error } = await supabase.storage.from(bucket).upload(path, file, { contentType: file.type, upsert: false });
  if (error) throw new Error(error.message.includes('mime') ? 'Type de fichier non autorisé' : error.message);
  return path;
}
export async function signedUrl(bucket, path, download) {
  const { data } = await supabase.storage.from(bucket).createSignedUrl(path, 600, download ? { download } : undefined);
  return data?.signedUrl ?? null;
}

export function downloadCSV(name, rows) {
  const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
  const csv = '﻿' + rows.map((r) => r.map(esc).join(';')).join('\r\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  a.download = name; a.click(); URL.revokeObjectURL(a.href);
}
