import { Link } from "@inertiajs/react";

import { Icon } from "@/components/icon";

const CARDS = [
  {
    href: "/admin/users",
    icon: "users",
    title: "User",
    body: "Direktori seluruh user platform lintas-family: cari, lihat detail dan family yang diikuti.",
  },
  {
    href: "/admin/payments",
    icon: "credit-card",
    title: "Pembayaran",
    body: "Review permintaan langganan yang menunggu pembayaran, aktifkan atau tolak.",
  },
  {
    href: "/admin/llm-settings",
    icon: "sparkles",
    title: "LLM & 9Router",
    body: "Pilih combo 9Router atau model dari provider yang tersedia untuk asisten Amina.",
  },
  {
    href: "/admin/ai-errors",
    icon: "alert-triangle",
    title: "Log AI",
    body: "Pantau kegagalan panggilan LLM (rate limit, auth, timeout) lintas family, difilter berdasarkan status dan model.",
  },
] as const;

export default function AdminHomePage() {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
      <h1 style={{ fontSize: 22, margin: 0 }}>Dashboard Admin</h1>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))",
          gap: "var(--space-3)",
        }}
      >
        {CARDS.map((card) => (
          <Link
            key={card.href}
            href={card.href}
            className="card elev-sm"
            style={{ textDecoration: "none", color: "inherit" }}
          >
            <Icon name={card.icon} size={22} color="var(--color-accent)" />
            <div className="card-title">{card.title}</div>
            <p className="card-body">{card.body}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}
