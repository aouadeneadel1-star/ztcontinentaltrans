import { MapPin } from "lucide-react";

interface AboutTrustProps {
  t: Record<string, string>;
}

const TRUST_ITEMS = ["Years", "Available", "Quote", "Response", "Satisfaction"];

export default function AboutTrust({ t }: AboutTrustProps) {
  return (
    <section id="about" className="section-padding bg-surface-alt">
      <div className="container-wide">
        <div className="text-center">
          <h2 className="section-title">{t.aboutTitle}</h2>
          <p className="section-subtitle mx-auto">{t.aboutSub}</p>
        </div>

        <div className="mt-10 flex flex-wrap justify-center gap-x-12 gap-y-6 text-center">
          {TRUST_ITEMS.map((item) => {
            const valKey = `trust${item}Val`;
            const labelKey = `trust${item}`;
            return (
              <div key={item}>
                <p className="text-4xl font-bold text-text">{t[valKey]}</p>
                <p className="mt-1 text-sm text-text-secondary">{t[labelKey]}</p>
              </div>
            );
          })}
        </div>

        <div className="mt-12 grid gap-6 lg:grid-cols-2 items-center">
          <div className="bg-white rounded-xl border border-border p-6 flex items-start gap-4">
            <MapPin size={20} className="text-brand-700 mt-0.5 shrink-0" />
            <div>
              <p className="font-medium text-text">Basé à Boissy-Saint-Léger (94)</p>
              <p className="text-sm text-text-secondary mt-1">1 Place de la Boulaie, 94470</p>
              <p className="text-sm text-text-secondary mt-1">
                Fondée en 2020 par Zoheir BELHACHEMI, ZT Continental Trans couvre la France et l&apos;Europe.
              </p>
            </div>
          </div>
          <picture>
            <source srcSet="/images/logistics-port.webp" type="image/webp" />
            <img
              src="/images/logistics-port.jpg"
              alt="Portail logistique avec conteneurs et camions"
              className="rounded-xl w-full h-48 object-cover"
              loading="lazy"
            />
          </picture>
        </div>
      </div>
    </section>
  );
}
