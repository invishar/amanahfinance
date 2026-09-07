"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { Icon } from "@/components/icon";
import { SkeletonList } from "@/components/ui";
import { ApiError } from "@/lib/api/client";
import { useActiveFamily, useCreateInvite, useFamilyMembers } from "@/lib/api/hooks";
import { useSession } from "@/lib/auth";

const ROLE_LABEL: Record<string, string> = {
  admin: "Admin",
  member: "Anggota",
  viewer: "Pengamat",
};

export default function SettingsPage() {
  const router = useRouter();
  const { family } = useActiveFamily();
  const members = useFamilyMembers();
  const { logout } = useSession();

  const createInvite = useCreateInvite();
  const [inviteOpen, setInviteOpen] = useState(false);
  const [email, setEmail] = useState("");
  const [role, setRole] = useState("member");
  const [inviteToken, setInviteToken] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const submitInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setInviteToken(null);
    try {
      const invite = await createInvite.mutateAsync({ email: email.trim(), role });
      setInviteToken(invite.token ?? null);
      setInviteOpen(false);
      setEmail("");
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.fieldMessage("email") ?? err.message);
      } else {
        setError("Terjadi kesalahan tak terduga.");
      }
    }
  };

  const copyToken = async () => {
    if (!inviteToken) return;
    await navigator.clipboard?.writeText(inviteToken);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="amana-container">
      <h1 style={{ fontSize: 22, margin: 0 }}>Pengaturan Keluarga</h1>

      <div className="card elev-sm">
        <div className="card-kicker">Keluarga</div>
        <div className="card-title">{family?.name ?? "—"}</div>
      </div>

      <div className="card elev-sm">
        <div className="card-title">Anggota</div>

        {members.isPending ? (
          <SkeletonList count={2} height={38} />
        ) : (
          (members.data ?? []).map((member) => (
            <div
              key={member.id}
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
                <span style={{ fontSize: 14 }}>
                  {member.nickname || member.user?.full_name || "Anggota"}
                </span>
              </div>
              <span className="tag tag-accent">
                {ROLE_LABEL[member.role ?? "member"] ?? member.role}
              </span>
            </div>
          ))
        )}

        {inviteOpen ? (
          <form
            onSubmit={submitInvite}
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "var(--space-2)",
              marginTop: 10,
            }}
          >
            <div className="field">
              <label htmlFor="invite-email">Email yang diundang</label>
              <input
                id="invite-email"
                className="input"
                type="email"
                placeholder="anggota@email.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>
            <div className="field">
              <label htmlFor="invite-role">Peran</label>
              <select
                id="invite-role"
                className="input"
                value={role}
                onChange={(e) => setRole(e.target.value)}
              >
                <option value="member">Anggota</option>
                <option value="admin">Admin</option>
                <option value="viewer">Pengamat</option>
              </select>
            </div>
            {error && <p className="field-error">{error}</p>}
            <div style={{ display: "flex", gap: 8 }}>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => setInviteOpen(false)}
              >
                Batal
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={createInvite.isPending}
              >
                {createInvite.isPending ? "Mengirim…" : "Buat undangan"}
              </button>
            </div>
          </form>
        ) : (
          <button
            type="button"
            className="btn btn-secondary"
            style={{ marginTop: 10 }}
            onClick={() => {
              setInviteOpen(true);
              setError(null);
            }}
          >
            <Icon name="user-plus" size={14} />
            Undang Anggota Keluarga
          </button>
        )}

        {inviteToken && (
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
              {inviteToken}
            </span>
            <button
              type="button"
              className="btn btn-ghost"
              style={{ padding: 4, fontSize: 12 }}
              onClick={copyToken}
              aria-label="Salin kode undangan"
            >
              {copied ? (
                "Tersalin"
              ) : (
                <Icon name="copy" size={15} color="var(--color-accent)" />
              )}
            </button>
          </div>
        )}
      </div>

      <button
        type="button"
        className="btn btn-secondary"
        onClick={async () => {
          await logout();
          router.replace("/login");
        }}
      >
        <Icon name="log-out" size={14} />
        Keluar
      </button>
    </div>
  );
}
