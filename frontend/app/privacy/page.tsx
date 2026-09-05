import type { Metadata } from "next";

import { LegalContact, LegalPage } from "@/components/legal-page";

export const metadata: Metadata = { title: "Kebijakan Privasi | AmanaFinance" };

export default function PrivacyPage() {
  return (
    <LegalPage
      eyebrow="Privasi"
      title="Kebijakan Privasi"
      summary="Catatan keuangan keluarga bersifat pribadi. Kami menggunakannya hanya untuk menyediakan dan menjaga layanan AmanaFinance."
    >
      <section>
        <h2>Data yang kami proses</h2>
        <p>
          Kami memproses data akun seperti nama, email atau nomor telepon; data keluarga dan
          anggota; serta catatan yang Anda masukkan seperti akun keuangan, transaksi, anggaran,
          sumber pemasukan, target tabungan, dan aturan rutin.
        </p>
        <p>
          Jika Anda memakai Amina atau fitur unggahan, kami juga memproses isi percakapan dan
          file yang sengaja Anda kirim, misalnya foto struk atau rekaman suara. Sistem dapat
          mencatat informasi teknis seperlunya, seperti alamat IP, sesi, waktu akses, dan log
          kesalahan untuk keamanan serta perbaikan layanan.
        </p>
      </section>

      <section>
        <h2>Untuk apa data digunakan</h2>
        <p>Data digunakan untuk:</p>
        <ul>
          <li>membuat dan mengamankan akun;</li>
          <li>menampilkan, menghitung, dan menyinkronkan catatan keuangan keluarga;</li>
          <li>menjalankan Amina sesuai permintaan Anda;</li>
          <li>menangani bantuan, gangguan, penyalahgunaan, dan kewajiban hukum; serta</li>
          <li>memperbaiki kinerja dan keandalan platform.</li>
        </ul>
        <p>
          Kami tidak menjual data pribadi atau catatan keuangan Anda dan tidak menggunakannya
          untuk iklan tertarget.
        </p>
      </section>

      <section>
        <h2>Akses keluarga dan penyedia layanan</h2>
        <p>
          Data dalam satu ruang keluarga dapat dilihat atau dikelola anggota lain sesuai peran
          yang diberikan. Pastikan Anda hanya mengundang orang yang dipercaya.
        </p>
        <p>
          Agar aplikasi berjalan, data dapat diproses oleh penyedia infrastruktur hosting,
          penyimpanan, keamanan, dan penyedia model AI yang dikonfigurasi AmanaFinance. Kami
          membatasi pemrosesan tersebut pada kebutuhan penyediaan layanan. Pemrosesan tertentu
          dapat berlangsung pada server di luar Indonesia, bergantung pada penyedia yang aktif.
        </p>
      </section>

      <section>
        <h2>Penyimpanan dan keamanan</h2>
        <p>
          Data disimpan selama akun digunakan atau selama diperlukan untuk menyediakan layanan,
          menyelesaikan sengketa, menjaga keamanan, dan memenuhi kewajiban hukum. Kami menerapkan
          perlindungan teknis dan akses terbatas, tetapi tidak ada sistem internet yang sepenuhnya
          bebas risiko. Jangan masukkan PIN, kata sandi bank, kode OTP, atau nomor kartu lengkap.
        </p>
      </section>

      <section>
        <h2>Pilihan dan hak Anda</h2>
        <p>
          Anda dapat melihat, memperbaiki, menambah, dan menghapus banyak catatan langsung dari
          aplikasi. Untuk meminta salinan, koreksi, penghapusan akun/data, menarik persetujuan,
          atau menyampaikan keberatan, hubungi kami. Kami dapat meminta verifikasi identitas dan
          tetap menyimpan data tertentu apabila diwajibkan hukum atau diperlukan untuk keamanan.
        </p>
      </section>

      <section>
        <h2>Hubungi kami</h2>
        <p>
          Pertanyaan atau permintaan terkait privasi dapat dikirim ke <LegalContact />.
        </p>
      </section>
    </LegalPage>
  );
}
