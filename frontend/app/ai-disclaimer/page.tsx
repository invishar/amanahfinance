import type { Metadata } from "next";

import { LegalContact, LegalPage } from "@/components/legal-page";

export const metadata: Metadata = { title: "Tentang Amina | AmanaFinance" };

export default function AiDisclaimerPage() {
  return (
    <LegalPage
      eyebrow="Asisten AI"
      title="Tentang Amina"
      summary="Amina adalah asisten pencatatan keuangan keluarga, bukan pengganti pertimbangan Anda atau tenaga profesional."
    >
      <section>
        <h2>Cara Amina bekerja</h2>
        <p>
          Amina membaca percakapan dan data keluarga yang relevan agar dapat menjawab pertanyaan,
          membuat ringkasan, atau menyiapkan tindakan di aplikasi. Permintaan AI dapat diteruskan
          ke penyedia model melalui layanan perantara yang dikonfigurasi AmanaFinance. Jangan
          mengirim kata sandi, PIN, OTP, nomor kartu lengkap, atau rahasia perbankan lainnya.
        </p>
      </section>

      <section>
        <h2>Amina dapat keliru</h2>
        <p>
          AI dapat salah memahami pesan, memakai data yang belum diperbarui, atau menghasilkan
          jawaban yang kurang tepat. Periksa kembali nominal, tanggal, kategori, saldo, dan saran
          sebelum mengandalkannya. Untuk keputusan investasi, kredit, pajak, hukum, atau keputusan
          penting lain, pertimbangkan bantuan profesional yang sesuai.
        </p>
      </section>

      <section>
        <h2>Kontrol tetap di tangan pengguna</h2>
        <p>
          Amina dirancang untuk menyiapkan tindakan, sedangkan perubahan penting perlu ditinjau
          atau dikonfirmasi pengguna. Jika Amina sedang gagal atau tidak tersedia, seluruh fitur
          pencatatan manual tetap dapat digunakan untuk menambah, mengubah, dan menghapus data.
        </p>
      </section>

      <section>
        <h2>Batas topik</h2>
        <p>
          Amina berfokus pada keuangan pribadi dan keluarga, penggunaan AmanaFinance, serta topik
          ekonomi yang berdampak pada keputusan rumah tangga. Amina dapat menolak pertanyaan umum
          yang tidak berkaitan dengan ruang lingkup tersebut.
        </p>
      </section>

      <section>
        <h2>Laporkan masalah</h2>
        <p>Jika menemukan jawaban atau tindakan yang tidak wajar, hubungi <LegalContact />.</p>
      </section>
    </LegalPage>
  );
}
