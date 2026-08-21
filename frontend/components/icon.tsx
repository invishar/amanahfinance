// Satu-satunya pintu masuk ikon. Set ikon: Lucide (jangan campur set lain).

import {
  AlertTriangle,
  ArrowDownLeft,
  ArrowUpRight,
  Banknote,
  BarChart2,
  Camera,
  Car,
  Check,
  ChevronLeft,
  ChevronRight,
  Copy,
  CreditCard,
  Eye,
  EyeOff,
  FileText,
  Film,
  Grid2x2,
  Home,
  Landmark,
  LogOut,
  MessageCircle,
  Mic,
  Pencil,
  Plus,
  Receipt,
  Search,
  Send,
  ShieldCheck,
  ShoppingCart,
  Smartphone,
  Sparkles,
  Target,
  Trash2,
  User,
  UserPlus,
  Users,
  Wallet,
  X,
  type LucideIcon,
} from "lucide-react";
import type { CSSProperties } from "react";

const ICONS = {
  "alert-triangle": AlertTriangle,
  "arrow-down-left": ArrowDownLeft,
  "arrow-up-right": ArrowUpRight,
  banknote: Banknote,
  "bar-chart-2": BarChart2,
  camera: Camera,
  car: Car,
  check: Check,
  "chevron-left": ChevronLeft,
  "chevron-right": ChevronRight,
  copy: Copy,
  "credit-card": CreditCard,
  eye: Eye,
  "eye-off": EyeOff,
  "file-text": FileText,
  film: Film,
  "grid-2x2": Grid2x2,
  home: Home,
  landmark: Landmark,
  "log-out": LogOut,
  "message-circle": MessageCircle,
  mic: Mic,
  pencil: Pencil,
  plus: Plus,
  receipt: Receipt,
  search: Search,
  send: Send,
  "shield-check": ShieldCheck,
  "shopping-cart": ShoppingCart,
  smartphone: Smartphone,
  sparkles: Sparkles,
  target: Target,
  "trash-2": Trash2,
  user: User,
  "user-plus": UserPlus,
  users: Users,
  wallet: Wallet,
  x: X,
} satisfies Record<string, LucideIcon>;

export type IconName = keyof typeof ICONS;

export function isIconName(name: string): name is IconName {
  return name in ICONS;
}

interface IconProps {
  name: string;
  size?: number;
  color?: string;
  style?: CSSProperties;
  className?: string;
  "aria-hidden"?: boolean;
}

export function Icon({
  name,
  size = 16,
  color,
  style,
  className,
  ...rest
}: IconProps) {
  const Cmp = isIconName(name) ? ICONS[name] : Target;
  return (
    <Cmp
      width={size}
      height={size}
      color={color ?? "currentColor"}
      style={{ flex: "none", ...style }}
      className={className}
      aria-hidden
      {...rest}
    />
  );
}
