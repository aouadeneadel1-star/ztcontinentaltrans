import { useState } from "react";
import { Menu, X } from "lucide-react";
import { SITE, NAV_ITEMS_FR, NAV_ITEMS_EN } from "../lib/constants";

interface NavbarProps {
  lang: string;
  onToggleLang: () => void;
}

export default function Navbar({ lang, onToggleLang }: NavbarProps) {
  const [open, setOpen] = useState(false);
  const navItems = lang === "fr" ? NAV_ITEMS_FR : NAV_ITEMS_EN;

  const scrollTo = (id: string) => {
    setOpen(false);
    document.querySelector(id)?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-white border-b border-border">
      <div className="container-wide flex items-center justify-between h-14 md:h-16">
        <button onClick={() => scrollTo("#hero")} className="flex items-center gap-2 shrink-0">
          <img src="/logo.webp" alt={SITE.name} width="32" height="32" className="w-7 h-7 md:w-8 md:h-8" loading="eager" />
          <span className="text-base md:text-lg font-bold text-text tracking-tight">{SITE.name}</span>
        </button>

        <nav className="hidden md:flex items-center gap-1">
          {navItems.map((item) => (
            <button
              key={item.href}
              onClick={() => scrollTo(item.href)}
              className="px-3 py-2 text-sm text-text-secondary hover:text-text rounded-lg hover:bg-surface-alt transition-colors"
            >
              {item.label}
            </button>
          ))}
          <button
            onClick={onToggleLang}
            className="ml-2 px-3 py-1.5 text-xs font-medium text-text-secondary border border-border rounded-lg hover:bg-surface-alt transition-colors"
          >
            {lang === "fr" ? "EN" : "FR"}
          </button>
        </nav>

        <div className="flex items-center gap-1 md:hidden">
          <button
            onClick={onToggleLang}
            className="px-2.5 py-1.5 text-xs font-medium text-text-secondary border border-border rounded-lg hover:bg-surface-alt transition-colors"
          >
            {lang === "fr" ? "EN" : "FR"}
          </button>
          <button
            className="p-2 rounded-lg hover:bg-surface-alt transition-colors active:bg-surface-alt"
            onClick={() => setOpen(!open)}
            aria-label="Menu"
          >
            {open ? <X size={22} /> : <Menu size={22} />}
          </button>
        </div>
      </div>

      <div
        className={`md:hidden overflow-hidden transition-all duration-200 ease-in-out border-t border-border bg-white ${
          open ? "max-h-96 opacity-100" : "max-h-0 opacity-0"
        }`}
      >
        <div className="container-wide py-2">
          {navItems.map((item) => (
            <button
              key={item.href}
              onClick={() => scrollTo(item.href)}
              className="w-full px-4 py-3.5 text-base font-medium text-text-secondary hover:text-text rounded-xl hover:bg-surface-alt text-left transition-colors active:bg-surface-alt"
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>
    </header>
  );
}
