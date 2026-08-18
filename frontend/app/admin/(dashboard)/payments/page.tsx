"use client";

import { useState } from "react";

import { Icon } from "@/components/icon";
import { SkeletonList } from "@/components/ui";
import {
  useActivateSubscription,
  useAdminSubscriptions,
  useRejectSubscription,
  type Subscription,
} from "@/lib/api/admin-hooks";
import { ApiError } from "@/lib/api/client";
import { formatDateID, formatRupiah } from "@/lib/format";

const STATUS_LABEL: Record<string, string> = {
  pending_payment: "Menunggu review",
  active: "Aktif",
  rejected: "Ditolak",
  expired: "Kedaluwarsa",
};

const STATUS_TAG_CLASS: Record<string, string> = {
  pending_payment: "tag-accent",
  active: "tag-outline",
  rejected: "tag-neutral",
  expired: "tag-neutral",
};

export default function AdminPaymentsPage() {
  const [status, setStatus] = useState("pending_payment");
  const [page, setPage] = useState(1);
  const [rejecting, setRejecting] = useState<Subscription | null>(null);

  const { data, isPending } = useAdminSubscriptions(status, page);
  const activate = useActivateSubscription();

  const subscriptions = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
      <h1 style={{ fontSize: 22, margin: 0 }}>Pembayaran</h1>

      <div className="field" style={{ maxWidth: 260 }}>
        <label htmlFor="status-filter">Status</label>
        <select
          id="status-filter"
          className="input"
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            setPage(1);
          }}
        >
          <option value="">Semua</option>
          <option value="pending_payment">Menunggu review</option>
          <option value="active">Aktif</option>
          <option value="rejected">Ditolak</option>
          <option value="expired">Kedaluwarsa</option>
        </select>
      </div>

      <div className="card elev-sm">
        {isPending ? (
          <SkeletonList count={4} height={72} />
        ) : subscriptions.length === 0 ? (
          <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
            Tidak ada langganan dengan status ini.
          </p>
        ) : (
          subscriptions.map((sub) => (
            <div
              key={sub.id}
              style={{
                display: "flex",
                flexDirection: "column",
                gap: 6,
                padding: "12px 4px",
                borderBottom: "1px solid var(--color-divider)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 10 }}>
                <div style={{ fontSize: 14, fontWeight: 600 }}>{sub.plan_name}</div>
                <span className={`tag ${STATUS_TAG_CLASS[sub.status ?? ""] ?? "tag-neutral"}`}>
                  {STATUS_LABEL[sub.status ?? ""] ?? sub.status}
                </span>
              </div>

              <div className="text-muted" style={{ fontSize: 12 }}>
                {formatRupiah(sub.amount ?? 0)} · diajukan{" "}
                {sub.paid_at ? formatDateID(sub.paid_at) : "—"}
              </div>

              {sub.payment_note && (
                <div className="text-muted" style={{ fontSize: 12 }}>
                  Catatan: {sub.payment_note}
                </div>
              )}

              {sub.payment_proof_url && (
                <a
                  href={sub.payment_proof_url}
                  target="_blank"
                  rel="noreferrer"
                  className="btn btn-ghost"
                  style={{ alignSelf: "flex-start", padding: "4px 0", fontSize: 12 }}
                >
                  <Icon name="file-text" size={14} />
                  Lihat bukti pembayaran
                </a>
              )}

              {sub.review_note && (
                <div className="text-muted" style={{ fontSize: 12 }}>
                  Catatan review: {sub.review_note}
                </div>
              )}

              {sub.status === "pending_payment" && (
                <div style={{ display: "flex", gap: 8, marginTop: 4 }}>
                  <button
                    type="button"
                    className="btn btn-primary"
                    disabled={activate.isPending}
                    onClick={() => sub.id && activate.mutate(sub.id)}
                  >
                    <Icon name="check" size={14} />
                    Aktifkan
                  </button>
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setRejecting(sub)}
                  >
                    <Icon name="x" size={14} />
                    Tolak
                  </button>
                </div>
              )}
            </div>
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

      {rejecting && (
        <RejectDialog
          subscription={rejecting}
          onCancel={() => setRejecting(null)}
          onDone={() => setRejecting(null)}
        />
      )}
    </div>
  );
}

function RejectDialog({
  subscription,
  onCancel,
  onDone,
}: {
  subscription: Subscription;
  onCancel: () => void;
  onDone: () => void;
}) {
  const reject = useRejectSubscription();
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    setError(null);
    if (!note.trim()) {
      setError("Alasan penolakan wajib diisi.");
      return;
    }
    try {
      if (subscription.id) {
        await reject.mutateAsync({ id: subscription.id, review_note: note.trim() });
      }
      onDone();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Terjadi kesalahan tak terduga.");
    }
  };

  return (
    <div className="dialog-backdrop" onClick={onCancel} role="presentation">
      <div
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label="Tolak langganan"
      >
        <div className="dialog-title">Tolak langganan</div>
        <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
          {subscription.plan_name} · {formatRupiah(subscription.amount ?? 0)}
        </p>
        <div className="field">
          <label htmlFor="review-note">Alasan penolakan</label>
          <textarea
            id="review-note"
            className="input"
            placeholder="Mis. bukti transfer tidak sesuai nominal"
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
        </div>
        {error && <p className="field-error">{error}</p>}
        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={onCancel}>
            Batal
          </button>
          <button
            type="button"
            className="btn btn-primary"
            onClick={submit}
            disabled={reject.isPending}
          >
            {reject.isPending ? "Menolak…" : "Tolak langganan"}
          </button>
        </div>
      </div>
    </div>
  );
}
