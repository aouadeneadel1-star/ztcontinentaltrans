import { useState, useMemo, lazy, Suspense } from "react";
import { CONTENT, SERVICES } from "./lib/constants";
import Navbar from "./components/Navbar";
import Hero from "./components/Hero";
import CookieConsent from "./components/CookieConsent";

const Partners = lazy(() => import("./components/Partners"));
const Reviews = lazy(() => import("./components/Reviews"));
const AboutTrust = lazy(() => import("./components/AboutTrust"));
const Services = lazy(() => import("./components/Services"));
const Coverage = lazy(() => import("./components/Coverage"));
const QuoteCalculator = lazy(() => import("./components/QuoteCalculator"));
const Contact = lazy(() => import("./components/Contact"));
const Footer = lazy(() => import("./components/Footer"));
const WhatsAppButton = lazy(() => import("./components/WhatsAppButton"));

const SectionFallback = () => <div className="section-padding" />;

export default function App() {
  const [lang, setLang] = useState("fr");
  const t = useMemo(() => CONTENT[lang], [lang]);
  const services = useMemo(() => SERVICES[lang], [lang]);

  return (
    <div className="min-h-screen">
      <Navbar lang={lang} onToggleLang={() => setLang(lang === "fr" ? "en" : "fr")} />

      <main>
        <Hero t={t} lang={lang} />
        <Suspense fallback={<SectionFallback />}><Partners lang={lang} /></Suspense>
        <Suspense fallback={<SectionFallback />}><Reviews t={t} lang={lang} /></Suspense>
        <Suspense fallback={<SectionFallback />}><AboutTrust t={t} /></Suspense>
        <Suspense fallback={<SectionFallback />}><Services t={t} services={services} /></Suspense>
        <Suspense fallback={<SectionFallback />}><Coverage lang={lang} t={t} /></Suspense>
        <Suspense fallback={<SectionFallback />}><QuoteCalculator t={t} /></Suspense>
        <Suspense fallback={<SectionFallback />}><Contact t={t} /></Suspense>
      </main>

      <Suspense fallback={null}><Footer t={t} lang={lang} /></Suspense>
      <Suspense fallback={null}><WhatsAppButton /></Suspense>
      <CookieConsent />
    </div>
  );
}