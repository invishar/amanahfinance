// Query key selalu menyertakan familyId supaya cache tidak bocor antar family.

export const qk = {
  me: ["me"] as const,
  families: ["families"] as const,
  members: (familyId: string) => ["family-members", familyId] as const,
  wallets: (familyId: string) => ["wallets", familyId] as const,
  accounts: (familyId: string) => ["accounts", familyId] as const,
  incomeSources: (familyId: string) => ["income-sources", familyId] as const,
  savingsGoals: (familyId: string) => ["savings-goals", familyId] as const,
  transactions: (familyId: string) => ["transactions", familyId] as const,
  analytics: (familyId: string, month: string) =>
    ["analytics", familyId, month] as const,
  chatThreads: (familyId: string) => ["chat-threads", familyId] as const,
  messages: (threadId: string) => ["messages", threadId] as const,
  aiActions: (familyId: string) => ["ai-actions", familyId] as const,
  onboardingAnswers: (familyId: string) => ["onboarding-answers", familyId] as const,

  // Platform admin: lintas-family, tidak pernah butuh familyId.
  adminUsers: (search: string, subscriptionStatus: string, page: number) =>
    ["admin-users", search, subscriptionStatus, page] as const,
  adminUser: (id: string) => ["admin-user", id] as const,
  adminSubscriptions: (status: string, page: number) =>
    ["admin-subscriptions", status, page] as const,
  adminAiErrors: (status: string, model: string, page: number) =>
    ["admin-ai-errors", status, model, page] as const,
  adminAiLogs: (model: string, page: number) =>
    ["admin-ai-logs", model, page] as const,
  llmSetting: ["llm-setting"] as const,
};
