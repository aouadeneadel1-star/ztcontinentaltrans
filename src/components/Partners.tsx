import { PARTNERS } from "../lib/constants";

interface PartnerLogo {
  name: string;
  webp?: string;
  fallback?: string;
}

interface PartnersProps {
  lang: string;
}

export default function Partners({ lang }: PartnersProps) {
  const data = PARTNERS[lang as keyof typeof PARTNERS] || PARTNERS.fr;

  return (
    <div className="section-padding border-y border-border bg-surface-alt overflow-hidden">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8 text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-brand-700">
            {data.title}
          </p>
          <p className="mt-3 text-sm text-text-secondary max-w-2xl mx-auto">
            {data.sub}
          </p>
        </div>

        {/* Desktop: Grid with hover animation */}
        <div className="hidden lg:flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
          {data.logos.map((logo: PartnerLogo, index: number) => (
            <div
              key={`${logo.name}-${index}`}
              className="animate-[logoFloat_3s_ease-in-out_infinite]"
              style={{ animationDelay: `${index * 0.15}s` }}
            >
              {logo.webp ? (
                <picture>
                  <source srcSet={logo.webp} type="image/webp" />
                  <img
                    src={logo.fallback}
                    alt={logo.name}
                    width="120"
                    height="60"
                    className="h-12 w-auto object-contain opacity-60 grayscale transition-all duration-300 hover:opacity-100 hover:grayscale-0"
                    loading="lazy"
                  />
                </picture>
              ) : (
                <span className="text-sm font-medium text-text-muted">
                  {logo.name}
                </span>
              )}
            </div>
          ))}
        </div>

        {/* Mobile/Tablet: Infinite scroll marquee */}
        <div className="lg:hidden overflow-hidden">
          <div className="flex gap-10 animate-[scrollLogos_25s_linear_infinite] hover:[animation-play-state:paused]" style={{ width: "max-content" }}>
            {[...data.logos, ...data.logos, ...data.logos].map((logo: PartnerLogo, i: number) => (
              <div key={`${logo.name}-${i}`} className="shrink-0">
                {logo.webp ? (
                  <picture>
                    <source srcSet={logo.webp} type="image/webp" />
                    <img
                      src={logo.fallback}
                      alt={logo.name}
                      width="100"
                      height="50"
                      className="h-10 w-auto object-contain opacity-40"
                      loading="lazy"
                    />
                  </picture>
                ) : (
                  <span className="text-xs font-medium text-text-muted">
                    {logo.name}
                  </span>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
