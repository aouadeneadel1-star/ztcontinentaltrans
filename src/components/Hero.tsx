import { ArrowRight } from "lucide-react";

interface HeroProps {
  t: Record<string, string>;
  lang: string;
}

export default function Hero({ t }: HeroProps) {
  const scrollTo = (id: string) => {
    document.querySelector(id)?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <section id="hero" className="relative min-h-[85vh] flex items-center overflow-hidden bg-brand-900">
      <picture>
        <source srcSet="/images/hero-custom.webp" type="image/webp" />
        <img
          src="/images/hero-custom.jpg"
          alt="Camion de livraison"
          width="1920"
          height="1080"
          className="absolute inset-0 w-full h-full object-cover opacity-40"
          loading="eager"
          fetchpriority="high"
        />
      </picture>
      <div className="absolute inset-0 bg-gradient-to-r from-brand-900/90 via-brand-900/70 to-transparent" />

      <div className="container-wide py-20 w-full relative z-10">
        <div className="max-w-3xl">
          <div className="text-sm text-white/60 mb-6">
            <span>{t.heroTag}</span>
          </div>

          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white leading-[1.1]">
            {t.heroTitle}
          </h1>

          <p className="mt-6 text-lg sm:text-xl text-white/70 max-w-2xl leading-relaxed">
            {t.heroSub}
          </p>

          <div className="mt-8 flex flex-wrap gap-3">
            <button
              onClick={() => scrollTo("#quote")}
              className="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-all duration-200 bg-white text-brand-900 hover:bg-white/90"
            >
              {t.heroCtaQuote}
              <ArrowRight size={16} />
            </button>

          </div>
        </div>
      </div>
    </section>
  );
}
