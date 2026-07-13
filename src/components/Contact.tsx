import { useState, type FormEvent } from "react";
import { Send, Check, MapPin, Phone, Mail } from "lucide-react";
import { SITE } from "../lib/constants";

interface ContactProps {
  t: Record<string, string>;
}

export default function Contact({ t }: ContactProps) {
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    setError("");
    const form = e.currentTarget;
    const data = {
      name: (form.elements.namedItem("name") as HTMLInputElement).value,
      email: (form.elements.namedItem("email") as HTMLInputElement).value,
      phone: (form.elements.namedItem("phone") as HTMLInputElement).value,
      message: (form.elements.namedItem("message") as HTMLTextAreaElement).value,
    };
    try {
      const res = await fetch("/api/contact", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      if (res.ok) { setSent(true); return; }
      const err = await res.json().catch(() => ({}));
      setError(err.error || "Erreur lors de l'envoi.");
    } catch {
      setError("Erreur réseau. Vérifiez votre connexion.");
    } finally {
      setLoading(false);
    }
  };

  if (sent) {
    return (
      <section id="contact" className="section-padding bg-surface-alt">
        <div className="container-wide">
          <div className="max-w-lg mx-auto text-center">
            <div className="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 mx-auto">
              <Check size={24} />
            </div>
            <p className="mt-4 text-lg font-medium text-text">{t.contactSent}</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section id="contact" className="section-padding bg-surface-alt">
      <div className="container-wide">
        <div className="text-center">
          <h2 className="section-title">{t.contactTitle}</h2>
          <p className="section-subtitle mx-auto">{t.contactSub}</p>
        </div>

        <div className="mt-10 grid gap-8 lg:grid-cols-2 max-w-4xl mx-auto">
          <div className="space-y-6">
            <div className="flex items-start gap-3">
              <MapPin size={18} className="text-brand-700 mt-0.5 shrink-0" />
              <div>
                <p className="font-medium text-text">Adresse</p>
                <p className="text-sm text-text-secondary">{SITE.address}</p>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <Phone size={18} className="text-brand-700 mt-0.5 shrink-0" />
              <div>
                <p className="font-medium text-text">Téléphone</p>
                <a href={SITE.phoneLink} className="text-sm text-brand-700 hover:underline">{SITE.phone}</a>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <Mail size={18} className="text-brand-700 mt-0.5 shrink-0" />
              <div>
                <p className="font-medium text-text">Email</p>
                <a href={`mailto:${SITE.email}`} className="text-sm text-brand-700 hover:underline">{SITE.email}</a>
              </div>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label htmlFor="name" className="sr-only">{t.contactName}</label>
              <input
                id="name"
                type="text"
                placeholder={t.contactName}
                required
                className="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
              />
            </div>
            <div>
              <label htmlFor="email" className="sr-only">{t.contactEmail}</label>
              <input
                id="email"
                type="email"
                placeholder={t.contactEmail}
                required
                className="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
              />
            </div>
            <div>
              <label htmlFor="phone" className="sr-only">{t.contactPhone}</label>
              <input
                id="phone"
                type="tel"
                placeholder={t.contactPhone}
                className="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
              />
            </div>
            <div>
              <label htmlFor="message" className="sr-only">{t.contactMessage}</label>
              <textarea
                id="message"
                rows={4}
                placeholder={t.contactMessage}
                required
                className="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none"
              />
            </div>
            {error && <p className="text-sm text-red-600">{error}</p>}
            <button type="submit" disabled={loading} className="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-all duration-200 bg-brand-700 text-white hover:bg-brand-800 active:bg-brand-900 disabled:opacity-50 w-full">
              <Send size={16} />
              {loading ? "..." : t.contactSend}
            </button>
          </form>
        </div>
      </div>
    </section>
  );
}
