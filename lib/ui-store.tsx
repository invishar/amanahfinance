"use client";

// State yang benar-benar milik klien: modal CRUD, bottom sheet, draft form.
// Data server TIDAK tinggal di sini — itu urusan TanStack Query (lib/api/hooks).

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import type { EntityKind } from "@/lib/api/hooks";
import { ENTITY_FORMS, type Draft, type DraftValue } from "@/lib/entity-forms";

export interface ModalState {
  kind: EntityKind;
  /** Ada = sedang mengubah; kosong = sedang menambah. */
  id?: string;
  draft: Draft;
}

interface UiValue {
  modal: ModalState | null;
  openModal: (kind: EntityKind, entity?: Record<string, unknown>) => void;
  closeModal: () => void;
  setDraftField: (name: string, value: DraftValue) => void;
  moreSheetOpen: boolean;
  setMoreSheetOpen: (open: boolean) => void;
}

const UiContext = createContext<UiValue | null>(null);

export function UiProvider({ children }: { children: ReactNode }) {
  const [modal, setModal] = useState<ModalState | null>(null);
  const [moreSheetOpen, setMoreSheetOpen] = useState(false);

  const openModal = useCallback(
    (kind: EntityKind, entity?: Record<string, unknown>) => {
      setModal({
        kind,
        id: typeof entity?.id === "string" ? entity.id : undefined,
        draft: ENTITY_FORMS[kind].toDraft(entity),
      });
    },
    [],
  );

  const closeModal = useCallback(() => setModal(null), []);

  const setDraftField = useCallback((name: string, value: DraftValue) => {
    setModal((prev) =>
      prev ? { ...prev, draft: { ...prev.draft, [name]: value } } : prev,
    );
  }, []);

  const value = useMemo<UiValue>(
    () => ({
      modal,
      openModal,
      closeModal,
      setDraftField,
      moreSheetOpen,
      setMoreSheetOpen,
    }),
    [modal, openModal, closeModal, setDraftField, moreSheetOpen],
  );

  return <UiContext.Provider value={value}>{children}</UiContext.Provider>;
}

export function useUi(): UiValue {
  const ctx = useContext(UiContext);
  if (!ctx) throw new Error("useUi harus dipakai di dalam <UiProvider>");
  return ctx;
}
