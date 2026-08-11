import { redirect } from "next/navigation";

export default function Home() {
  // Belum ada sesi: pintu masuk aplikasi adalah layar login.
  redirect("/login");
}
