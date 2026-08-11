// Bentuk data mengikuti response API (amanafinance-api). Saat backend siap,
// tipe di sini diganti hasil generate dari /api/v1/openapi.json.
// Uang selalu integer rupiah penuh — tidak pernah float.

export type AccountType = "bank" | "ewallet" | "cash";
export type TransactionType = "income" | "expense";
export type ActionStatus = "confirmed" | "cancelled";

export interface Family {
  id: string;
  name: string;
}

export interface User {
  id: string;
  name: string;
  role: "admin" | "member" | "viewer";
}

export interface Wallet {
  id: string;
  name: string;
  monthly_budget: number;
  icon: string;
}

export interface Account {
  id: string;
  name: string;
  account_type: AccountType;
  current_balance: number;
}

export interface IncomeSource {
  id: string;
  name: string;
}

export interface Transaction {
  id: string;
  type: TransactionType;
  amount: number;
  wallet_id: string | null;
  account_id: string | null;
  source_id: string | null;
  note: string;
  transaction_date: string;
}

export interface SavingsGoal {
  id: string;
  target_name: string;
  target_amount: number;
  current_amount: number;
  deadline: string;
  avg_monthly_contribution: number;
}

/* --- Chat & action card (baris `ai_actions` di API) ---------------------- */

export interface ActionCardField {
  label: string;
  value: string;
}

interface ActionCardBase {
  type: "action_confirm";
  title: string;
  fields: ActionCardField[];
  status?: ActionStatus;
}

export type ActionCard =
  | (ActionCardBase & {
      action: "create_transaction";
      payload?: {
        type: TransactionType;
        amount: number;
        wallet_id: string | null;
        account_id: string | null;
        note: string;
      };
    })
  | (ActionCardBase & {
      action: "create_wallet";
      payload?: { name: string; monthly_budget: number; icon: string };
    })
  | (ActionCardBase & {
      action: "create_account";
      payload?: {
        name: string;
        account_type: AccountType;
        current_balance: number;
      };
    });

export interface ChatMessage {
  id: string;
  role: "user" | "assistant";
  content?: string;
  actionCard?: ActionCard;
}

/* --- Draft form CRUD ----------------------------------------------------- */

export type EntityKind = "wallet" | "account" | "income" | "goal";

export interface WalletDraft {
  id?: string;
  name: string;
  monthly_budget: number;
  icon: string;
}
export interface AccountDraft {
  id?: string;
  name: string;
  account_type: AccountType;
  current_balance: number;
}
export interface IncomeDraft {
  id?: string;
  name: string;
}
export interface GoalDraft {
  id?: string;
  target_name: string;
  target_amount: number;
  current_amount: number;
  deadline: string;
  avg_monthly_contribution: number;
}

export type ModalState =
  | { kind: "wallet"; item: WalletDraft }
  | { kind: "account"; item: AccountDraft }
  | { kind: "income"; item: IncomeDraft }
  | { kind: "goal"; item: GoalDraft };
