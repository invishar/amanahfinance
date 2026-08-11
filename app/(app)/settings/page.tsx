"use client";

import { useRouter } from "next/navigation";

import { Icon } from "@/components/icon";
import { useAmana } from "@/lib/store";

export default function SettingsPage() {
  const router = useRouter();
  const { family, currentUser, inviteCode, generateInvite, logout } = useAmana();

  return (
    <div className="amana-container">
      <h1 style={{ fontSize: 22, margin: 0 }}>Pengaturan Keluarga</h1>

      <div className="card elev-sm">
        <div className="card-kicker">Keluarga</div>
        <div className="card-title">{family.name}</div>
      </div>

      <div className="card elev-sm">
        <div className="card-title">Anggota</div>
        <div
          style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            padding: "8px 0",
            borderBottom: "1px solid var(--color-divider)",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <Icon name="user" size={18} color="var(--color-accent)" />
            <span style={{ fontSize: 14 }}>{currentUser.name}</span>
          </div>
          <span className="tag tag-accent">Admin</span>
        </div>

        <button
          type="button"
          className="btn btn-secondary"
          style={{ marginTop: 10 }}
          onClick={generateInvite}
        >
          <Icon name="user-plus" size={14} />
          Undang Anggota Keluarga
        </button>

        {inviteCode && (
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              background: "var(--color-surface)",
              borderRadius: "var(--radius-md)",
              padding: "10px 12px",
              marginTop: 6,
              border: "1px solid var(--color-divider)",
            }}
          >
            <span
              style={{
                fontFamily: "var(--font-heading)",
                fontSize: 15,
                letterSpacing: "0.04em",
              }}
            >
              {inviteCode}
            </span>
            <button
              type="button"
              className="btn btn-ghost"
              style={{ padding: 4 }}
              onClick={() => navigator.clipboard?.writeText(inviteCode)}
              aria-label="Salin kode undangan"
            >
              <Icon name="copy" size={15} color="var(--color-accent)" />
            </button>
          </div>
        )}
      </div>

      <button
        type="button"
        className="btn btn-secondary"
        onClick={() => {
          logout();
          router.push("/login");
        }}
      >
        <Icon name="log-out" size={14} />
        Keluar
      </button>
    </div>
  );
}
