export const SITE = {
  name: "ZT Continental Trans",
  tagline: "Transport et logistique en France et en Europe",
  phone: "+33 6 13 54 35 09",
  email: "contact@ztcontinentaltrans.com",
  address: "1 Place de la Boulaie, 94470 Boissy-Saint-Léger",
  siret: "881 077 796 00020",
  siren: "881 077 796",
  tva: "FR84881077796",
  rcs: "881 077 796 R.C.S. Creteil",
  capital: "10 000,00 €",
  legalForm: "SARL",
  founded: 2020,
  founder: "Zoheir BELHACHEMI",
  phoneLink: "tel:+33613543509",
  whatsappLink: "https://wa.me/33613543509",
};

export const NAV_ITEMS_FR = [
  { label: "Accueil", href: "#hero" },
  { label: "Services", href: "#services" },
  { label: "Couverture", href: "#coverage" },
  { label: "Devis", href: "#quote" },
  { label: "Contact", href: "#contact" },
];

export const NAV_ITEMS_EN = [
  { label: "Home", href: "#hero" },
  { label: "Services", href: "#services" },
  { label: "Coverage", href: "#coverage" },
  { label: "Quote", href: "#quote" },
  { label: "Contact", href: "#contact" },
];

export const SERVICES: {
  fr: { id: string; title: string; desc: string; icon: string }[];
  en: { id: string; title: string; desc: string; icon: string }[];
} = {
  fr: [
    {
      id: "road",
      title: "Transport Routier",
      desc: "Fret national et européen en camions sécurisés. Suivi GPS en temps réel.",
      icon: "Truck",
    },
    {
      id: "express",
      title: "Livraison Express",
      desc: "Messagerie rapide et coursiers dédiés pour vos urgences.",
      icon: "Zap",
    },
    {
      id: "logistics",
      title: "Logistique & Stockage",
      desc: "Entreposage sécurisé, préparation de commandes et cross-docking.",
      icon: "Boxes",
    },
    {
      id: "relocation",
      title: "Déménagement",
      desc: "Prise en charge complète : emballage, manutention et transport.",
      icon: "Home",
    },
    {
      id: "distribution",
      title: "Distribution Urbaine",
      desc: "Livraison du dernier kilomètre en centres-villes.",
      icon: "Navigation",
    },
    {
      id: "sale",
      title: "Véhicules d'Occasion",
      desc: "Achat et vente d'utilitaires, tracteurs routiers et remorques.",
      icon: "ShieldCheck",
    },
  ],
  en: [
    {
      id: "road",
      title: "Road Freight",
      desc: "National and European freight in secure trucks. Real-time GPS tracking.",
      icon: "Truck",
    },
    {
      id: "express",
      title: "Express Delivery",
      desc: "Fast courier services for urgent shipments.",
      icon: "Zap",
    },
    {
      id: "logistics",
      title: "Logistics & Storage",
      desc: "Secure warehousing, order preparation and cross-docking.",
      icon: "Boxes",
    },
    {
      id: "relocation",
      title: "Moving Services",
      desc: "Full-service moving: packing, handling and transport.",
      icon: "Home",
    },
    {
      id: "distribution",
      title: "Urban Distribution",
      desc: "Last-mile delivery in city centers.",
      icon: "Navigation",
    },
    {
      id: "sale",
      title: "Used Vehicles",
      desc: "Buy and sell utility vehicles, trucks and trailers.",
      icon: "ShieldCheck",
    },
  ],
};

export const CONTENT: Record<string, Record<string, string>> = {
  fr: {
    heroTitle: "Transport et logistique en France et en Europe",
    heroTag: "Transport et logistique depuis 2020",
    heroSub:
      "Fret routier, livraison express, entreposage et distribution urbaine. Une équipe disponible 24h/7j.",
    heroCtaQuote: "Obtenir un devis",
    servicesTitle: "Nos services",
    servicesSub:
      "Six domaines d'expertise pour couvrir l'ensemble de vos besoins logistiques.",
    aboutTitle: "Pourquoi nous choisir",
    aboutSub:
      "Une entreprise de transport fondée en 2020, basée à Boissy-Saint-Léger. Notre équipe assure des prestations fiables et adaptées à chaque client.",
    trustYears: "Années d'expérience",
    trustAvailable: "Disponibilité",
    trustQuote: "Devis gratuit",
    trustResponse: "Intervention rapide",
    trustSatisfaction: "Clients satisfaits",
    trustYearsVal: "Depuis 2020",
    trustAvailableVal: "24h/7j",
    trustQuoteVal: "Sous 24h",
    trustResponseVal: "Express possible",
    trustSatisfactionVal: "98%",
    quoteTitle: "Calculateur de devis",
    quoteSub:
      "Estimez votre tarif en quelques clics. Un devis précis et sans engagement.",
    reviewsTitle: "Témoignages",
    reviewsSub: "La satisfaction de nos clients est notre meilleure publicité.",
    contactTitle: "Contact",
    contactSub: "Une question ? Notre équipe vous répond sous 24h.",
    contactName: "Nom",
    contactEmail: "Email",
    contactPhone: "Téléphone",
    contactMessage: "Message",
    contactSend: "Envoyer",
    contactSent: "Message envoyé avec succès",
    contactAddress: "1 Place de la Boulaie, 94470 Boissy-Saint-Léger",
    footerDesc:
      "Transport routier de marchandises, livraison express et services logistiques en France et en Europe.",
    footerNav: "Navigation",
    footerContact: "Contact",
    footerLegal: "© 2026 ZT Continental Trans",
    quoteService: "Type de service",
    quoteFrom: "Ville de départ",
    quoteTo: "Ville d'arrivée",
    quoteWeight: "Poids (tonnes)",
    quoteCalculate: "Calculer",
    quoteResult: "Estimation",
    quoteTotal: "Total TTC",
    quoteDownload: "Télécharger PDF",
    quoteEmail: "Votre email",
    quoteEmailSend: "Envoyer par email",
    quoteEmailSent: "Devis envoyé par email",
  },
  en: {
    heroTitle: "Transport and Logistics in France and Europe",
    heroTag: "Transport and logistics since 2020",
    heroSub:
      "Road freight, express delivery, warehousing and urban distribution. A team available 24/7.",
    heroCtaQuote: "Get a quote",
    servicesTitle: "Our services",
    servicesSub:
      "Six areas of expertise covering all your logistics needs.",
    aboutTitle: "Why choose us",
    aboutSub:
      "A transport company founded in 2020, based in Boissy-Saint-Léger. Our team provides reliable services tailored to each client.",
    trustYears: "Years of experience",
    trustAvailable: "Availability",
    trustQuote: "Free quote",
    trustResponse: "Fast response",
    trustSatisfaction: "Client satisfaction",
    trustYearsVal: "Since 2020",
    trustAvailableVal: "24/7",
    trustQuoteVal: "Within 24h",
    trustResponseVal: "Express available",
    trustSatisfactionVal: "98%",
    quoteTitle: "Quote calculator",
    quoteSub:
      "Estimate your rate in a few clicks. An accurate, no-obligation quote.",
    reviewsTitle: "Testimonials",
    reviewsSub: "Our clients' satisfaction is our best advertisement.",
    contactTitle: "Contact",
    contactSub: "A question? Our team will respond within 24h.",
    contactName: "Name",
    contactEmail: "Email",
    contactPhone: "Phone",
    contactMessage: "Message",
    contactSend: "Send",
    contactSent: "Message sent successfully",
    contactAddress: "1 Place de la Boulaie, 94470 Boissy-Saint-Léger",
    footerDesc:
      "Road freight, express delivery and logistics services in France and Europe.",
    footerNav: "Navigation",
    footerContact: "Contact",
    footerLegal: "© 2026 ZT Continental Trans",
    quoteService: "Service type",
    quoteFrom: "Departure city",
    quoteTo: "Arrival city",
    quoteWeight: "Weight (tons)",
    quoteCalculate: "Calculate",
    quoteResult: "Estimate",
    quoteTotal: "Total incl. VAT",
    quoteDownload: "Download PDF",
    quoteEmail: "Your email",
    quoteEmailSend: "Send by email",
    quoteEmailSent: "Quote sent by email",
  },
};

export const PARTNER_LOGOS = [
  { name: "Leroy Merlin", webp: "/images/partners/leroy-merlin.webp", fallback: "/images/partners/leroy-merlin.png" },
  { name: "IKEA", webp: "/images/partners/ikea.webp", fallback: "/images/partners/ikea.png" },
  { name: "SuperVan", webp: "/images/partners/supervan.webp", fallback: "/images/partners/supervan.png" },
  { name: "Pages Jaunes", webp: "/images/partners/pages-jaunes.webp", fallback: "/images/partners/pages-jaunes.png" },
  { name: "Colisweb", webp: "/images/partners/colisweb.png", fallback: "/images/partners/colisweb.png" },
  { name: "JP Home", webp: "/images/partners/jp-home.png", fallback: "/images/partners/jp-home.png" },
  { name: "C Chez Vous", webp: "/images/partners/c-chez-vous.png", fallback: "/images/partners/c-chez-vous.png" },
  { name: "GOXL", webp: "/images/partners/goxl.png", fallback: "/images/partners/goxl.png" },
];

export const PARTNERS = {
  fr: {
    title: "Ils nous font confiance",
    sub: "Quelques-unes des entreprises avec lesquelles nous collaborons.",
    logos: PARTNER_LOGOS,
  },
  en: {
    title: "Trusted by",
    sub: "Some of the companies we work with.",
    logos: PARTNER_LOGOS,
  },
};

export const COVERAGE = {
  fr: {
    title: "Où nous intervenons",
    sub: "France, Europe et Algérie. Une couverture adaptée à vos besoins.",
    base: "Basés à Boissy-Saint-Léger (94), nous desservons l'ensemble du territoire français et européen.",
    countries: [
      { name: "France", flag: "🇫🇷", desc: "National" },
      { name: "Belgique", flag: "🇧🇪", desc: "Bruxelles, Liège, Anvers" },
      { name: "Allemagne", flag: "🇩🇪", desc: "Berlin, Francfort, Munich" },
      { name: "Italie", flag: "🇮🇹", desc: "Milan, Rome, Turin" },
      { name: "Espagne", flag: "🇪🇸", desc: "Madrid, Barcelone" },
      { name: "Algérie", flag: "🇩🇿", desc: "Alger (import/export maritime)" },
    ],
  },
  en: {
    title: "Where we operate",
    sub: "France, Europe and Algeria. Coverage adapted to your needs.",
    base: "Based in Boissy-Saint-Léger (94), we serve all of France and Europe.",
    countries: [
      { name: "France", flag: "🇫🇷", desc: "National" },
      { name: "Belgium", flag: "🇧🇪", desc: "Brussels, Liège, Antwerp" },
      { name: "Germany", flag: "🇩🇪", desc: "Berlin, Frankfurt, Munich" },
      { name: "Italy", flag: "🇮🇹", desc: "Milan, Rome, Turin" },
      { name: "Spain", flag: "🇪🇸", desc: "Madrid, Barcelona" },
      { name: "Algeria", flag: "🇩🇿", desc: "Algiers (maritime import/export)" },
    ],
  },
};

export const REVIEWS = {
  fr: [
    {
      text: "Rapidité dans la livraison, livreur agréable et souriant.",
    },
    {
      text: "La livraison s'est bien déroulée les livreurs étaient très professionnels.",
    },
    {
      text: "Je suis très satisfaite du service livraison merci IKEA.",
    },
    {
      text: "Veuillez remercier les deux chauffeurs qui ont tous les 2 été polis.",
    },
  ],
  en: [
    {
      text: "Fast delivery, pleasant and smiling delivery person.",
    },
    {
      text: "The delivery went well, the delivery people were very professional.",
    },
    {
      text: "I am very satisfied with the delivery service thank you IKEA.",
    },
    {
      text: "Please thank the two drivers who were both polite.",
    },
  ],
};

export const CITIES = [
  { label: "Paris", country: "France" },
  { label: "Marseille", country: "France" },
  { label: "Lyon", country: "France" },
  { label: "Lille", country: "France" },
  { label: "Bordeaux", country: "France" },
  { label: "Strasbourg", country: "France" },
  { label: "Nantes", country: "France" },
  { label: "Toulouse", country: "France" },
  { label: "Nice", country: "France" },
  { label: "Rennes", country: "France" },
  { label: "Bruxelles", country: "Belgique" },
  { label: "Berlin", country: "Allemagne" },
  { label: "Francfort", country: "Allemagne" },
  { label: "Milan", country: "Italie" },
  { label: "Rome", country: "Italie" },
  { label: "Madrid", country: "Espagne" },
  { label: "Barcelone", country: "Espagne" },
  { label: "Alger", country: "Algérie" },
];
