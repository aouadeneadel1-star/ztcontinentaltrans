import { MessageCircle } from "lucide-react";
import { SITE } from "../lib/constants";

export default function WhatsAppButton() {
  return (
    <a
      href={SITE.whatsappLink}
      target="_blank"
      rel="noopener noreferrer"
      className="fixed bottom-5 right-5 z-40 w-12 h-12 rounded-full bg-[#25D366] text-white flex items-center justify-center shadow-lg hover:bg-[#22c35e] transition-colors"
      aria-label="Contacter sur WhatsApp"
    >
      <MessageCircle size={22} />
    </a>
  );
}
