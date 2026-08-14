"use client";

import { useEffect, useState } from "react";

import { ApiError } from "@/lib/api/client";
import { useSaveEntity } from "@/lib/api/hooks";
import { buildBody, ENTITY_FORMS } from "@/lib/entity-forms";
import { useUi, type ModalState } from "@/lib/ui-store";

export function CrudDialog() {
  const { modal } = useUi();
  if (!modal) return null;
  // Key per entitas: draft & error ikut ter-reset saat modal berganti isi.
  return <CrudDialogInner key={modal.kind + (modal.id ?? "new")} modal={modal} />;
}

function CrudDialogInner({ modal }: { modal: ModalState }) {
  const { closeModal, setDraftField } = useUi();
  const form = ENTITY_FORMS[modal.kind];
  const isEdit = Boolean(modal.id);
  const save = useSaveEntity(modal.kind);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") closeModal();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [closeModal]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFieldErrors({});
    setFormError(null);
    try {
      await save.mutateAsync({
        id: modal.id,
        body: buildBody(modal.kind, modal.draft, isEdit),
      });
      closeModal();
    } catch (error) {
      if (error instanceof ApiError) {
        const perField: Record<string, string> = {};
        for (const key of Object.keys(error.fieldErrors)) {
          const message = error.fieldMessage(key);
          if (message) perField[key] = message;
        }
        setFieldErrors(perField);
        if (Object.keys(perField).length === 0) setFormError(error.message);
      } else {
        setFormError("Terjadi kesalahan tak terduga.");
      }
    }
  };

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
        onSubmit={submit}
        role="dialog"
        aria-modal="true"
        aria-label={form.title}
      >
        <div className="dialog-title">{form.title}</div>

        {form.fields
          .filter((field) => !(isEdit && field.createOnly))
          .map((field) => {
            const id = `${modal.kind}-${field.name}`;
            const value = modal.draft[field.name] ?? "";
            return (
              <div className="field" key={field.name}>
                <label htmlFor={id}>{field.label}</label>
                {field.type === "select" ? (
                  <select
                    id={id}
                    className="input"
                    value={String(value)}
                    onChange={(e) => setDraftField(field.name, e.target.value)}
                  >
                    {field.options?.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                ) : (
                  <input
                    id={id}
                    className="input"
                    type={field.type === "number" ? "number" : field.type}
                    value={String(value)}
                    onChange={(e) =>
                      setDraftField(
                        field.name,
                        field.type === "number"
                          ? Number(e.target.value)
                          : e.target.value,
                      )
                    }
                  />
                )}
                {field.hint && (
                  <p className="text-muted" style={{ margin: "6px 0 0", fontSize: 11 }}>
                    {field.hint}
                  </p>
                )}
                {fieldErrors[field.name] && (
                  <p className="field-error">{fieldErrors[field.name]}</p>
                )}
              </div>
            );
          })}

        {formError && <p className="field-error">{formError}</p>}

        <div className="dialog-actions">
          <button type="button" className="btn btn-secondary" onClick={closeModal}>
            Batal
          </button>
          <button type="submit" className="btn btn-primary" disabled={save.isPending}>
            {save.isPending ? "Menyimpan…" : "Simpan"}
          </button>
        </div>
      </form>
    </div>
  );
}
