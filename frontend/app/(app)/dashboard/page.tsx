"use client";

import { useRouter } from "next/navigation";
import { useMemo } from "react";

import { Icon } from "@/components/icon";
import { EmptyState, ProgressBar, Skeleton, SkeletonList } from "@/components/ui";
import {
  useAccounts,
  useAnalytics,
  useIncomeSources,
  useSavingsGoals,
  useTransactions,
  useWallets,
} from "@/lib/api/hooks";
import { useMe } from "@/lib/auth";
import { firstName, formatDayDateID, formatRupiah, initials } from "@/lib/format";
import {
  accountsView,
  goalsView,
  recentTransactions,
  sortBySpent,
  totalBalance,
  walletsView,
} from "@/lib/selectors";

export default function DashboardPage() {
  const router = useRouter();
  const me = useMe();
  const accounts = useAccounts();
  const analytics = useAnalytics();
  const wallets = useWallets();
  const goals = useSavingsGoals();
  const transactions = useTransactions();
  const incomeSources = useIncomeSources();

  // API tidak punya `GET /dashboard`; layar ini dirakit dari beberapa query.
  const accountList = useMemo(
    () => accountsView(accounts.data ?? []),
    [accounts.data],
  );
  const bars = useMemo(
    () => sortBySpent(walletsView(wallets.data ?? [], analytics.data)),
    [wallets.data, analytics.data],
  );
  const goalList = useMemo(() => goalsView(goals.data ?? []), [goals.data]);
  const recent = useMemo(
    () =>
      recentTransactions(
        transactions.data ?? [],
        wallets.data ?? [],
        incomeSources.data ?? [],
      ),
    [transactions.data, wallets.data, incomeSources.data],
  );

  const userName = me.data?.full_name ?? "";
  const goToChat = () => router.push("/chat");

  return (
    <div className="amana-container" style={{ gap: "var(--space-5)" }}>
      <div
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
        }}
      >
        <div>
          <div className="text-muted" style={{ fontSize: 13 }}>
            {formatDayDateID(new Date().toISOString().slice(0, 10))}
          </div>
          <h1 style={{ fontSize: 26, margin: "2px 0 0" }}>
            Halo{userName ? `, ${firstName(userName)}` : ""}
          </h1>
        </div>
        <div
          style={{
            width: 44,
            height: 44,
            borderRadius: "50%",
            background: "var(--color-accent)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            color: "#fff",
            fontFamily: "var(--font-heading)",
            fontWeight: 700,
            fontSize: 16,
            flex: "none",
          }}
        >
          {userName ? initials(userName) : ""}
        </div>
      </div>

      {/* Hero saldo */}
      {accounts.isPending ? (
        <Skeleton height={196} style={{ borderRadius: "var(--radius-lg)" }} />
      ) : (
        <div
          style={{
            borderRadius: "var(--radius-lg)",
            padding: "var(--space-5)",
            background:
              "linear-gradient(135deg, var(--color-accent-500), var(--color-accent-2-500))",
            color: "#fff",
            display: "flex",
            flexDirection: "column",
            gap: 16,
            boxShadow: "var(--shadow-md)",
            overflow: "hidden",
          }}
        >
          <div
            style={{
              fontSize: 11,
              letterSpacing: "0.1em",
              textTransform: "uppercase",
              opacity: 0.85,
            }}
          >
            Total Saldo
          </div>
          <div
            style={{
              fontFamily: "var(--font-heading)",
              fontWeight: 600,
              fontSize: 32,
              letterSpacing: "-0.01em",
              wordBreak: "break-word",
            }}
          >
            {formatRupiah(totalBalance(accounts.data ?? []))}
          </div>
          {accountList.length === 0 ? (
            <div style={{ fontSize: 13, opacity: 0.9 }}>
              Belum ada akun — tambahkan di menu Akun.
            </div>
          ) : (
            <div style={{ display: "flex", flexWrap: "wrap", gap: 10 }}>
              {accountList.map((a) => (
                <div
                  key={a.id}
                  style={{
                    flex: "1 1 120px",
                    minWidth: 0,
                    background: "rgba(255,255,255,0.18)",
                    borderRadius: "var(--radius-md)",
                    padding: "10px 12px",
                    display: "flex",
                    flexDirection: "column",
                    gap: 4,
                  }}
                >
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: 6,
                      fontSize: 12,
                      opacity: 0.9,
                      whiteSpace: "nowrap",
                      overflow: "hidden",
                      textOverflow: "ellipsis",
                    }}
                  >
                    <Icon name={a.typeIcon} size={13} />
                    <span style={{ overflow: "hidden", textOverflow: "ellipsis" }}>
                      {a.name}
                    </span>
                  </div>
                  <div
                    style={{
                      fontFamily: "var(--font-heading)",
                      fontSize: 14,
                      fontWeight: 600,
                      whiteSpace: "nowrap",
                      overflow: "hidden",
                      textOverflow: "ellipsis",
                    }}
                  >
                    {a.balanceLabel}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Pengeluaran per wallet */}
      <div>
        <div className="card-title" style={{ marginBottom: 10 }}>
          Pengeluaran per Wallet
        </div>
        {wallets.isPending || analytics.isPending ? (
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(2, 1fr)",
              gap: 10,
            }}
          >
            <SkeletonList count={4} height={104} />
          </div>
        ) : bars.length === 0 ? (
          <EmptyState
            message="Belum ada wallet. Bikin kantong anggaran pertama lewat obrolan sama Amina."
            actionLabel="Catat lewat chat"
            onAction={goToChat}
          />
        ) : (
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(2, 1fr)",
              gap: 10,
            }}
          >
            {bars.map((w) => (
              <div
                key={w.id}
                className="card elev-sm"
                style={{ gap: 8, padding: "var(--space-3)" }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <div
                    style={{
                      width: 30,
                      height: 30,
                      borderRadius: "50%",
                      background: "var(--color-accent-100)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      flex: "none",
                    }}
                  >
                    <Icon name={w.icon} size={15} color="var(--color-accent-700)" />
                  </div>
                  <div style={{ fontSize: 13, fontWeight: 600 }}>{w.name}</div>
                </div>
                <ProgressBar pct={w.percent} height={6} color={w.barColor} />
                <div className="text-muted" style={{ fontSize: 12 }}>
                  {w.spentLabel}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Target tabungan */}
      <div className="card elev-sm">
        <div className="card-title">Target Tabungan</div>
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: 14,
            marginTop: 8,
          }}
        >
          {goals.isPending ? (
            <SkeletonList count={3} height={44} />
          ) : goalList.length === 0 ? (
            <p className="text-muted" style={{ margin: 0, fontSize: 13 }}>
              Belum ada target tabungan.
            </p>
          ) : (
            goalList.map((g) => (
              <div
                key={g.id}
                style={{ display: "flex", alignItems: "center", gap: 12 }}
              >
                <div
                  style={{
                    width: 44,
                    height: 44,
                    borderRadius: "50%",
                    background: `conic-gradient(var(--color-accent-500) ${g.percent}%, var(--color-neutral-200) 0)`,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    flex: "none",
                  }}
                >
                  <div
                    style={{
                      width: 34,
                      height: 34,
                      borderRadius: "50%",
                      background: "var(--color-surface)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      fontSize: 10,
                      fontWeight: 700,
                    }}
                  >
                    {g.percent}%
                  </div>
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 600 }}>{g.name}</div>
                  <div className="text-muted" style={{ fontSize: 12 }}>
                    {g.currentLabel} / {g.targetLabel}
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Transaksi terbaru */}
      <div className="card elev-sm">
        <div className="card-title">Transaksi Terbaru</div>
        <div style={{ display: "flex", flexDirection: "column", marginTop: 6 }}>
          {transactions.isPending ? (
            <SkeletonList count={6} height={54} />
          ) : recent.length === 0 ? (
            <p className="text-muted" style={{ margin: "8px 0 0", fontSize: 13 }}>
              Belum ada transaksi — ceritakan pengeluaran pertamamu ke Amina.
            </p>
          ) : (
            recent.map((t) => (
              <div
                key={t.id}
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: 12,
                  padding: "10px 0",
                  borderBottom: "1px solid var(--color-divider)",
                }}
              >
                <div
                  style={{
                    width: 34,
                    height: 34,
                    borderRadius: "50%",
                    background: t.iconBg,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    flex: "none",
                  }}
                >
                  <Icon name={t.icon} size={16} color={t.iconColor} />
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13 }}>{t.note}</div>
                  <div className="text-muted" style={{ fontSize: 11 }}>
                    {t.walletName} · {t.dateLabel}
                  </div>
                </div>
                <div
                  style={{
                    fontSize: 13,
                    fontFamily: "var(--font-heading)",
                    color: t.iconColor,
                    whiteSpace: "nowrap",
                  }}
                >
                  {t.amountLabel}
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
