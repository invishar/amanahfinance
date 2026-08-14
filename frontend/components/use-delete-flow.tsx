"use client";

import { useState } from "react";

import { ConfirmDialog } from "@/components/confirm-dialog";
import { ApiError } from "@/lib/api/client";
import { useDeleteEntity, type EntityKind } from "@/lib/api/hooks";

interface Target {
  id: string;
  label: string;
}

/** Alur hapus: konfirmasi → DELETE → tangani 409 (masih dipakai transaksi). */
export function useDeleteFlow(kind: EntityKind) {
  const [target, setTarget] = useState<Target | null>(null);
  const [error, setError] = useState<string | null>(null);
  const remove = useDeleteEntity(kind);

  const askDelete = (id: string, label: string) => {
    setError(null);
    setTarget({ id, label });
  };

  const confirm = async () => {
    if (!target) return;
    setError(null);
    try {
      await remove.mutateAsync(target.id);
      setTarget(null);
    } catch (e) {
      if (e instanceof ApiError) {
        setError(
          e.status === 409
            ? "Tidak bisa dihapus karena masih dipakai data lain."
            : e.message,
        );
      } else {
        setError("Terjadi kesalahan tak terduga.");
      }
    }
  };

  const dialog = target ? (
    <ConfirmDialog
      title={`Hapus ${target.label}?`}
      message="Tindakan ini tidak bisa dibatalkan."
      pending={remove.isPending}
      error={error}
      onCancel={() => setTarget(null)}
      onConfirm={confirm}
    />
  ) : null;

  return { askDelete, dialog };
}
