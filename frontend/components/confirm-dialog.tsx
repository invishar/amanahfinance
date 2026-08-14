"use client";

export function ConfirmDialog({
  title,
  message,
  confirmLabel = "Hapus",
  pending,
  error,
  onCancel,
  onConfirm,
}: {
  title: string;
  message: string;
  confirmLabel?: string;
  pending?: boolean;
  error?: string | null;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <div
      className="dialog-backdrop"
      style={{ zIndex: 30 }}
      onClick={onCancel}
      role="presentation"
    >
      <div
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        role="alertdialog"
        aria-modal="true"
        aria-label={title}
      >
        <div className="dialog-title">{title}</div>
        <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
          {message}
        </p>
        {error && <p className="field-error">{error}</p>}
        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={onCancel}>
            Batal
          </button>
          <button
            type="button"
            className="btn btn-primary"
            onClick={onConfirm}
            disabled={pending}
          >
            {pending ? "Menghapus…" : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
