import { REVIEWS } from "../lib/constants";

interface ReviewsProps {
  t: Record<string, string>;
  lang: string;
}

export default function Reviews({ t, lang }: ReviewsProps) {
  const data = REVIEWS[lang as keyof typeof REVIEWS] || REVIEWS.fr;

  return (
    <section className="section-padding bg-surface-alt border-y border-border">
      <div className="container-wide">
        <div className="text-center">
          <p className="section-label">{t.reviewsTitle}</p>
          <h2 className="section-title">{t.reviewsTitle}</h2>
          <p className="section-subtitle mx-auto">
            {t.reviewsSub}
          </p>
        </div>

        <div className="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
          {data.map((review, i) => (
            <div
              key={i}
              className="card"
            >
              <svg
                className="mb-3 text-brand-300"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z" />
                <path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z" />
              </svg>
              <p className="text-sm text-text-secondary leading-relaxed italic">
                "{review.text}"
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}