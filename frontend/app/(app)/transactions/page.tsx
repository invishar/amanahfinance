"use client";

import { useMemo, useState } from "react";

import { ConfirmDialog } from "@/components/confirm-dialog";
import { Icon } from "@/components/icon";
import { TransactionEditDialog } from "@/components/transaction-edit-dialog";
import { EmptyState, PageHeader, SkeletonList } from "@/components/ui";
import { ApiError } from "@/lib/api/client";
import {
  type Transaction,
  useDeleteTransaction,
  useIncomeSources,
  useTransactions,
  useWallets,
} from "@/lib/api/hooks";
import { recentTransactions } from "@/lib/selectors";

export default function TransactionsPage() {
  const transactions = useTransactions();
  const wallets = useWallets();
  const incomeSources = useIncomeSources();
  const remove = useDeleteTransaction();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Transaction | null>(null);
  const [deleting, setDeleting] = useState<Transaction | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const list = useMemo(
    () =>
      recentTransactions(
        transactions.data ?? [],
        wallets.data ?? [],
        incomeSources.data ?? [],
        50,
      ),
    [transactions.data, wallets.data, incomeSources.data],
  );

  const openCreate = () => {
    setEditing(null);
    setDialogOpen(true);
  };

  const openEdit = (transaction: Transaction) => {
    setEditing(transaction);
    setDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!deleting?.id) return;
    setDeleteError(null);
    try {
      await remove.mutateAsync(deleting.id);
      setDeleting(null);
    } catch (error) {
      setDeleteError(
        error instanceof ApiError
          ? error.message
          : "Transaksi belum berhasil dihapus.",
      );
    }
  };

  return (
    <div className="amana-container">
      <PageHeader
        eyebrow="Catatan keuangan"
        title="Transaksi"
        description="Tambah, koreksi, atau hapus transaksi secara manual kapan pun—tanpa bergantung pada Amina."
        onAdd={openCreate}
        addLabel="Catat transaksi"
      />

      {transactions.isPending ? (
        <div className="card"><SkeletonList count={7} height={58} /></div>
      ) : transactions.isError ? (
        <div className="notice notice-danger">
          Transaksi belum bisa dimuat. Coba muat ulang halaman.
        </div>
      ) : list.length === 0 ? (
        <EmptyState
          icon="receipt"
          title="Belum ada transaksi"
          message="Catat transaksi pertama secara manual. Fitur ini tetap tersedia meski Amina sedang offline."
          actionLabel="Catat transaksi"
          onAction={openCreate}
        />
      ) : (
        <div className="card transaction-list">
          {list.map((transaction) => (
            <div key={transaction.id} className="transaction-row">
              <div
                className="entity-icon"
                data-tone={transaction.raw.type === "income" ? "mint" : "coral"}
              >
                <Icon name={transaction.icon} size={18} />
              </div>
              <div className="transaction-main">
                <strong>{transaction.note}</strong>
                <span>{transaction.walletName} · {transaction.dateLabel}</span>
              </div>
              <strong
                className="transaction-amount"
                style={{ color: transaction.iconColor }}
              >
                {transaction.amountLabel}
              </strong>
              <div className="row-actions">
                <button
                  type="button"
                  className="btn btn-icon-sm btn-secondary"
                  onClick={() => openEdit(transaction.raw)}
                  aria-label={`Ubah ${transaction.note}`}
                >
                  <Icon name="pencil" size={14} />
                </button>
                <button
                  type="button"
                  className="btn btn-icon-sm btn-secondary"
                  onClick={() => {
                    setDeleteError(null);
                    setDeleting(transaction.raw);
                  }}
                  aria-label={`Hapus ${transaction.note}`}
                >
                  <Icon name="trash-2" size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <TransactionEditDialog
        open={dialogOpen}
        transaction={editing}
        onClose={() => setDialogOpen(false)}
      />
      {deleting ? (
        <ConfirmDialog
          title="Hapus transaksi?"
          message="Saldo akun dan progres terkait akan disesuaikan kembali secara otomatis."
          pending={remove.isPending}
          error={deleteError}
          onCancel={() => setDeleting(null)}
          onConfirm={confirmDelete}
        />
      ) : null}
    </div>
  );
}
