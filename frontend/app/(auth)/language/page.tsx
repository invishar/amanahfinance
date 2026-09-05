"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { AuthHeader } from "@/components/auth-header";
import { Icon } from "@/components/icon";
import { useActiveFamily } from "@/lib/api/hooks";
import { useSession } from "@/lib/auth";
import { type Locale, useLanguage } from "@/lib/i18n";

const OPTIONS: Array<{
  locale: Locale;
  flag: string;
  name: string;
  description: string;
}> = [
  {
    locale: "id",
    flag: "ID",
    name: "Bahasa Indonesia",
    description: "Gunakan AmanaFinance dan bicara dengan Amina dalam Bahasa Indonesia.",
  },
  {
    locale: "en",
    flag: "EN",
    name: "English",
    description: "Use AmanaFinance and talk with Amina in English.",
  },
];

export default function LanguagePage() {
  const router = useRouter();
  const { status } = useSession();
  const { familyId, isLoading: familyLoading } = useActiveFamily();
  const { locale, savedLocale, loading, setLocale } = useLanguage();
  const [selected, setSelected] = useState<Locale>(locale);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (status === "anonymous") router.replace("/login");
  }, [status, router]);

  const save = async () => {
    setPending(true);
    setError(null);
    try {
      await setLocale(selected);
      router.replace(familyId ? "/chat" : "/onboarding");
    } catch {
      setError(selected === "en" ? "Couldn't save your language. Please try again." : "Bahasa belum berhasil disimpan. Coba lagi.");
      setPending(false);
    }
  };

  if (status !== "authenticated" || loading || familyLoading) {
    return <div className="amana-auth-shell"><div className="amana-brand-circle">AF</div></div>;
  }

  return (
    <main className="amana-auth-shell">
      <div className="language-card">
        <AuthHeader
          title={savedLocale ? "Bahasa aplikasi" : "Pilih bahasa"}
          subtitle={savedLocale ? "Ubah bahasa tampilan dan jawaban Amina" : "Choose the language you want to use"}
        />

        <div className="language-options" role="radiogroup" aria-label="Pilihan bahasa">
          {OPTIONS.map((option) => (
            <button
              key={option.locale}
              type="button"
              className="language-option"
              data-active={selected === option.locale}
              role="radio"
              aria-checked={selected === option.locale}
              onClick={() => setSelected(option.locale)}
            >
              <span className="language-code" data-no-translate>{option.flag}</span>
              <span>
                <strong>{option.name}</strong>
                <small>{option.description}</small>
              </span>
              {selected === option.locale && <Icon name="check" size={20} />}
            </button>
          ))}
        </div>

        {error && <p className="field-error">{error}</p>}
        <button type="button" className="btn btn-primary btn-block" onClick={save} disabled={pending}>
          {pending ? (selected === "en" ? "Saving…" : "Menyimpan…") : (selected === "en" ? "Continue" : "Lanjutkan")}
        </button>
      </div>
    </main>
  );
}
