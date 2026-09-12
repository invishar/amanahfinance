import { useMemo, useRef, useState, type FormEvent } from "react";

import { Icon } from "@/components/icon";
import { Skeleton } from "@/components/ui";
import { useLlmSetting, useNineRouterModels, useUpdateLlmSetting, type LlmSetting, type NineRouterCatalog } from "@/lib/api/admin-hooks";
import { ApiError } from "@/lib/api/client";

export default function AdminLlmSettingsPage() {
  const settings = useLlmSetting();
  return <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-4)" }}>
    <h1 style={{ fontSize: 22, margin: 0 }}>Pengaturan LLM Amina</h1>
    {settings.isPending ? <Skeleton height={350} /> : settings.isError || !settings.data ?
      <div className="notice notice-danger">Pengaturan belum bisa dimuat. <button className="btn btn-secondary" onClick={() => settings.refetch()}>Coba lagi</button></div> :
      <LlmSettingForm initial={settings.data} />}
  </div>;
}

export function LlmSettingForm({ initial }: { initial: LlmSetting }) {
  const update = useUpdateLlmSetting();
  const discovery = useNineRouterModels();
  const [active, setActive] = useState(initial);
  const [gateway, setGateway] = useState<"direct" | "9router">(initial.gateway ?? "direct");
  const [provider, setProvider] = useState<"anthropic" | "openai_compatible">(initial.provider ?? "anthropic");
  const [mode, setMode] = useState<"model" | "combo">(initial.selection_mode ?? "model");
  const [model, setModel] = useState(initial.model ?? "");
  const [baseUrl, setBaseUrl] = useState(initial.base_url ?? "");
  const [key, setKey] = useState("");
  const [catalog, setCatalog] = useState<NineRouterCatalog | null>(null);
  const [providerFilter, setProviderFilter] = useState("");
  const [search, setSearch] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const revision = useRef(0);
  const busy = update.isPending || discovery.isPending;
  const models = catalog?.models ?? [];
  const providers = useMemo(() => [...new Set(models.filter(m => m.kind === "model").map(m => m.provider))].sort(), [models]);
  const options = models.filter(m => m.kind === mode && (mode === "combo" || !providerFilter || m.provider === providerFilter)
    && `${m.name} ${m.id}`.toLowerCase().includes(search.toLowerCase()));
  const selected = models.find(m => m.id === model && m.kind === mode);
  const canSave = Boolean(model.trim()) && (gateway !== "9router" || Boolean(selected && selected.supports_tools !== false));

  const changed = () => { setSaved(false); setErrors({}); setNotice(null); };
  const invalidateCatalog = () => { revision.current++; setCatalog(null); changed(); };
  const showError = (error: unknown) => {
    if (error instanceof ApiError) {
      const fields: Record<string, string> = {};
      for (const field of Object.keys(error.fieldErrors)) fields[field] = error.fieldMessage(field) ?? error.message;
      setErrors(fields);
      setNotice(Object.keys(fields).length ? null : error.message);
    } else setNotice("Permintaan gagal. Coba lagi.");
  };
  const loadCatalog = async () => {
    changed();
    const requestRevision = ++revision.current;
    setCatalog(null);
    try {
      const result = await discovery.mutateAsync({ base_url: baseUrl.trim(), ...(key.trim() ? { key: key.trim() } : {}) });
      if (requestRevision !== revision.current) return;
      setCatalog(result);
      setBaseUrl(result.base_url);
      setProviderFilter("");
      setSearch("");
    } catch (error) { if (requestRevision === revision.current) showError(error); }
  };
  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!canSave || busy) return;
    changed();
    try {
      const result = await update.mutateAsync({
        gateway, provider: gateway === "9router" ? "openai_compatible" : provider,
        selection_mode: gateway === "9router" ? mode : "model",
        model: model.trim(), base_url: baseUrl.trim() || null,
        ...(key.trim() ? { key: key.trim() } : {}),
      });
      setActive(result);
      setBaseUrl(result.base_url ?? "");
      setKey("");
      setSaved(true);
    } catch (error) { showError(error); }
  };
  const fieldError = (field: string) => errors[field] ? <p className="field-error">{errors[field]}</p> : null;

  return <form onSubmit={submit} className="card elev-sm" style={{ maxWidth: 760 }}>
    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
      <Icon name="sparkles" size={20} color="var(--color-accent)" />
      <div className="card-title">Koneksi dan model Amina</div>
    </div>
    <p className="card-body">Berlaku untuk seluruh keluarga. API key disimpan terenkripsi di server.</p>
    <div className="notice" style={{ marginBottom: 16 }}>
      <span>Aktif: <strong>{active.model ?? "Belum dipilih"}</strong> ? {active.gateway === "9router" ? `9Router / ${active.selection_mode === "combo" ? "Combo" : "Model"}` : active.provider === "anthropic" ? "Anthropic" : "OpenAI-compatible"}</span>
    </div>
    <fieldset disabled={update.isPending} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
      <div className="field">
        <label htmlFor="llm-gateway">Layanan LLM</label>
        <select id="llm-gateway" className="input" value={gateway} onChange={e => { setGateway(e.target.value as typeof gateway); invalidateCatalog(); }}>
          <option value="9router">9Router ? pilih combo atau provider</option>
          <option value="direct">Koneksi langsung ke provider</option>
        </select>
        {fieldError("gateway")}
      </div>
      {gateway === "direct" && <div className="field">
        <label htmlFor="llm-provider">Provider</label>
        <select id="llm-provider" className="input" value={provider} onChange={e => { setProvider(e.target.value as typeof provider); changed(); }}>
          <option value="anthropic">Anthropic (Claude)</option>
          <option value="openai_compatible">OpenAI-compatible</option>
        </select>{fieldError("provider")}
      </div>}
      <div className="field">
        <label htmlFor="llm-base-url">{gateway === "9router" ? "Alamat server 9Router" : "Base URL"}</label>
        <input id="llm-base-url" className="input" type="url" value={baseUrl} placeholder={gateway === "9router" ? "http://server:20128/v1" : "https://alamat-provider/v1"}
          required={gateway === "9router" || provider === "openai_compatible"} onChange={e => { setBaseUrl(e.target.value); invalidateCatalog(); }} />
        {gateway === "9router" && <p className="text-muted" style={{ fontSize: 12 }}>Gunakan alamat yang bisa dijangkau server aplikasi. URL utama maupun URL berakhiran /v1 bisa digunakan.</p>}
        {fieldError("base_url")}
      </div>
      <div className="field">
        <label htmlFor="llm-key">API key {active.has_key ? `(tersimpan: ${active.key_preview ?? "tersedia"})` : "(belum diatur)"}</label>
        <input id="llm-key" className="input" type="password" autoComplete="off" value={key} placeholder="Kosongkan untuk memakai key tersimpan pada koneksi yang sama"
          onChange={e => { setKey(e.target.value); invalidateCatalog(); }} />{fieldError("key")}
      </div>
      {gateway === "9router" ? <>
        <button type="button" className="btn btn-secondary" disabled={busy || !baseUrl.trim()} onClick={loadCatalog}>
          {discovery.isPending ? "Mengambil katalog 9Router..." : "Muat combo & model"}
        </button>
        <p className="text-muted" style={{ fontSize: 12 }}>Daftar diambil dari API 9Router. Memuat daftar belum mengubah model aktif Amina.</p>
        {catalog && <div role="status" className="notice">Terhubung. {models.filter(m => m.kind === "combo").length} combo dan {models.filter(m => m.kind === "model").length} model tersedia.</div>}
        <div className="field">
          <label htmlFor="llm-mode">Gunakan</label>
          <select id="llm-mode" className="input" value={mode} onChange={e => { setMode(e.target.value as typeof mode); setModel(""); setSearch(""); changed(); }}>
            <option value="combo">Combo 9Router</option><option value="model">Provider & model tertentu</option>
          </select>{fieldError("selection_mode")}
          <p className="text-muted" style={{ fontSize: 12 }}>{mode === "combo" ? "Amina memakai combo yang telah dibuat di 9Router, termasuk aturan fallback-nya." : "Pilih satu model dari provider yang muncul dalam katalog 9Router."}</p>
        </div>
        {mode === "model" && <div className="field">
          <label htmlFor="llm-provider-filter">Provider dari 9Router</label>
          <select id="llm-provider-filter" className="input" value={providerFilter} disabled={!catalog} onChange={e => { setProviderFilter(e.target.value); setModel(""); changed(); }}>
            <option value="">Semua provider</option>{providers.map(p => <option key={p} value={p}>{p}</option>)}
          </select>
        </div>}
        <div className="field"><label htmlFor="llm-search">Cari {mode === "combo" ? "combo" : "model"}</label>
          <input id="llm-search" className="input" value={search} disabled={!catalog} onChange={e => setSearch(e.target.value)} placeholder="Ketik nama untuk menyaring daftar" /></div>
        <div className="field">
          <label htmlFor="llm-model">{mode === "combo" ? "Combo" : "Model"}</label>
          <select id="llm-model" className="input" value={model} disabled={!catalog} onChange={e => { setModel(e.target.value); changed(); }}>
            <option value="">{catalog ? "Pilih dari katalog" : "Muat katalog terlebih dahulu"}</option>
            {model && !options.some(m => m.id === model) && <option value={model}>{model}{selected ? " (pilihan saat ini)" : " (belum tersedia dalam daftar)"}</option>}
            {options.map(m => <option key={m.id} value={m.id} disabled={m.supports_tools === false}>{m.name}{m.name !== m.id ? ` ? ${m.id}` : ""}{m.supports_tools === false ? " (tidak mendukung formulir Amina)" : ""}</option>)}
          </select>{fieldError("model")}
        </div>
        {catalog && options.length === 0 && <p className="notice">Tidak ada {mode === "combo" ? "combo" : "model"} yang sesuai. Ubah pencarian atau periksa konfigurasi provider/combo pada 9Router, lalu muat ulang.</p>}
        {model && catalog && !selected && <p className="field-error">Pilihan saat ini tidak ada pada daftar ini. Pilih kembali sebelum menyimpan.</p>}
      </> : <div className="field">
        <label htmlFor="llm-model">ID model</label><input id="llm-model" className="input" value={model} required onChange={e => { setModel(e.target.value); changed(); }} />{fieldError("model")}
      </div>}
      {notice && <p className="field-error" role="alert">{notice}</p>}
      {saved && <p role="status" style={{ color: "var(--color-accent-2)" }}>Tersimpan. Pesan Amina berikutnya menggunakan pilihan ini.</p>}
      <button type="submit" className="btn btn-primary" style={{ marginTop: 12 }} disabled={busy || !canSave}>
        {update.isPending ? "Memeriksa dan menyimpan..." : "Simpan pengaturan"}
      </button>
    </fieldset>
  </form>;
}
