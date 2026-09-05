import type { Metadata } from "next";

import { LegalContact, LegalPage } from "@/components/legal-page";

export const metadata: Metadata = { title: "Syarat & Ketentuan | AmanaFinance" };

export default function TermsPage() {
  return (
    <LegalPage
      eyebrow="Ketentuan layanan"
      title="Syarat & Ketentuan"
      summary="Ketentuan singkat ini mengatur penggunaan AmanaFinance sebagai platform pencatatan keuangan keluarga."
    >
      <section>
        <h2>Layanan AmanaFinance</h2>
        <p>
          AmanaFinance membantu pengguna mencatat dan meninjau keuangan keluarga, menyusun
          anggaran dan target, serta memakai asisten Amina. AmanaFinance bukan bank, dompet
          elektronik, penyelenggara pembayaran, penasihat investasi, konsultan pajak, atau
          penasihat hukum. Kami tidak menyimpan dana dan tidak menjalankan transfer uang.
        </p>
      </section>

      <section>
        <h2>Akun dan tanggung jawab pengguna</h2>
        <p>
          Anda wajib memberikan informasi yang wajar dan menjaga kerahasiaan akses akun. Aktivitas
          yang dilakukan melalui akun Anda menjadi tanggung jawab Anda, kecuali terbukti terjadi
          gangguan pada sistem kami. Laporkan akses tanpa izin sesegera mungkin.
        </p>
        <p>
          Anda bertanggung jawab memastikan memiliki hak untuk memasukkan data anggota keluarga,
          transaksi, catatan, atau file ke platform. Dilarang memakai layanan untuk kegiatan
          melawan hukum, merusak sistem, mencoba mengakses data keluarga lain, atau mengunggah
          konten berbahaya.
        </p>
      </section>

      <section>
        <h2>Langganan dan pembayaran</h2>
        <p>
          Jika paket berbayar tersedia, harga, masa aktif, fitur, dan cara pembayaran akan
          ditampilkan sebelum pembelian. Biaya yang sudah dibayar pada dasarnya tidak dapat
          dikembalikan setelah masa layanan berjalan, kecuali layanan gagal kami berikan atau
          peraturan yang berlaku menentukan lain. Perubahan harga hanya berlaku ke periode
          berikutnya setelah pemberitahuan.
        </p>
      </section>

      <section>
        <h2>Ketersediaan dan perubahan</h2>
        <p>
          Kami berusaha menjaga layanan tetap aman dan tersedia, tetapi pemeliharaan, koneksi,
          penyedia pihak ketiga, atau keadaan di luar kendali dapat menimbulkan gangguan. Fitur
          dapat diperbaiki, ditambah, dibatasi, atau dihentikan dengan pemberitahuan yang wajar
          jika perubahan berdampak besar bagi pengguna.
        </p>
      </section>

      <section>
        <h2>Keputusan keuangan dan batas tanggung jawab</h2>
        <p>
          Ringkasan, perhitungan, dan jawaban Amina merupakan alat bantu berdasarkan data yang
          tersedia, bukan jaminan hasil atau nasihat profesional. Anda tetap perlu memeriksa
          catatan dan mengambil keputusan sendiri. Sepanjang diizinkan hukum, tanggung jawab kami
          terbatas pada kerugian langsung yang terbukti timbul karena kesalahan layanan kami.
        </p>
      </section>

      <section>
        <h2>Pengakhiran dan hukum yang berlaku</h2>
        <p>
          Anda dapat berhenti memakai layanan dan meminta penghapusan akun. Kami dapat membatasi
          akun yang melanggar ketentuan atau mengancam keamanan, dengan pemberitahuan bila
          memungkinkan. Ketentuan ini tunduk pada hukum Republik Indonesia. Perselisihan
          diupayakan selesai lebih dahulu melalui musyawarah.
        </p>
      </section>

      <section>
        <h2>Kontak</h2>
        <p>Pertanyaan, keluhan, atau permintaan bantuan dapat dikirim ke <LegalContact />.</p>
      </section>
    </LegalPage>
  );
}
