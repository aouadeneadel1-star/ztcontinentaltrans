import type { ServiceItem } from "../types";

interface ServicesProps {
  t: Record<string, string>;
  services: ServiceItem[];
}

export default function Services({ t, services }: ServicesProps) {
  const scrollToQuote = () => {
    document.querySelector("#quote")?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <section id="services" className="section-padding">
      <div className="container-wide">
        <div className="text-center">
          <p className="section-label">{t.servicesTitle}</p>
          <h2 className="section-title">{t.servicesTitle}</h2>
          <p className="section-subtitle mx-auto">{t.servicesSub}</p>
        </div>

        <div className="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
          {services.map((service, i) => (
            <div key={service.id} className="card flex flex-col">
              <span className="text-xs font-semibold text-text-muted tracking-wider mb-3 inline-block">
                {(i + 1).toString().padStart(2, "0")}
              </span>
              <h3 className="font-semibold text-text">{service.title}</h3>
              <p className="mt-2 text-sm text-text-secondary leading-relaxed flex-1">
                {service.desc}
              </p>
              <button
                onClick={scrollToQuote}
                className="mt-4 text-sm font-medium text-brand-700 hover:text-brand-800 transition-colors"
              >
                Demander un devis
              </button>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
