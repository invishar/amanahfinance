import Link from "next/link";

export const LEGAL_UPDATED_AT = "5 September 2026";
export const SUPPORT_EMAIL = "support@amanafinance.id";

export function LegalPage({
  eyebrow,
  title,
  summary,
  children,
}: {
  eyebrow: string;
  title: string;
  summary: string;
  children: React.ReactNode;
}) {
  return (
    <main className="legal-shell">
      <nav className="legal-nav" aria-label="Navigasi dokumen hukum">
        <Link href="/" className="legal-brand">
          <span className="amana-brand-circle">AF</span>
          <span>AmanaFinance</span>
        </Link>
        <Link href="/login" className="btn btn-secondary">
          Kembali ke aplikasi
        </Link>
      </nav>

      <article className="legal-card">
        <header className="legal-header">
          <span className="page-eyebrow">{eyebrow}</span>
          <h1>{title}</h1>
          <p>{summary}</p>
          <small>Terakhir diperbarui: {LEGAL_UPDATED_AT}</small>
        </header>
        <div className="legal-content">{children}</div>
      </article>

      <footer className="legal-footer">
        <Link href="/privacy">Privasi</Link>
        <Link href="/terms">Syarat & Ketentuan</Link>
        <Link href="/ai-disclaimer">Tentang Amina</Link>
      </footer>
    </main>
  );
}

export function LegalContact() {
  return <a href={`mailto:${SUPPORT_EMAIL}`}>{SUPPORT_EMAIL}</a>;
}
