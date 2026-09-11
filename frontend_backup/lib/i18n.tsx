"use client";

import { useQueryClient } from "@tanstack/react-query";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import { api, type Schemas } from "@/lib/api/client";
import { useMe, useSession } from "@/lib/auth";
import { qk } from "@/lib/api/keys";

export type Locale = "id" | "en";
export type UserWithLocale = Schemas["User"] & { locale?: Locale | null };

const STORAGE_KEY = "amanafinance-locale";

// Kamus memakai teks Indonesia yang sudah ada sebagai key. Dengan begitu
// halaman lama tetap menjadi sumber/fallback yang mudah dibaca dan setiap
// teks dinamis yang muncul setelah request API ikut diterjemahkan.
const EN: Record<string, string> = {
  "Keuangan keluarga": "Family finances",
  "Asisten keuangan keluarga yang ngerti obrolan sehari-hari": "A family finance assistant that understands everyday conversations",
  "Masuk": "Sign in",
  "Keluar": "Sign out",
  "Daftar": "Sign up",
  "Mendaftar…": "Signing up…",
  "Belum punya akun?": "Don't have an account?",
  "Sudah punya akun?": "Already have an account?",
  "Buat akun baru": "Create a new account",
  "Mulai catat keuangan keluarga bareng-bareng": "Start tracking your family finances together",
  "Nama lengkap": "Full name",
  "Email": "Email",
  "Kata sandi": "Password",
  "Tampilkan kata sandi": "Show password",
  "Sembunyikan kata sandi": "Hide password",
  "Saya menyetujui": "I agree to the",
  "dan telah membaca": "and have read the",
  "Kebijakan Privasi": "Privacy Policy",
  "Syarat & Ketentuan": "Terms & Conditions",
  "Tentang Amina": "About Amina",
  "Beranda": "Home",
  "Transaksi": "Transactions",
  "Anggaran": "Budgets",
  "Akun": "Accounts",
  "Pemasukan": "Income",
  "Target": "Goals",
  "Analisa": "Insights",
  "Keluarga": "Family",
  "Lainnya": "More",
  "Chat dengan Amina": "Chat with Amina",
  "Pengaturan Keluarga": "Family Settings",
  "Anggota": "Members",
  "Admin": "Admin",
  "Pengamat": "Viewer",
  "Undang Anggota Keluarga": "Invite Family Member",
  "Email yang diundang": "Invitee email",
  "Peran": "Role",
  "Buat undangan": "Create invitation",
  "Mengirim…": "Sending…",
  "Batal": "Cancel",
  "Tersalin": "Copied",
  "Salin kode undangan": "Copy invitation code",
  "Informasi & kebijakan": "Information & policies",
  "Pelajari cara data dan fitur Amina digunakan di AmanaFinance.": "Learn how your data and Amina are used in AmanaFinance.",
  "Bahasa aplikasi": "App language",
  "Bahasa Indonesia": "Indonesian",
  "English": "English",
  "Simpan bahasa": "Save language",
  "Menyimpan…": "Saving…",
  "Tempat uang": "Money accounts",
  "Akun & e-wallet": "Accounts & e-wallets",
  "Simpan saldo bank, uang tunai, dan dompet digital keluarga dalam satu tempat.": "Keep family bank, cash, and e-wallet balances in one place.",
  "Tambah akun": "Add account",
  "Belum ada akun": "No accounts yet",
  "Tambahkan tempat uangmu berada untuk melihat total saldo keluarga.": "Add where your money is kept to see the family's total balance.",
  "Gagal memuat akun. Coba muat ulang halaman.": "Couldn't load accounts. Please reload the page.",
  "Sumber pemasukan": "Income sources",
  "Kelola sumber penghasilan rutin maupun tambahan keluarga.": "Manage your family's regular and additional income sources.",
  "Tambah pemasukan": "Add income source",
  "Belum ada sumber pemasukan": "No income sources yet",
  "Tambahkan sumber penghasilan agar arus kas lebih mudah dipantau.": "Add an income source to track cash flow more easily.",
  "Gagal memuat sumber pemasukan.": "Couldn't load income sources.",
  "Rencana belanja": "Spending plans",
  "Wallet & anggaran": "Wallets & budgets",
  "Bagi pengeluaran ke pos yang jelas dan pantau batas bulanannya.": "Organize spending into clear categories and track monthly limits.",
  "Tambah wallet": "Add wallet",
  "Belum ada wallet": "No wallets yet",
  "Buat pos anggaran pertama untuk mulai mengendalikan pengeluaran.": "Create your first budget category to start managing spending.",
  "Gagal memuat wallet.": "Couldn't load wallets.",
  "Terpakai": "Spent",
  "dari": "of",
  "Rencana masa depan": "Future plans",
  "Target tabungan": "Savings goals",
  "Pantau progres dana darurat, pendidikan, liburan, dan tujuan keluarga lainnya.": "Track emergency funds, education, vacations, and other family goals.",
  "Tambah target": "Add goal",
  "Belum ada target": "No goals yet",
  "Buat target pertama agar rencana keluarga terasa lebih nyata.": "Create your first goal to make family plans more tangible.",
  "Gagal memuat target tabungan.": "Couldn't load savings goals.",
  "terkumpul": "saved",
  "Riwayat keuangan": "Financial history",
  "Semua transaksi": "All transactions",
  "Catat, periksa, dan koreksi pemasukan serta pengeluaran tanpa bergantung pada AI.": "Record, review, and correct income and expenses without relying on AI.",
  "Tambah transaksi": "Add transaction",
  "Belum ada transaksi": "No transactions yet",
  "Catat transaksi pertama secara manual atau melalui Amina.": "Record your first transaction manually or through Amina.",
  "Gagal memuat transaksi.": "Couldn't load transactions.",
  "Semua": "All",
  "Pengeluaran": "Expense",
  "Transfer": "Transfer",
  "Tabungan": "Savings",
  "Catat transaksi": "Record transaction",
  "Atur anggaran": "Manage budgets",
  "Catat manual": "Record manually",
  "Kontrol manual selalu tersedia": "Manual controls are always available",
  "Amina membantu, tetapi pencatatan tidak bergantung pada AI.": "Amina helps, but record keeping never depends on AI.",
  "Amina sedang sulit dihubungi. Semua fitur tetap bisa dipakai secara manual.": "Amina is currently unavailable. All features still work manually.",
  "Tulis pesan ke Amina...": "Write a message to Amina...",
  "Pesan untuk Amina": "Message for Amina",
  "Kirim": "Send",
  "Kirim foto struk": "Send receipt photo",
  "Rekam suara": "Record voice",
  "Amina sedang mengetik": "Amina is typing",
  "Terkirim": "Sent",
  "Dibaca Amina": "Read by Amina",
  "Belum ada percakapan": "No conversations yet",
  "Mulai ngobrol dengan Amina tentang keuangan keluargamu.": "Start talking with Amina about your family finances.",
  "Asisten keuangan": "Financial assistant",
  "Ringkasan keluarga": "Family overview",
  "Total saldo": "Total balance",
  "Bulan ini": "This month",
  "Pemasukan bulan ini": "Income this month",
  "Pengeluaran bulan ini": "Expenses this month",
  "Arus kas": "Cash flow",
  "Aksi cepat": "Quick actions",
  "Transaksi terbaru": "Recent transactions",
  "Lihat semua": "View all",
  "Belum ada aktivitas": "No activity yet",
  "Mulai catat transaksi untuk melihat ringkasan keuangan keluarga di sini.": "Start recording transactions to see your family's financial summary here.",
  "Gagal memuat ringkasan keuangan.": "Couldn't load the financial summary.",
  "Kondisi keuangan": "Financial health",
  "Analisis keuangan": "Financial insights",
  "Lihat gambaran arus kas dan penggunaan anggaran keluarga bulan ini.": "See your family's cash flow and budget usage this month.",
  "Belum cukup data": "Not enough data yet",
  "Tambahkan transaksi dan anggaran agar analisis dapat ditampilkan.": "Add transactions and budgets to view insights.",
  "Aman": "On track",
  "Hampir habis": "Nearly spent",
  "Lewat budget": "Over budget",
  "Tanpa budget": "No budget",
  "Tunai": "Cash",
  "Bank": "Bank",
  "Dompet digital": "E-wallet",
  "Milik bersama": "Shared",
  "Pribadi": "Personal",
  "Aktif": "Active",
  "Selesai": "Completed",
  "Dijeda": "Paused",
  "Tambah": "Add",
  "Ubah": "Edit",
  "Hapus": "Delete",
  "Simpan": "Save",
  "Menyimpan": "Saving",
  "Konfirmasi": "Confirm",
  "Tolak": "Reject",
  "Edit dulu": "Edit first",
  "Yakin ingin menghapus?": "Are you sure you want to delete this?",
  "Tindakan ini tidak dapat dibatalkan.": "This action cannot be undone.",
  "Terjadi kesalahan tak terduga.": "An unexpected error occurred.",
  "Tidak bisa menghubungi server. Cek koneksi kamu.": "Couldn't reach the server. Check your connection.",
  "Terjadi kesalahan di server.": "A server error occurred.",
  "Wajib diisi.": "Required.",
  "Format email tidak valid.": "Invalid email format.",
  "Sudah dipakai, coba nama lain.": "Already in use, try another one.",
  "Konfirmasi kata sandi tidak cocok.": "Password confirmation does not match.",
  "Terlalu pendek.": "Too short.",
  "Terlalu panjang.": "Too long.",
  "Nilainya terlalu kecil.": "Value is too small.",
  "Harus berupa angka bulat.": "Must be a whole number.",
  "Tanggal tidak valid.": "Invalid date.",
  "Pilihan tidak valid.": "Invalid option.",
  "Data tidak ditemukan.": "Data not found.",
  "Isian ini belum benar.": "This value is not valid.",
  "Kembali ke aplikasi": "Back to app",
  "Terakhir diperbarui:": "Last updated:",
  "Privasi": "Privacy",
  "Ketentuan layanan": "Service terms",
  "Asisten AI": "AI assistant",
  "Pilih bahasa": "Choose your language",
  "Ubah bahasa tampilan dan jawaban Amina": "Change the interface and Amina's response language",
  "Choose the language you want to use": "Choose the language you want to use",
  "Pilihan bahasa": "Language options",
  "Gunakan AmanaFinance dan bicara dengan Amina dalam Bahasa Indonesia.": "Use AmanaFinance and talk with Amina in Indonesian.",
  "Bahasa tampilan aplikasi dan jawaban Amina mengikuti pilihan ini.": "The app interface and Amina's responses use this language.",
  "Bahasa berhasil disimpan.": "Language saved.",
  "Lanjutkan": "Continue",
  "Cara Amina bekerja": "How Amina works",
  "Amina dapat keliru": "Amina can make mistakes",
  "Kontrol tetap di tangan pengguna": "You remain in control",
  "Batas topik": "Topic limits",
  "Laporkan masalah": "Report an issue",
  "Data yang kami proses": "Data we process",
  "Untuk apa data digunakan": "How we use data",
  "Akses keluarga dan penyedia layanan": "Family access and service providers",
  "Penyimpanan dan keamanan": "Storage and security",
  "Pilihan dan hak Anda": "Your choices and rights",
  "Hubungi kami": "Contact us",
  "Layanan AmanaFinance": "The AmanaFinance service",
  "Akun dan tanggung jawab pengguna": "Accounts and user responsibilities",
  "Langganan dan pembayaran": "Subscriptions and payments",
  "Ketersediaan dan perubahan": "Availability and changes",
  "Keputusan keuangan dan batas tanggung jawab": "Financial decisions and limits of liability",
  "Pengakhiran dan hukum yang berlaku": "Termination and governing law",
  "Kontak": "Contact",
  "Catatan keuangan keluarga bersifat pribadi. Kami menggunakannya hanya untuk menyediakan dan menjaga layanan AmanaFinance.": "Your family's financial records are private. We use them only to provide and maintain AmanaFinance.",
  "Ketentuan singkat ini mengatur penggunaan AmanaFinance sebagai platform pencatatan keuangan keluarga.": "These brief terms govern the use of AmanaFinance as a family finance tracking platform.",
  "Amina adalah asisten pencatatan keuangan keluarga, bukan pengganti pertimbangan Anda atau tenaga profesional.": "Amina is a family finance assistant, not a substitute for your own judgment or a qualified professional.",
  "Kami memproses data akun seperti nama, email atau nomor telepon; data keluarga dan anggota; serta catatan yang Anda masukkan seperti akun keuangan, transaksi, anggaran, sumber pemasukan, target tabungan, dan aturan rutin.": "We process account data such as your name, email or phone number; family and member data; and records you enter, including financial accounts, transactions, budgets, income sources, savings goals, and recurring rules.",
  "Jika Anda memakai Amina atau fitur unggahan, kami juga memproses isi percakapan dan file yang sengaja Anda kirim, misalnya foto struk atau rekaman suara. Sistem dapat mencatat informasi teknis seperlunya, seperti alamat IP, sesi, waktu akses, dan log kesalahan untuk keamanan serta perbaikan layanan.": "When you use Amina or upload features, we also process conversations and files you intentionally send, such as receipt photos or voice recordings. The system may record necessary technical information such as IP addresses, sessions, access times, and error logs for security and service improvements.",
  "Data digunakan untuk:": "We use data to:",
  "membuat dan mengamankan akun;": "create and secure accounts;",
  "menampilkan, menghitung, dan menyinkronkan catatan keuangan keluarga;": "display, calculate, and synchronize family financial records;",
  "menjalankan Amina sesuai permintaan Anda;": "run Amina when you request it;",
  "menangani bantuan, gangguan, penyalahgunaan, dan kewajiban hukum; serta": "handle support, outages, abuse, and legal obligations; and",
  "memperbaiki kinerja dan keandalan platform.": "improve platform performance and reliability.",
  "Kami tidak menjual data pribadi atau catatan keuangan Anda dan tidak menggunakannya untuk iklan tertarget.": "We do not sell your personal or financial data and do not use it for targeted advertising.",
  "Data dalam satu ruang keluarga dapat dilihat atau dikelola anggota lain sesuai peran yang diberikan. Pastikan Anda hanya mengundang orang yang dipercaya.": "Data in a family space can be viewed or managed by other members according to their assigned roles. Only invite people you trust.",
  "Agar aplikasi berjalan, data dapat diproses oleh penyedia infrastruktur hosting, penyimpanan, keamanan, dan penyedia model AI yang dikonfigurasi AmanaFinance. Kami membatasi pemrosesan tersebut pada kebutuhan penyediaan layanan. Pemrosesan tertentu dapat berlangsung pada server di luar Indonesia, bergantung pada penyedia yang aktif.": "To operate the app, data may be processed by hosting, storage, security, and AI model providers configured by AmanaFinance. We limit this processing to what is needed to provide the service. Depending on the active provider, certain processing may occur on servers outside Indonesia.",
  "Data disimpan selama akun digunakan atau selama diperlukan untuk menyediakan layanan, menyelesaikan sengketa, menjaga keamanan, dan memenuhi kewajiban hukum. Kami menerapkan perlindungan teknis dan akses terbatas, tetapi tidak ada sistem internet yang sepenuhnya bebas risiko. Jangan masukkan PIN, kata sandi bank, kode OTP, atau nomor kartu lengkap.": "Data is retained while your account is used or as needed to provide the service, resolve disputes, maintain security, and meet legal obligations. We apply technical safeguards and restricted access, but no internet system is entirely risk-free. Do not enter PINs, bank passwords, OTP codes, or full card numbers.",
  "Anda dapat melihat, memperbaiki, menambah, dan menghapus banyak catatan langsung dari aplikasi. Untuk meminta salinan, koreksi, penghapusan akun/data, menarik persetujuan, atau menyampaikan keberatan, hubungi kami. Kami dapat meminta verifikasi identitas dan tetap menyimpan data tertentu apabila diwajibkan hukum atau diperlukan untuk keamanan.": "You can view, correct, add, and delete many records directly in the app. Contact us to request a copy, correction, account/data deletion, withdraw consent, or object to processing. We may verify your identity and retain certain data where required by law or needed for security.",
  "Pertanyaan atau permintaan terkait privasi dapat dikirim ke": "Privacy questions or requests can be sent to",
  "AmanaFinance membantu pengguna mencatat dan meninjau keuangan keluarga, menyusun anggaran dan target, serta memakai asisten Amina. AmanaFinance bukan bank, dompet elektronik, penyelenggara pembayaran, penasihat investasi, konsultan pajak, atau penasihat hukum. Kami tidak menyimpan dana dan tidak menjalankan transfer uang.": "AmanaFinance helps users record and review family finances, create budgets and goals, and use the Amina assistant. AmanaFinance is not a bank, e-wallet, payment provider, investment adviser, tax consultant, or legal adviser. We do not hold funds or transfer money.",
  "Anda wajib memberikan informasi yang wajar dan menjaga kerahasiaan akses akun. Aktivitas yang dilakukan melalui akun Anda menjadi tanggung jawab Anda, kecuali terbukti terjadi gangguan pada sistem kami. Laporkan akses tanpa izin sesegera mungkin.": "You must provide reasonable information and keep account access confidential. You are responsible for activity through your account unless it is shown to result from a failure in our system. Report unauthorized access promptly.",
  "Anda bertanggung jawab memastikan memiliki hak untuk memasukkan data anggota keluarga, transaksi, catatan, atau file ke platform. Dilarang memakai layanan untuk kegiatan melawan hukum, merusak sistem, mencoba mengakses data keluarga lain, atau mengunggah konten berbahaya.": "You are responsible for ensuring you have the right to enter family member data, transactions, notes, or files. You may not use the service unlawfully, damage the system, attempt to access another family's data, or upload harmful content.",
  "Jika paket berbayar tersedia, harga, masa aktif, fitur, dan cara pembayaran akan ditampilkan sebelum pembelian. Biaya yang sudah dibayar pada dasarnya tidak dapat dikembalikan setelah masa layanan berjalan, kecuali layanan gagal kami berikan atau peraturan yang berlaku menentukan lain. Perubahan harga hanya berlaku ke periode berikutnya setelah pemberitahuan.": "If paid plans are available, the price, term, features, and payment method will be shown before purchase. Fees are generally non-refundable once the service period begins, unless we fail to provide the service or applicable law requires otherwise. Price changes apply only to a later period after notice.",
  "Kami berusaha menjaga layanan tetap aman dan tersedia, tetapi pemeliharaan, koneksi, penyedia pihak ketiga, atau keadaan di luar kendali dapat menimbulkan gangguan. Fitur dapat diperbaiki, ditambah, dibatasi, atau dihentikan dengan pemberitahuan yang wajar jika perubahan berdampak besar bagi pengguna.": "We work to keep the service secure and available, but maintenance, connectivity, third-party providers, or events beyond our control may cause disruptions. Features may be improved, added, limited, or discontinued with reasonable notice when a change significantly affects users.",
  "Ringkasan, perhitungan, dan jawaban Amina merupakan alat bantu berdasarkan data yang tersedia, bukan jaminan hasil atau nasihat profesional. Anda tetap perlu memeriksa catatan dan mengambil keputusan sendiri. Sepanjang diizinkan hukum, tanggung jawab kami terbatas pada kerugian langsung yang terbukti timbul karena kesalahan layanan kami.": "Summaries, calculations, and Amina's answers are aids based on available data, not guaranteed outcomes or professional advice. You should review records and make your own decisions. To the extent permitted by law, our liability is limited to direct losses proven to result from an error in our service.",
  "Anda dapat berhenti memakai layanan dan meminta penghapusan akun. Kami dapat membatasi akun yang melanggar ketentuan atau mengancam keamanan, dengan pemberitahuan bila memungkinkan. Ketentuan ini tunduk pada hukum Republik Indonesia. Perselisihan diupayakan selesai lebih dahulu melalui musyawarah.": "You may stop using the service and request account deletion. We may restrict accounts that breach these terms or threaten security, with notice where possible. These terms are governed by the laws of the Republic of Indonesia. We will first attempt to resolve disputes amicably.",
  "Pertanyaan, keluhan, atau permintaan bantuan dapat dikirim ke": "Questions, complaints, or support requests can be sent to",
  "Amina membaca percakapan dan data keluarga yang relevan agar dapat menjawab pertanyaan, membuat ringkasan, atau menyiapkan tindakan di aplikasi. Permintaan AI dapat diteruskan ke penyedia model melalui layanan perantara yang dikonfigurasi AmanaFinance. Jangan mengirim kata sandi, PIN, OTP, nomor kartu lengkap, atau rahasia perbankan lainnya.": "Amina reads relevant conversations and family data to answer questions, create summaries, or prepare actions in the app. AI requests may be passed to model providers through an intermediary service configured by AmanaFinance. Do not send passwords, PINs, OTP codes, full card numbers, or other banking secrets.",
  "AI dapat salah memahami pesan, memakai data yang belum diperbarui, atau menghasilkan jawaban yang kurang tepat. Periksa kembali nominal, tanggal, kategori, saldo, dan saran sebelum mengandalkannya. Untuk keputusan investasi, kredit, pajak, hukum, atau keputusan penting lain, pertimbangkan bantuan profesional yang sesuai.": "AI can misunderstand messages, use data that has not been updated, or produce inaccurate answers. Verify amounts, dates, categories, balances, and suggestions before relying on them. For investment, credit, tax, legal, or other important decisions, consider appropriate professional advice.",
  "Amina dirancang untuk menyiapkan tindakan, sedangkan perubahan penting perlu ditinjau atau dikonfirmasi pengguna. Jika Amina sedang gagal atau tidak tersedia, seluruh fitur pencatatan manual tetap dapat digunakan untuk menambah, mengubah, dan menghapus data.": "Amina is designed to prepare actions while important changes must be reviewed or confirmed by the user. If Amina fails or is unavailable, all manual features remain available to add, edit, and delete data.",
  "Amina berfokus pada keuangan pribadi dan keluarga, penggunaan AmanaFinance, serta topik ekonomi yang berdampak pada keputusan rumah tangga. Amina dapat menolak pertanyaan umum yang tidak berkaitan dengan ruang lingkup tersebut.": "Amina focuses on personal and family finance, using AmanaFinance, and economic topics that affect household decisions. Amina may decline general questions outside this scope.",
  "Jika menemukan jawaban atau tindakan yang tidak wajar, hubungi": "If you find an unusual answer or action, contact",
  "Catatan keuangan": "Financial records",
  "Jaga pengeluaran tetap terarah": "Keep spending on track",
  "Bantuan dari data keluargamu": "Guidance from your family data",
  "Bagi uang berdasarkan kebutuhan agar batas belanja mudah dipantau.": "Organize money by need so spending limits are easy to track.",
  "Mulai dengan kebutuhan rutin seperti belanja, transportasi, atau pendidikan.": "Start with routine needs such as groceries, transport, or education.",
  "Ubah rencana besar menjadi target yang terasa dekat dan terukur.": "Turn big plans into goals that feel achievable and measurable.",
  "Tambah, koreksi, atau hapus transaksi secara manual kapan pun—tanpa bergantung pada Amina.": "Add, correct, or delete transactions manually at any time—without relying on Amina.",
  "Catat transaksi pertama secara manual. Fitur ini tetap tersedia meski Amina sedang offline.": "Record your first transaction manually. This remains available even when Amina is offline.",
  "Tambah pemasukan atau pengeluaran": "Add income or expense",
  "Catat pengeluaran": "Record expense",
  "Minta saran keuangan": "Ask for financial guidance",
  "Gimana kondisi keuangan bulan ini?": "How are our finances this month?",
  "Aku belum tahu nominal pastinya": "I don't know the exact amount yet",
  "Belum tahu nominalnya": "I don't know the amount yet",
  "Sudah cukup, lanjut aja": "That's enough, continue",
  "Lewati ini": "Skip this",
  "Amina lagi kenalan sama keuangan keluargamu — jawab santai aja, boleh bilang lewat kalau ada yang belum kepikiran": "Amina is getting to know your family finances—answer casually, and feel free to skip anything you're unsure about.",
  "Amina sedang membaca dan menyiapkan jawaban": "Amina has read your message and is preparing an answer",
  "Amina sedang menyiapkan jawaban": "Amina is preparing an answer",
  "Amina lagi ada gangguan. Coba muat ulang halamannya.": "Amina is having trouble. Please reload the page.",
  "Memuat percakapan": "Loading conversation",
  "Tambah baru…": "Add new…",
  "Tutup": "Close",
  "Batal edit": "Cancel editing",
  "Sudah disimpan": "Saved",
  "Tersimpan.": "Saved.",
  "Dibatalkan": "Cancelled",
  "Tindakan ini tidak bisa dibatalkan.": "This action cannot be undone.",
  "Saldo awal (Rp)": "Opening balance (IDR)",
  "Saldo Awal (Rp)": "Opening Balance (IDR)",
  "Nominal (Rp)": "Amount (IDR)",
  "Budget bulanan (Rp)": "Monthly budget (IDR)",
  "Budget Bulanan (Rp)": "Monthly Budget (IDR)",
  "Target nominal (Rp)": "Target amount (IDR)",
  "Nominal Target (Rp)": "Target Amount (IDR)",
  "Perkiraan Nominal (Rp)": "Estimated Amount (IDR)",
  "Tanggal": "Date",
  "Target tanggal": "Target date",
  "Jenis": "Type",
  "Catatan": "Notes",
  "Nama akun": "Account name",
  "Nama Akun": "Account Name",
  "Nama wallet": "Wallet name",
  "Nama Wallet": "Wallet Name",
  "Nama sumber pemasukan": "Income source name",
  "Nama Sumber": "Source Name",
  "Nama target": "Goal name",
  "Nama Target": "Goal Name",
  "Pilih akun": "Select account",
  "Pilih akun tujuan": "Select destination account",
  "Pilih wallet": "Select wallet",
  "Pilih sumber": "Select source",
  "Pilih target": "Select goal",
  "Ke Akun": "To Account",
  "Sumber Dana": "Funding Source",
  "Sumber dana": "Funding source",
  "Akun Penampung": "Destination Account",
  "Akun Tujuan": "Destination Account",
  "Perkiraan:": "Estimate:",
  "Target:": "Target:",
  "Saldo akun dan progres terkait akan disesuaikan kembali secara otomatis.": "Related account balances and progress will be adjusted automatically.",
  "Saldo berjalan dihitung server dari transaksi, jadi hanya bisa diisi saat akun dibuat.": "The running balance is calculated from transactions, so it can only be entered when creating an account.",
  "Gagal memuat sumber pemasukan. Coba muat ulang halaman.": "Couldn't load income sources. Please reload the page.",
  "Gagal memuat target. Coba muat ulang halaman.": "Couldn't load goals. Please reload the page.",
  "Gagal memuat wallet. Coba muat ulang halaman.": "Couldn't load wallets. Please reload the page.",
  "Transaksi belum bisa dimuat. Coba muat ulang halaman.": "Transactions couldn't be loaded. Please reload the page.",
  "Belum ada transaksi. Kamu bisa mencatatnya manual tanpa menunggu Amina.": "No transactions yet. You can record one manually without waiting for Amina.",
  "Belum ada akun — tambahkan di menu Akun.": "No accounts yet—add one from Accounts.",
  "Belum ada target tabungan.": "No savings goals yet.",
  "Belum ada wallet untuk dianalisa.": "No wallets to analyze yet.",
  "Arus masuk": "Cash in",
  "Selisih bulan ini": "Net this month",
  "Pengeluaran per Wallet": "Spending by Wallet",
  "Breakdown per Wallet": "Breakdown by Wallet",
  "Total Saldo": "Total Balance",
  "Transaksi Terbaru": "Recent Transactions",
  "Rencana keluarga": "Family plans",
  "Kantong keluarga": "Family wallets",
  "Buat anggaran": "Create budget",
  "Buat target": "Create goal",
  "Buat wallet baru": "Create wallet",
  "Buat kantong anggaran secara manual. Amina tetap bisa membantu kapan saja.": "Create a budget wallet manually. Amina can still help at any time.",
  "Buat target seperti dana darurat, pendidikan, atau liburan keluarga.": "Create goals such as an emergency fund, education, or a family vacation.",
  "Tambahkan gaji, usaha, atau sumber pendapatan lain secara manual.": "Add salary, business, or other income sources manually.",
  "Catat sumber penghasilan rutin maupun tambahan milik keluarga.": "Record your family's regular and additional income sources.",
  "Dua Mingguan": "Biweekly",
  "Tidak Tentu": "Irregular",
  "Setor Tabungan": "Savings deposit",
  "Saran dari Amina": "Suggestion from Amina",
  "Buat Wallet Baru": "Create New Wallet",
  "Buat Target Tabungan": "Create Savings Goal",
  "Tambah Akun Baru": "Add New Account",
  "Tambah Sumber Pemasukan": "Add Income Source",
  "Catat Transaksi": "Record Transaction",
  "Edit": "Edit",
  "Menghapus…": "Deleting…",
  "Menyiapkan…": "Preparing…",
  "Tanya Amina": "Ask Amina",
  "Nama keluarga": "Family name",
  "Kode undangan": "Invitation code",
  "Gabung keluarga": "Join family",
  "Siapkan keluargamu": "Set up your family",
  "Satu langkah lagi — buat family baru atau gabung ke yang sudah ada.": "One more step—create a new family or join an existing one.",
  "+ Tambah baru…": "+ Add new…",
  "— pilih —": "— select —",
  "Dashboard Admin": "Admin Dashboard",
  "Admin Platform": "Platform Admin",
  "Kelola user, pembayaran, dan setting LLM AmanaFinance": "Manage AmanaFinance users, payments, and LLM settings",
  "Pembayaran": "Payments",
  "LLM Setting": "LLM Settings",
  "Log AI": "AI Logs",
  "Log Prompt": "Prompt Logs",
  "Direktori seluruh user platform lintas-family: cari, lihat detail dan family yang diikuti.": "Platform-wide user directory: search, view details, and joined families.",
  "Nama, email, atau telepon": "Name, email, or phone",
  "Tidak ada user yang cocok.": "No matching users.",
  "Detail user": "User details",
  "Login terakhir:": "Last login:",
  "Bergabung:": "Joined:",
  "Belum tergabung di family manapun.": "Not a member of any family yet.",
  "Status langganan": "Subscription status",
  "Belum berlangganan": "Not subscribed",
  "Menunggu review": "Pending review",
  "Ditolak": "Rejected",
  "Kedaluwarsa": "Expired",
  "Review permintaan langganan yang menunggu pembayaran, aktifkan atau tolak.": "Review subscription payment requests, then activate or reject them.",
  "Tidak ada langganan dengan status ini.": "No subscriptions with this status.",
  "Aktifkan": "Activate",
  "Tolak langganan": "Reject subscription",
  "Alasan penolakan": "Rejection reason",
  "Lihat bukti pembayaran": "View payment receipt",
  "Catatan review:": "Review notes:",
  "Atur model, base URL, dan API key LLM yang dipakai asisten Amina di seluruh platform.": "Configure the model, base URL, and LLM API key used by Amina across the platform.",
  "Provider": "Provider",
  "Model": "Model",
  "Base URL (opsional)": "Base URL (optional)",
  "Kosongkan untuk mempertahankan key lama": "Leave blank to keep the existing key",
  "Dipakai oleh asisten Amina di seluruh family. Key tersimpan terenkripsi dan tidak pernah ditampilkan utuh.": "Used by Amina across all families. The key is stored encrypted and never displayed in full.",
  "Pantau kegagalan panggilan LLM (rate limit, auth, timeout) lintas family, difilter berdasarkan status dan model.": "Monitor LLM call failures across families, filtered by status and model.",
  "Belum ada kegagalan provider yang tercatat.": "No provider failures recorded.",
  "Detail kegagalan AI": "AI failure details",
  "Tanpa respons (timeout/koneksi)": "No response (timeout/connection)",
  "Respons provider": "Provider response",
  "Belum ada prompt yang tercatat.": "No prompts recorded.",
  "Detail prompt": "Prompt details",
  "User prompt": "User prompt",
  "System prompt": "System prompt",
  "Hanya untuk akun dengan akses admin platform": "Only for accounts with platform admin access",
  "Akun ini tidak punya akses admin platform.": "This account does not have platform admin access.",
  "Akses ditolak": "Access denied",
  "Halaman sebelumnya": "Previous page",
  "Halaman berikutnya": "Next page",
  "Cari": "Search",
  "Status": "Status",
  "Waktu:": "Time:",
  "Telepon:": "Phone:",
  "Email/telepon atau kata sandi salah.": "Incorrect email/phone or password.",
  "Server tidak mengirim token.": "The server did not return a token.",
  "Tidak bisa dihapus karena masih dipakai data lain.": "This cannot be deleted because other data still uses it.",
  "Amina lagi ada gangguan teknis. Coba kirim pesan itu lagi beberapa saat lagi ya.": "Amina is having technical trouble. Please try sending that message again shortly.",
  "Maaf, aku belum paham maksudnya. Bisa dijelaskan lagi?": "Sorry, I didn't understand that. Could you explain it again?",
};

function translateInline(value: string): string {
  return value
    .replace(/^akun\b/i, "account")
    .replace(/^wallet\b/i, "wallet")
    .replace(/^target\b/i, "goal")
    .replace(/^sumber pemasukan\b/i, "income source")
    .replace(/^transaksi\b/i, "transaction");
}

const PATTERNS: Array<[RegExp, (match: RegExpMatchArray) => string]> = [
  [/^Asisten keuangan (.+)$/, (m) => `Financial assistant for ${m[1]}`],
  [/^Hapus (.+)\?$/, (m) => `Delete ${translateInline(m[1])}?`],
  [/^Hapus (.+)$/, (m) => `Delete ${translateInline(m[1])}`],
  [/^Ubah (.+)$/, (m) => `Edit ${translateInline(m[1])}`],
  [/^Tambah (.+)$/, (m) => `Add ${translateInline(m[1])}`],
  [/^Tersisa (\d+) hari$/, (m) => `${m[1]} days remaining`],
];

export function translateText(value: string, locale: Locale): string {
  if (locale === "id" || !value) return value;
  const leading = value.match(/^\s*/)?.[0] ?? "";
  const trailing = value.match(/\s*$/)?.[0] ?? "";
  const core = value.trim();
  if (!core) return value;
  const direct = EN[core];
  if (direct) return `${leading}${direct}${trailing}`;
  for (const [pattern, render] of PATTERNS) {
    const match = core.match(pattern);
    if (match) return `${leading}${render(match)}${trailing}`;
  }
  return value;
}

interface LanguageValue {
  locale: Locale;
  savedLocale: Locale | null;
  loading: boolean;
  setLocale: (locale: Locale, persist?: boolean) => Promise<void>;
  t: (text: string) => string;
}

const LanguageContext = createContext<LanguageValue | null>(null);

function storedLocale(): Locale {
  if (typeof window === "undefined") return "id";
  return window.localStorage.getItem(STORAGE_KEY) === "en" ? "en" : "id";
}

export function LanguageProvider({ children }: { children: ReactNode }) {
  const { status } = useSession();
  const me = useMe();
  const queryClient = useQueryClient();
  const [locale, setLocalLocale] = useState<Locale>(storedLocale);
  const savedLocale = ((me.data as UserWithLocale | undefined)?.locale ?? null) as Locale | null;

  const effectiveLocale = savedLocale ?? locale;

  useEffect(() => {
    if (savedLocale) window.localStorage.setItem(STORAGE_KEY, savedLocale);
  }, [savedLocale]);

  const setLocale = useCallback(async (next: Locale, persist = true) => {
    setLocalLocale(next);
    window.localStorage.setItem(STORAGE_KEY, next);
    if (persist && status === "authenticated") {
      const user = await api.one<UserWithLocale>("PUT", "/auth/preferences", { locale: next });
      queryClient.setQueryData(qk.me, user);
    }
  }, [queryClient, status]);

  useEffect(() => {
    document.documentElement.lang = effectiveLocale;
    const translateNode = (root: ParentNode) => {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      let node = walker.nextNode();
      while (node) {
        if (node.parentElement?.closest("script, style, [data-no-translate]")) {
          node = walker.nextNode();
          continue;
        }
        const translated = translateText(node.textContent ?? "", effectiveLocale);
        if (translated !== node.textContent) node.textContent = translated;
        node = walker.nextNode();
      }
      root.querySelectorAll?.<HTMLElement>("[placeholder], [title], [aria-label]").forEach((element) => {
        for (const name of ["placeholder", "title", "aria-label"]) {
          const value = element.getAttribute(name);
          if (value) element.setAttribute(name, translateText(value, effectiveLocale));
        }
      });
    };
    translateNode(document.body);
    const observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        if (mutation.type === "characterData" && mutation.target.parentNode) {
          translateNode(mutation.target.parentNode);
        }
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) translateNode(node as Element);
          if (node.nodeType === Node.TEXT_NODE && node.parentNode) translateNode(node.parentNode);
        });
      }
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    return () => observer.disconnect();
  }, [effectiveLocale]);

  const value = useMemo<LanguageValue>(() => ({
    locale: effectiveLocale,
    savedLocale,
    loading: status === "authenticated" && me.isPending,
    setLocale,
    t: (text) => translateText(text, effectiveLocale),
  }), [effectiveLocale, savedLocale, status, me.isPending, setLocale]);

  // Remount saat bahasa berganti supaya teks sumber Bahasa Indonesia kembali
  // terbentuk sebelum diterjemahkan ke English (dan sebaliknya).
  return <LanguageContext.Provider key={effectiveLocale} value={value}>{children}</LanguageContext.Provider>;
}

export function useLanguage(): LanguageValue {
  const context = useContext(LanguageContext);
  if (!context) throw new Error("useLanguage must be used inside LanguageProvider");
  return context;
}
