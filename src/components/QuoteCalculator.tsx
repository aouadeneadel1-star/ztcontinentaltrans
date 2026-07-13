import { useState, type FormEvent } from "react";
import { ArrowRight } from "lucide-react";
import type { QuoteResult } from "../types";
import { CITIES } from "../lib/constants";

interface QuoteCalculatorProps {
  t: Record<string, string>;
}

const SERVICE_OPTIONS = [
  { value: "road", label: "Transport Routier" },
  { value: "express", label: "Livraison Express" },
  { value: "logistics", label: "Logistique & Stockage" },
  { value: "relocation", label: "Déménagement" },
  { value: "distribution", label: "Distribution Urbaine" },
];

export default function QuoteCalculator({ t }: QuoteCalculatorProps) {
  const [form, setForm] = useState({
    serviceType: "road",
    departureCity: "",
    arrivalCity: "",
    weight: "",
    email: "",
  });
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState("");

  const handleChange = (field: string, value: string) => {
    setForm((f) => ({ ...f, [field]: value }));
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError("");
    setSent(false);
    try {
      const res = await fetch("/api/quote/send", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(form),
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

  const filteredCities = (query: string) =>
    CITIES.filter((c) => c.label.toLowerCase().includes(query.toLowerCase()));

  if (sent) {
    return (
      <section id="quote" className="section-padding bg-surface-alt">
        <div className="container-wide">
          <div className="max-w-lg mx-auto text-center">
            <div className="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600 mx-auto">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <p className="mt-4 text-lg font-medium text-text">{t.quoteEmailSent}</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section id="quote" className="section-padding bg-surface-alt">
      <div className="container-wide">
        <div className="text-center">
          <p className="section-label">{t.quoteTitle}</p>
          <h2 className="section-title">{t.quoteTitle}</h2>
          <p className="section-subtitle mx-auto">{t.quoteSub}</p>
        </div>

        <div className="mt-10 max-w-2xl mx-auto">
          <div className="bg-white rounded-2xl border border-border p-6 sm:p-8 shadow-sm">
            <form onSubmit={handleSubmit} className="space-y-5">
              <div>
                <label htmlFor="serviceType" className="block text-sm font-medium text-text mb-1.5">
                  {t.quoteService}
                </label>
                <select
                  id="serviceType"
                  value={form.serviceType}
                  onChange={(e) => handleChange("serviceType", e.target.value)}
                  className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow"
                >
                  {SERVICE_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                  ))}
                </select>
              </div>

              <div>
                <p className="block text-sm font-medium text-text mb-1.5">Trajet</p>
                <div className="grid grid-cols-[1fr_auto_1fr] gap-2 items-center">
                  <div>
                    <input
                      type="text"
                      list="cities-from"
                      placeholder={t.quoteFrom}
                      value={form.departureCity}
                      onChange={(e) => handleChange("departureCity", e.target.value)}
                      required
                      className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow"
                    />
                    <datalist id="cities-from">
                      {filteredCities(form.departureCity).map((c) => (
                        <option key={c.label} value={c.label} />
                      ))}
                    </datalist>
                  </div>
                  <ArrowRight size={18} className="text-text-muted shrink-0" />
                  <div>
                    <input
                      type="text"
                      list="cities-to"
                      placeholder={t.quoteTo}
                      value={form.arrivalCity}
                      onChange={(e) => handleChange("arrivalCity", e.target.value)}
                      required
                      className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow"
                    />
                    <datalist id="cities-to">
                      {filteredCities(form.arrivalCity).map((c) => (
                        <option key={c.label} value={c.label} />
                      ))}
                    </datalist>
                  </div>
                </div>
              </div>

              <div>
                <label htmlFor="weight" className="block text-sm font-medium text-text mb-1.5">
                  {t.quoteWeight}
                </label>
                <input
                  type="number"
                  step="0.1"
                  min="0.1"
                  placeholder="0"
                  value={form.weight}
                  onChange={(e) => handleChange("weight", e.target.value)}
                  required
                  className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow"
                />
              </div>

              <div>
                <label htmlFor="email" className="block text-sm font-medium text-text mb-1.5">
                  {t.quoteEmail}
                </label>
                <input
                  type="email"
                  placeholder="exemple@email.com"
                  value={form.email}
                  onChange={(e) => handleChange("email", e.target.value)}
                  required
                  className="w-full rounded-xl border border-border bg-surface px-4 py-3 text-sm text-text placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow"
                />
              </div>

              {error && <p className="text-sm text-red-600">{error}</p>}

              <button type="submit" disabled={loading} className="w-full rounded-xl bg-brand-700 text-white px-6 py-3 text-sm font-medium hover:bg-brand-800 active:bg-brand-900 disabled:opacity-50 transition-all">
                {loading ? "..." : t.quoteEmailSend}
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>
  );
}
