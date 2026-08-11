"use client";

// Store UI + cache data sementara untuk seluruh aplikasi.
//
// Catatan porting: di produksi lapisan ini pecah jadi dua — state UI murni
// (modal, sheet, draft form) tetap di klien, sedangkan `wallets`, `accounts`,
// `incomeSources`, `savingsGoals`, `transactions`, `messages`, `analytics`,
// `family` pindah ke TanStack Query dengan key ber-familyId, dan semua mutasi
// jalan lewat `lib/api.ts`. Selama backend belum ada, semuanya ditahan di sini
// supaya komponen tidak pernah menyentuh mock secara langsung.

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";

import {
  bankAccounts as mockAccounts,
  currentUser as mockUser,
  family as mockFamily,
  incomeSources as mockIncome,
  MOCK_TODAY,
  newId,
  savingsGoals as mockGoals,
  transactions as mockTransactions,
  wallets as mockWallets,
} from "@/lib/mock/data";
import {
  mockAssistantReply,
  onboardQuestions,
  seedMessages,
  type Scenario,
} from "@/lib/mock/assistant";
import type {
  Account,
  ActionStatus,
  ChatMessage,
  EntityKind,
  Family,
  IncomeSource,
  ModalState,
  SavingsGoal,
  Transaction,
  User,
  Wallet,
} from "@/lib/types";

interface AmanaState {
  family: Family;
  currentUser: User;
  wallets: Wallet[];
  accounts: Account[];
  incomeSources: IncomeSource[];
  savingsGoals: SavingsGoal[];
  transactions: Transaction[];
  messages: ChatMessage[];
  isTyping: boolean;
  /** null = wawancara awal sudah selesai / belum dimulai */
  onboardStep: number | null;
  onboardAnswers: Record<string, string>;
  inviteCode: string | null;
  modal: ModalState | null;
  moreSheetOpen: boolean;
  /** true setelah data pertama "termuat" — dipakai untuk skeleton. */
  ready: boolean;
}

function initialState(): AmanaState {
  return {
    family: { ...mockFamily },
    currentUser: { ...mockUser },
    wallets: [...mockWallets],
    accounts: [...mockAccounts],
    incomeSources: [...mockIncome],
    savingsGoals: [...mockGoals],
    transactions: [...mockTransactions],
    messages: seedMessages(mockFamily.name),
    isTyping: false,
    onboardStep: null,
    onboardAnswers: {},
    inviteCode: null,
    modal: null,
    moreSheetOpen: false,
    ready: false,
  };
}

interface AmanaActions {
  sendChat: (text: string, scenario?: Scenario) => void;
  skipOnboardStep: () => void;
  resolveAction: (messageId: string, status: ActionStatus) => void;
  startOnboarding: (familyName: string | null) => void;
  openModal: (kind: EntityKind, id?: string) => void;
  closeModal: () => void;
  updateModalField: (key: string, value: string | number) => void;
  saveModal: () => void;
  deleteItem: (kind: EntityKind, id: string) => void;
  generateInvite: () => void;
  setMoreSheetOpen: (open: boolean) => void;
  logout: () => void;
}

type AmanaContextValue = AmanaState & AmanaActions & { today: string };

const AmanaContext = createContext<AmanaContextValue | null>(null);

const listKeyOf = {
  wallet: "wallets",
  account: "accounts",
  income: "incomeSources",
  goal: "savingsGoals",
} as const;

function defaultDraft(kind: EntityKind): ModalState {
  switch (kind) {
    case "wallet":
      return { kind, item: { name: "", monthly_budget: 0, icon: "shopping-cart" } };
    case "account":
      return { kind, item: { name: "", account_type: "bank", current_balance: 0 } };
    case "income":
      return { kind, item: { name: "" } };
    case "goal":
      return {
        kind,
        item: {
          target_name: "",
          target_amount: 0,
          current_amount: 0,
          deadline: "2026-12-31",
          avg_monthly_contribution: 500000,
        },
      };
  }
}

export function AmanaProvider({ children }: { children: ReactNode }) {
  const [state, setStateRaw] = useState<AmanaState>(initialState);
  const stateRef = useRef(state);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);

  // setState yang menjaga stateRef tetap sinkron, supaya callback di dalam
  // setTimeout selalu membaca nilai terbaru.
  const setState = useCallback(
    (updater: (prev: AmanaState) => AmanaState) => {
      setStateRaw((prev) => {
        const next = updater(prev);
        stateRef.current = next;
        return next;
      });
    },
    [],
  );

  const later = useCallback((fn: () => void, ms: number) => {
    const id = setTimeout(fn, ms);
    timers.current.push(id);
  }, []);

  useEffect(() => {
    const pending = timers.current;
    return () => pending.forEach(clearTimeout);
  }, []);

  // Menandai data "selesai dimuat" — berdiri di tempat request pertama ke API,
  // supaya skeleton punya sesuatu untuk ditunggu.
  useEffect(() => {
    const id = setTimeout(() => setState((s) => ({ ...s, ready: true })), 350);
    return () => clearTimeout(id);
  }, [setState]);

  const pushMessage = useCallback(
    (msg: Omit<ChatMessage, "id">) => {
      setState((s) => ({
        ...s,
        messages: [...s.messages, { ...msg, id: newId("m") }],
      }));
    },
    [setState],
  );

  const advanceOnboarding = useCallback(
    (skipped: boolean) => {
      later(() => {
        const step = stateRef.current.onboardStep ?? 0;
        const next = step + 1;
        setState((s) => ({ ...s, isTyping: false }));
        if (skipped) {
          pushMessage({
            role: "assistant",
            content: "Oke, nggak masalah — bisa diisi belakangan.",
          });
        }
        if (next < onboardQuestions.length) {
          setState((s) => ({ ...s, onboardStep: next }));
          later(
            () =>
              pushMessage({
                role: "assistant",
                content: onboardQuestions[next].question,
              }),
            skipped ? 500 : 0,
          );
        } else {
          setState((s) => ({ ...s, onboardStep: null }));
          pushMessage({
            role: "assistant",
            content:
              "Makasih banyak infonya! Ini udah cukup buat aku bantu kamu. Sekarang kamu bisa langsung cerita transaksi, kirim foto struk, atau minta saran kapan aja.",
          });
        }
      }, 700);
    },
    [later, pushMessage, setState],
  );

  const sendChat = useCallback(
    (text: string, scenario?: Scenario) => {
      const finalText = text.trim();
      if (!finalText) return;

      const onboardStep = stateRef.current.onboardStep;
      pushMessage({ role: "user", content: finalText });

      // Jawaban wawancara awal: simpan, lalu lanjut ke pertanyaan berikutnya.
      if (onboardStep !== null && scenario === undefined) {
        const key = onboardQuestions[onboardStep].key;
        setState((s) => ({
          ...s,
          onboardAnswers: { ...s.onboardAnswers, [key]: finalText },
          isTyping: true,
        }));
        advanceOnboarding(false);
        return;
      }

      setState((s) => ({ ...s, isTyping: true }));
      later(() => {
        const reply = mockAssistantReply(finalText, scenario);
        setState((s) => ({ ...s, isTyping: false }));
        pushMessage({ role: "assistant", ...reply });
      }, 750);
    },
    [advanceOnboarding, later, pushMessage, setState],
  );

  const skipOnboardStep = useCallback(() => {
    setState((s) => ({ ...s, isTyping: true }));
    advanceOnboarding(true);
  }, [advanceOnboarding, setState]);

  const resolveAction = useCallback(
    (messageId: string, status: ActionStatus) => {
      const msg = stateRef.current.messages.find((m) => m.id === messageId);
      const card = msg?.actionCard;

      setState((s) => ({
        ...s,
        messages: s.messages.map((m) =>
          m.id === messageId && m.actionCard
            ? { ...m, actionCard: { ...m.actionCard, status } }
            : m,
        ),
      }));

      if (status !== "confirmed" || !card?.payload) return;

      // Di produksi ini satu panggilan `POST /ai-actions/{id}/confirm`; server
      // yang menulis datanya, klien tinggal invalidasi query terkait.
      if (card.action === "create_transaction") {
        const payload = card.payload;
        setState((s) => ({
          ...s,
          transactions: [
            ...s.transactions,
            {
              id: newId("t"),
              transaction_date: MOCK_TODAY,
              source_id: null,
              ...payload,
            },
          ],
        }));
      } else if (card.action === "create_wallet") {
        const payload = card.payload;
        setState((s) => ({
          ...s,
          wallets: [...s.wallets, { id: newId("w"), ...payload }],
        }));
      } else if (card.action === "create_account") {
        const payload = card.payload;
        setState((s) => ({
          ...s,
          accounts: [...s.accounts, { id: newId("a"), ...payload }],
        }));
      }

      later(
        () =>
          pushMessage({
            role: "assistant",
            content: "Sudah aku simpan. Ada lagi yang mau dicatat?",
          }),
        400,
      );
    },
    [later, pushMessage, setState],
  );

  const startOnboarding = useCallback(
    (familyName: string | null) => {
      const name = familyName?.trim() || "keluargamu";
      setState((s) => ({
        ...s,
        family: familyName?.trim()
          ? { ...s.family, name: familyName.trim() }
          : s.family,
        onboardStep: 0,
        onboardAnswers: {},
        messages: [
          {
            id: "m_ob_intro",
            role: "assistant",
            content: `Halo! Aku Amina, asisten keuangan buat ${name}. Sebelum mulai, boleh aku tanya beberapa hal dasar biar bantuanku makin pas? Santai aja, kalau belum tahu jawabannya bisa dilewati dulu.`,
          },
          {
            id: "m_ob_q0",
            role: "assistant",
            content: onboardQuestions[0].question,
          },
        ],
      }));
    },
    [setState],
  );

  const openModal = useCallback(
    (kind: EntityKind, id?: string) => {
      setState((s) => {
        if (!id) return { ...s, modal: defaultDraft(kind) };
        switch (kind) {
          case "wallet": {
            const found = s.wallets.find((w) => w.id === id);
            return found ? { ...s, modal: { kind, item: { ...found } } } : s;
          }
          case "account": {
            const found = s.accounts.find((a) => a.id === id);
            return found ? { ...s, modal: { kind, item: { ...found } } } : s;
          }
          case "income": {
            const found = s.incomeSources.find((i) => i.id === id);
            return found ? { ...s, modal: { kind, item: { ...found } } } : s;
          }
          case "goal": {
            const found = s.savingsGoals.find((g) => g.id === id);
            return found ? { ...s, modal: { kind, item: { ...found } } } : s;
          }
        }
      });
    },
    [setState],
  );

  const closeModal = useCallback(
    () => setState((s) => ({ ...s, modal: null })),
    [setState],
  );

  const updateModalField = useCallback(
    (key: string, value: string | number) => {
      setState((s) =>
        s.modal
          ? {
              ...s,
              modal: {
                ...s.modal,
                item: { ...s.modal.item, [key]: value },
              } as ModalState,
            }
          : s,
      );
    },
    [setState],
  );

  const saveModal = useCallback(() => {
    setState((s) => {
      if (!s.modal) return s;
      const { kind, item } = s.modal;
      const listKey = listKeyOf[kind];
      const list = s[listKey] as { id: string }[];
      const exists = item.id && list.some((x) => x.id === item.id);
      const nextList = exists
        ? list.map((x) => (x.id === item.id ? { ...x, ...item } : x))
        : [...list, { ...item, id: newId(kind[0]) }];
      return { ...s, [listKey]: nextList, modal: null } as AmanaState;
    });
  }, [setState]);

  const deleteItem = useCallback(
    (kind: EntityKind, id: string) => {
      setState((s) => {
        const listKey = listKeyOf[kind];
        const list = s[listKey] as { id: string }[];
        return { ...s, [listKey]: list.filter((x) => x.id !== id) } as AmanaState;
      });
    },
    [setState],
  );

  const generateInvite = useCallback(() => {
    setState((s) => ({
      ...s,
      inviteCode: "AMANA-" + Math.random().toString(36).slice(2, 7).toUpperCase(),
    }));
  }, [setState]);

  const setMoreSheetOpen = useCallback(
    (open: boolean) => setState((s) => ({ ...s, moreSheetOpen: open })),
    [setState],
  );

  const logout = useCallback(() => {
    setState(() => ({ ...initialState(), ready: true }));
  }, [setState]);

  const value = useMemo<AmanaContextValue>(
    () => ({
      ...state,
      today: MOCK_TODAY,
      sendChat,
      skipOnboardStep,
      resolveAction,
      startOnboarding,
      openModal,
      closeModal,
      updateModalField,
      saveModal,
      deleteItem,
      generateInvite,
      setMoreSheetOpen,
      logout,
    }),
    [
      state,
      sendChat,
      skipOnboardStep,
      resolveAction,
      startOnboarding,
      openModal,
      closeModal,
      updateModalField,
      saveModal,
      deleteItem,
      generateInvite,
      setMoreSheetOpen,
      logout,
    ],
  );

  return <AmanaContext.Provider value={value}>{children}</AmanaContext.Provider>;
}

export function useAmana(): AmanaContextValue {
  const ctx = useContext(AmanaContext);
  if (!ctx) throw new Error("useAmana harus dipakai di dalam <AmanaProvider>");
  return ctx;
}
