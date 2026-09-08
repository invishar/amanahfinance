<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Struktur proyek

Monorepo backend + frontend AmanaFinance:

- `app/`, `routes/`, `database/`, dst. — API Laravel (lihat [`CLAUDE.md`](CLAUDE.md) dan
  [`API-v1.md`](API-v1.md)).
- `frontend/` — klien Next.js (App Router, static export), riwayat commit dipertahankan
  dari repo `amanahfinance_front` lewat `git subtree`. Aturan sisi klien ada di
  [`frontend/CLAUDE.md`](frontend/CLAUDE.md). `npm run build` di `frontend/`
  menghasilkan `frontend/out/` yang di-deploy ke `public/` supaya diserve same-origin
  oleh Laravel.
- `resources/js/` — halaman Inertia (React) yang menggantikan klien Next di atas;
  dibangun oleh Vite ke `public/build/`, plus satu bundle Node untuk SSR (di bawah).

## Inertia SSR

Halaman Inertia dirender lebih dulu di server supaya HTML pertama sudah berisi
markup, bukan `<div id="app"></div>` kosong. Yang dirender hanya kerangka —
shell aplikasi, halaman auth, dan shared prop `auth`; seluruh angka keuangan
tetap datang dari `/api/v1` setelah hidrasi (lihat `HandleInertiaRequests`).

Potongan-potongannya:

| Berkas | Perannya |
| --- | --- |
| `resources/js/ssr.tsx` | Entry Node; dibangun `vite build --ssr` → `bootstrap/ssr/ssr.js` |
| `resources/js/lib/inertia-pages.tsx` | Resolusi halaman + layout, dipakai bersama entry browser dan Node |
| `resources/js/app.tsx` | `hydrateRoot` kalau markup SSR ada, `createRoot` kalau tidak |
| `config/inertia.php` | `ssr.enabled`, `ssr.url`, jalur bundle |
| `AppServiceProvider::boot()` | Matikan SSR saat Vite hot; catat kegagalan SSR ke log |

Build + jalankan:

```bash
npm run build                    # klien (public/build) + SSR (bootstrap/ssr)
php artisan inertia:start-ssr    # proses Node di 127.0.0.1:13714
php artisan inertia:check-ssr    # sehat atau tidak
php artisan inertia:stop-ssr
```

Saat `npm run dev`, SSR sengaja dilewati (`Inertia::disableSsr` mengecek hot
file Vite) — dev server di repo ini tidak menyediakan endpoint `/__inertia_ssr`,
jadi tanpa itu tiap halaman akan mencoba lalu gagal dulu.

### SSR di hPanel

SSR **butuh proses Node yang hidup terus**, sesuatu yang sepanjang repo ini
justru dihindari (lihat CLAUDE.md: tidak ada daemon, queue pakai burst cron).
Karena itu SSR di produksi bersifat *best effort*:

- Kalau proses SSR tidak jalan, Inertia jatuh balik ke render sisi klien.
  Halaman tetap hidup dan tidak ada error ke user — cuma kembali seperti
  sebelum SSR ada. Kejadiannya dicatat sebagai `warning` di `storage/logs`
  ("Inertia SSR gagal"), supaya tidak mati diam-diam.
- `php artisan inertia:start-ssr` memakai Symfony Process, jadi ia **butuh
  `proc_open`** — fungsi yang di beberapa paket hPanel ada di
  `disable_functions` (masalah yang sama dengan `schedule:run`, lihat
  CLAUDE.md). Kalau begitu, jalankan bundle-nya langsung:
  `node bootstrap/ssr/ssr.js`.
- Cara paling realistis menjaganya tetap hidup di hPanel adalah mendaftarkan
  `bootstrap/ssr/ssr.js` sebagai aplikasi di Node.js Selector (Passenger),
  yang me-restart proses sendiri kalau mati. Kalau host tidak menyediakan itu
  sama sekali, set `INERTIA_SSR_ENABLED=false` — lebih baik mematikan SSR
  secara eksplisit daripada membayar satu percobaan koneksi gagal per request.
- Bundle SSR self-contained (`ssr.noExternal` di `vite.config.js`), jadi
  proses Node-nya tidak ikut mati kalau `node_modules` dibersihkan setelah
  build. `bootstrap/ssr/` gitignored — dibangun di server, sama seperti
  `public/build/`.

> Setiap deploy yang mengubah `resources/js/` wajib membangun ulang bundle SSR
> **dan** me-restart prosesnya; proses lama memegang kode lama di memori.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
