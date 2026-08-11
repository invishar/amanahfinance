// MOCK ONLY — semua perhitungan di file ini adalah tanggung jawab SERVER.
// Begitu `lib/api.ts` terhubung, nilai-nilai ini datang jadi field response
// (spent per wallet, status budget, persentase, estimasi target) dan file ini
// dihapus. Jangan memanggilnya dari komponen; store yang memakainya.

import { MOCK_TODAY } from "@/lib/mock/data";
import type { SavingsGoal, Transaction } from "@/lib/types";

export function walletSpent(walletId: string, txs: Transaction[]): number {
  return txs
    .filter((t) => t.wallet_id === walletId && t.type === "expense")
    .reduce((sum, t) => sum + t.amount, 0);
}

export type BudgetStatus = "safe" | "near" | "over";

export function budgetStatus(spent: number, budget: number): BudgetStatus {
  if (spent > budget) return "over";
  if (budget > 0 && spent / budget >= 0.8) return "near";
  return "safe";
}

export function estimateGoalCompletion(goal: SavingsGoal): string {
  const remaining = Math.max(0, goal.target_amount - goal.current_amount);
  const monthsNeeded = Math.ceil(
    remaining / (goal.avg_monthly_contribution || 1),
  );
  const d = new Date(MOCK_TODAY);
  d.setMonth(d.getMonth() + monthsNeeded);
  return d.toLocaleDateString("id-ID", { month: "long", year: "numeric" });
}
