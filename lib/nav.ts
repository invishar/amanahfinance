// Peta tab aplikasi. Di prototipe ini state `appTab`; di sini jadi routing.

export interface NavItem {
  href:
    | "/chat"
    | "/dashboard"
    | "/wallets"
    | "/accounts"
    | "/income"
    | "/goals"
    | "/analysis"
    | "/settings";
  label: string;
  icon: string;
}

export const CHAT_NAV: NavItem = {
  href: "/chat",
  label: "Chat",
  icon: "message-circle",
};

/** Urutan sidebar desktop (tanpa Chat — Chat punya tombol pil sendiri). */
export const SIDEBAR_NAV: NavItem[] = [
  { href: "/dashboard", label: "Dashboard", icon: "home" },
  { href: "/wallets", label: "Wallets", icon: "wallet" },
  { href: "/accounts", label: "Akun", icon: "landmark" },
  { href: "/income", label: "Pemasukan", icon: "banknote" },
  { href: "/goals", label: "Target", icon: "target" },
  { href: "/analysis", label: "Analisa", icon: "bar-chart-2" },
  { href: "/settings", label: "Keluarga", icon: "users" },
];

/** Bottom tab bar: dua di kiri notch, satu di kanan, sisanya di bottom sheet. */
export const MOBILE_TABS_LEFT: NavItem[] = [
  { href: "/dashboard", label: "Dashboard", icon: "home" },
  { href: "/wallets", label: "Wallets", icon: "wallet" },
];

export const MOBILE_TABS_RIGHT: NavItem[] = [
  { href: "/goals", label: "Target", icon: "target" },
];

export const MORE_ITEMS: NavItem[] = [
  { href: "/accounts", label: "Akun", icon: "landmark" },
  { href: "/income", label: "Pemasukan", icon: "banknote" },
  { href: "/analysis", label: "Analisa", icon: "bar-chart-2" },
  { href: "/settings", label: "Keluarga", icon: "users" },
];
