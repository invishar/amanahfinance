"use client";

import { useEffect } from "react";

import { useAmana } from "@/lib/store";

const TITLES = {
  wallet: "Wallet",
  account: "Akun",
  income: "Sumber Pemasukan",
  goal: "Target Tabungan",
} as const;

export function CrudDialog() {
  const { modal, closeModal, updateModalField, saveModal } = useAmana();

  useEffect(() => {
    if (!modal) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") closeModal();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [modal, closeModal]);

  if (!modal) return null;

  const text = (key: string, value: string) => ({
    className: "input",
    value,
    onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      updateModalField(key, e.target.value),
  });

  const number = (key: string, value: number) => ({
    className: "input",
    type: "number",
    value: String(value),
    onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
      updateModalField(key, Number(e.target.value)),
  });

  return (
    <div
      className="dialog-backdrop"
      style={{ zIndex: 30 }}
      onClick={closeModal}
      role="presentation"
    >
      <form
        className="dialog"
        onClick={(e) => e.stopPropagation()}
        onSubmit={(e) => {
          e.preventDefault();
          saveModal();
        }}
        role="dialog"
        aria-modal="true"
        aria-label={TITLES[modal.kind]}
      >
        <div className="dialog-title">{TITLES[modal.kind]}</div>

        {modal.kind === "wallet" && (
          <>
            <div className="field">
              <label htmlFor="wallet-name">Nama wallet</label>
              <input id="wallet-name" {...text("name", modal.item.name)} />
            </div>
            <div className="field">
              <label htmlFor="wallet-budget">Budget bulanan (Rp)</label>
              <input
                id="wallet-budget"
                {...number("monthly_budget", modal.item.monthly_budget)}
              />
            </div>
          </>
        )}

        {modal.kind === "account" && (
          <>
            <div className="field">
              <label htmlFor="account-name">Nama akun</label>
              <input id="account-name" {...text("name", modal.item.name)} />
            </div>
            <div className="field">
              <label htmlFor="account-type">Jenis</label>
              <select
                id="account-type"
                {...text("account_type", modal.item.account_type)}
              >
                <option value="bank">Bank</option>
                <option value="ewallet">E-Wallet</option>
                <option value="cash">Tunai</option>
              </select>
            </div>
            <div className="field">
              <label htmlFor="account-balance">Saldo (Rp)</label>
              <input
                id="account-balance"
                {...number("current_balance", modal.item.current_balance)}
              />
            </div>
          </>
        )}

        {modal.kind === "income" && (
          <div className="field">
            <label htmlFor="income-name">Nama sumber pemasukan</label>
            <input id="income-name" {...text("name", modal.item.name)} />
          </div>
        )}

        {modal.kind === "goal" && (
          <>
            <div className="field">
              <label htmlFor="goal-name">Nama target</label>
              <input
                id="goal-name"
                {...text("target_name", modal.item.target_name)}
              />
            </div>
            <div className="field">
              <label htmlFor="goal-target">Target nominal (Rp)</label>
              <input
                id="goal-target"
                {...number("target_amount", modal.item.target_amount)}
              />
            </div>
            <div className="field">
              <label htmlFor="goal-current">Sudah terkumpul (Rp)</label>
              <input
                id="goal-current"
                {...number("current_amount", modal.item.current_amount)}
              />
            </div>
            <div className="field">
              <label htmlFor="goal-deadline">Target tanggal</label>
              <input
                id="goal-deadline"
                type="date"
                {...text("deadline", modal.item.deadline)}
              />
            </div>
          </>
        )}

        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={closeModal}>
            Batal
          </button>
          <button type="submit" className="btn btn-primary">
            Simpan
          </button>
        </div>
      </form>
    </div>
  );
}
