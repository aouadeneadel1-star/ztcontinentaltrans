import { COVERAGE } from "../lib/constants";

interface CoverageProps {
  t: Record<string, string>;
  lang: string;
}

export default function Coverage({ lang }: CoverageProps) {
  const data = COVERAGE[lang as keyof typeof COVERAGE] || COVERAGE.fr;

  return (
    <section id="coverage" className="section-padding">
      <div className="container-wide">
        <div className="text-center">
          <p className="section-label">{data.title}</p>
          <h2 className="section-title">{data.title}</h2>
          <p className="section-subtitle mx-auto">{data.sub}</p>
        </div>

        <div className="mt-10 grid gap-8 lg:grid-cols-2 items-start">
          <div className="space-y-4">
            <p className="text-sm text-text-secondary leading-relaxed">{data.base}</p>
            <div className="grid grid-cols-2 gap-3">
              {data.countries.map((c) => (
                <div key={c.name} className="flex items-center gap-3 bg-white rounded-lg border border-border p-3">
                  <span className="text-lg">{c.flag}</span>
                  <div>
                    <p className="text-sm font-medium text-text">{c.name}</p>
                    <p className="text-xs text-text-secondary">{c.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-xl overflow-hidden border border-border h-80 lg:h-96">
            <iframe
              title="Carte des zones d'intervention"
              src="https://maps.google.com/maps?q=48.757%2C2.391&z=6&output=embed"
              width="100%"
              height="100%"
              style={{ border: 0 }}
              loading="lazy"
              referrerPolicy="no-referrer"
              sandbox="allow-scripts allow-same-origin"
            />
          </div>
        </div>
      </div>
    </section>
  );
}
