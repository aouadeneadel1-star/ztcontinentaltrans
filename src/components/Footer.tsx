import { SITE, NAV_ITEMS_FR, NAV_ITEMS_EN } from "../lib/constants";

interface FooterProps {
  t: Record<string, string>;
  lang: string;
}

export default function Footer({ t, lang }: FooterProps) {
  const navItems = lang === "fr" ? NAV_ITEMS_FR : NAV_ITEMS_EN;
  const scrollTo = (id: string) => {
    document.querySelector(id)?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <footer className="border-t border-border bg-surface">
      <div className="container-wide py-12">
        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <div className="flex items-center gap-2">
              <img
                src="/logo.webp"
                alt={SITE.name}
                width="28"
                height="28"
                className="w-7 h-7"
                loading="lazy"
              />
              <p className="font-bold text-text">{SITE.name}</p>
            </div>
            <p className="mt-2 text-sm text-text-secondary leading-relaxed">{t.footerDesc}</p>
          </div>
          <div>
            <p className="font-semibold text-text text-sm">{t.footerNav}</p>
            <nav className="mt-3 flex flex-col gap-2">
              {navItems.map((item) => (
                <button
                  key={item.href}
                  onClick={() => scrollTo(item.href)}
                  className="text-sm text-text-secondary hover:text-text text-left transition-colors"
                >
                  {item.label}
                </button>
              ))}
            </nav>
          </div>
          <div>
            <p className="font-semibold text-text text-sm">{t.footerContact}</p>
            <div className="mt-3 space-y-1 text-sm text-text-secondary">
              <p>{SITE.address}</p>
              <a href={SITE.phoneLink} className="block hover:text-text transition-colors">{SITE.phone}</a>
              <a href={`mailto:${SITE.email}`} className="block hover:text-text transition-colors">{SITE.email}</a>
            </div>
          </div>
        </div>
        <div className="mt-10 pt-6 border-t border-border text-center text-xs text-text-muted space-y-1">
          <p>{t.footerLegal}</p>
          <p>SIRET {SITE.siret} | TVA {SITE.tva} | RCS {SITE.rcs} | Capital {SITE.capital}</p>
        </div>
      </div>
    </footer>
  );
}
