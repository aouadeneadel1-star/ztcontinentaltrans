import { useState, useEffect } from "react";

const COOKIE_KEY = "zt-cookies-accepted";

export default function CookieConsent() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (!localStorage.getItem(COOKIE_KEY)) setVisible(true);
  }, []);

  const accept = () => {
    localStorage.setItem(COOKIE_KEY, "true");
    setVisible(false);
  };

  const refuse = () => {
    localStorage.setItem(COOKIE_KEY, "false");
    setVisible(false);
  };

  if (!visible) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/20 backdrop-blur-[2px] p-4">
      <div className="w-full max-w-lg rounded-2xl border border-border bg-white p-6 shadow-2xl animate-[slideUp_0.3s_ease-out]">
        <div className="flex items-start gap-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-lg">
            🍪
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-text">Consentement cookies</p>
            <p className="mt-1.5 text-xs leading-relaxed text-text-secondary">
              Ce site utilise des cookies pour améliorer votre navigation. Aucune donnée personnelle n'est partagée avec des tiers.
            </p>
          </div>
        </div>
        <div className="mt-5 flex items-center justify-between gap-3 border-t border-border pt-4">
          <button
            onClick={refuse}
            className="rounded-lg border border-border px-5 py-2.5 text-xs font-medium text-text-secondary hover:bg-surface-alt hover:text-text transition-colors"
          >
            Tout refuser
          </button>
          <button
            onClick={accept}
            className="rounded-lg bg-brand-700 px-5 py-2.5 text-xs font-medium text-white hover:bg-brand-800 transition-colors"
          >
            Accepter
          </button>
        </div>
      </div>
    </div>
  );
}
