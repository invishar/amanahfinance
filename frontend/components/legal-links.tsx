import Link from "next/link";

export function LegalLinks({ compact = false }: { compact?: boolean }) {
  return (
    <div className={compact ? "legal-links legal-links-compact" : "legal-links"}>
      <Link href="/privacy">Kebijakan Privasi</Link>
      <span aria-hidden="true">·</span>
      <Link href="/terms">Syarat & Ketentuan</Link>
      <span aria-hidden="true">·</span>
      <Link href="/ai-disclaimer">Tentang Amina</Link>
    </div>
  );
}
