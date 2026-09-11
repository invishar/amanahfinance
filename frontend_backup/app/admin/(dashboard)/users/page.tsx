"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import { SkeletonList } from "@/components/ui";
import { useAdminUser, useAdminUsers } from "@/lib/api/admin-hooks";
import { formatDateID } from "@/lib/format";

const SUBSCRIPTION_LABEL: Record<string, string> = {
  pending_payment: "Menunggu review",
  active: "Aktif",
  rejected: "Ditolak",
  expired: "Kedaluwarsa",
  none: "Belum berlangganan",
};

const SUBSCRIPTION_TAG_CLASS: Record<string, string> = {
  pending_payment: "tag-accent",
  active: "tag-outline",
  rejected: "tag-neutral",
  expired: "tag-neutral",
};

function SubscriptionTag({
  status,
  planName,
  expiresAt,
}: {
  status?: string | null;
  planName?: string | null;
  expiresAt?: string | null;
}) {
  if (!status) {
    return <span className="tag tag-neutral">Belum berlangganan</span>;
  }
  return (
    <span className={`tag ${SUBSCRIPTION_TAG_CLASS[status] ?? "tag-neutral"}`}>
      {planName && <>{planName} · </>}
      {SUBSCRIPTION_LABEL[status] ?? status}
      {expiresAt && (status === "active" || status === "expired") && (
        <> · s.d. {formatDateID(expiresAt)}</>
      )}
    </span>
  );
}

export default function AdminUsersPage() {
  const [search, setSearch] = useState("");
  const [subscriptionStatus, setSubscriptionStatus] = useState("");
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState<string | null>(null);

  const { data, isPending } = useAdminUsers(search, subscriptionStatus, page);
  const users = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
      <h1 style={{ fontSize: 22, margin: 0 }}>User</h1>

      <div style={{ display: "flex", gap: "var(--space-3)", flexWrap: "wrap" }}>
        <div className="field" style={{ maxWidth: 360, flex: 1, minWidth: 240 }}>
          <label htmlFor="user-search">Cari</label>
          <div style={{ position: "relative" }}>
            <Icon
              name="search"
              size={15}
              style={{ position: "absolute", left: 14, top: "50%", transform: "translateY(-50%)" }}
              color="var(--color-neutral-600)"
            />
            <input
              id="user-search"
              className="input"
              style={{ paddingLeft: 38 }}
              placeholder="Nama, email, atau telepon"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </div>
        </div>

        <div className="field" style={{ maxWidth: 220 }}>
          <label htmlFor="subscription-filter">Status langganan</label>
          <select
            id="subscription-filter"
            className="input"
            value={subscriptionStatus}
            onChange={(e) => {
              setSubscriptionStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">Semua</option>
            <option value="active">Aktif</option>
            <option value="pending_payment">Menunggu review</option>
            <option value="rejected">Ditolak</option>
            <option value="expired">Kedaluwarsa</option>
            <option value="none">Belum berlangganan</option>
          </select>
        </div>
      </div>

      <div className="card elev-sm">
        {isPending ? (
          <SkeletonList count={5} height={48} />
        ) : users.length === 0 ? (
          <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
            Tidak ada user yang cocok.
          </p>
        ) : (
          users.map((user) => (
            <button
              key={user.id}
              type="button"
              onClick={() => setSelectedId(user.id ?? null)}
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 10,
                padding: "10px 4px",
                borderBottom: "1px solid var(--color-divider)",
                background: "none",
                border: "none",
                borderRadius: 0,
                textAlign: "left",
                cursor: "pointer",
                font: "inherit",
                color: "inherit",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: 10, minWidth: 0 }}>
                <Icon name="user" size={18} color="var(--color-accent)" />
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontSize: 14, fontWeight: 600, overflow: "hidden", textOverflow: "ellipsis" }}>
                    {user.full_name}
                  </div>
                  <div className="text-muted" style={{ fontSize: 12 }}>
                    {user.email ?? user.phone ?? "—"} · {user.families_count ?? 0} family
                  </div>
                  <div className="text-muted" style={{ fontSize: 12 }}>
                    Daftar {user.created_at ? formatDateID(user.created_at) : "—"}
                  </div>
                </div>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: 6, flexWrap: "wrap", justifyContent: "flex-end" }}>
                {user.is_admin && <span className="tag tag-accent">Admin</span>}
                <SubscriptionTag
                  status={user.subscription_status}
                  planName={user.subscription_plan_name}
                  expiresAt={user.subscription_expires_at}
                />
              </div>
            </button>
          ))
        )}
      </div>

      {meta && (meta.last_page ?? 1) > 1 && (
        <div style={{ display: "flex", justifyContent: "center", alignItems: "center", gap: 10 }}>
          <button
            type="button"
            className="btn btn-icon-sm btn-secondary"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            aria-label="Halaman sebelumnya"
          >
            <Icon name="chevron-left" size={14} />
          </button>
          <span className="text-muted" style={{ fontSize: 12 }}>
            Halaman {meta.current_page} / {meta.last_page}
          </span>
          <button
            type="button"
            className="btn btn-icon-sm btn-secondary"
            disabled={page >= (meta.last_page ?? 1)}
            onClick={() => setPage((p) => p + 1)}
            aria-label="Halaman berikutnya"
          >
            <Icon name="chevron-right" size={14} />
          </button>
        </div>
      )}

      {selectedId && <UserDetailDialog id={selectedId} onClose={() => setSelectedId(null)} />}
    </div>
  );
}

const ROLE_LABEL: Record<string, string> = {
  admin: "Admin",
  member: "Anggota",
  viewer: "Pengamat",
};

function UserDetailDialog({ id, onClose }: { id: string; onClose: () => void }) {
  const { data: user, isPending } = useAdminUser(id);

  return (
    <div className="dialog-backdrop" onClick={onClose} role="presentation">
      <div
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label="Detail user"
      >
        {isPending || !user ? (
          <SkeletonList count={3} height={20} />
        ) : (
          <>
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
              <div className="dialog-title">{user.full_name}</div>
              {user.is_admin && <span className="tag tag-accent">Admin</span>}
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: 13 }}>
              <div>
                <span className="text-muted">Email:</span> {user.email ?? "—"}
              </div>
              <div>
                <span className="text-muted">Telepon:</span> {user.phone ?? "—"}
              </div>
              <div>
                <span className="text-muted">Login terakhir:</span>{" "}
                {user.last_login_at ? new Date(user.last_login_at).toLocaleString("id-ID") : "Belum pernah"}
              </div>
              <div>
                <span className="text-muted">Bergabung:</span>{" "}
                {user.created_at ? formatDateID(user.created_at) : "—"}
              </div>
            </div>

            <div>
              <div className="card-kicker" style={{ marginBottom: 6 }}>
                Family
              </div>
              {(user.families ?? []).length === 0 ? (
                <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
                  Belum tergabung di family manapun.
                </p>
              ) : (
                <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
                  {(user.families ?? []).map((f) => (
                    <div
                      key={f.family_id}
                      style={{
                        display: "flex",
                        flexDirection: "column",
                        gap: 4,
                        padding: "6px 0",
                        borderBottom: "1px solid var(--color-divider)",
                      }}
                    >
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                        <span style={{ fontSize: 13 }}>{f.family_name}</span>
                        <span className="tag tag-neutral">
                          {ROLE_LABEL[f.role ?? "member"] ?? f.role}
                        </span>
                      </div>
                      <SubscriptionTag
                        status={f.subscription_status}
                        planName={f.subscription_plan_name}
                        expiresAt={f.subscription_expires_at}
                      />
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}

        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={onClose}>
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
}
