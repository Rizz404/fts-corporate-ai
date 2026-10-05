# FTS Corporate AI

Kantor virtual internal untuk satu perusahaan: karyawan menjelajahi ruang, bertanya ke asisten AI berbasis SOP resmi, dan meneruskan kebutuhan ke staf HR/IT melalui tiket. Rencana produk dan tahapan ada di [docs/](docs/README.md).

Stack: Laravel 13 (PHP 8.4), PostgreSQL 17, Blade, Tailwind CSS 4, Alpine.js 3, Vite 8.

## Kebutuhan

- PHP 8.4+ dengan ekstensi mbstring, intl, openssl, pdo_pgsql (atau pdo_sqlite untuk lokal sementara), fileinfo, xml/dom, ctype, curl, tokenizer
- Composer 2
- Node 22.12+ dan npm
- PostgreSQL 17 (Docker via `compose.yaml` atau instalasi native)

## Setup lokal

```bash
composer setup        # install, .env, APP_KEY, migrate, npm ci, build
composer dev          # artisan serve + queue:listen + vite
```

Atau langkah manual:

```bash
composer install
cp .env.example .env          # lalu isi DB_PASSWORD
php artisan key:generate
php artisan migrate
npm ci
npm run build                 # atau npm run dev
php artisan serve
```

### Database

Target dan CI memakai PostgreSQL 17. Dengan Docker:

```bash
# isi DB_PASSWORD di .env terlebih dahulu
docker compose up -d
```

Container membuat database `corporate_ai` dan `corporate_ai_test` untuk role `corporate_ai`. Tanpa Docker, buat role dan kedua database yang sama pada PostgreSQL 17 native.

Sementara (keputusan Plan 1), pengembangan lokal boleh memakai SQLite: set `DB_CONNECTION=sqlite`, hapus/comment variabel `DB_*` lainnya, lalu `touch database/database.sqlite`. Kode tetap ditulis untuk PostgreSQL.

### Akun admin pertama

```bash
php artisan db:seed                       # data referensi: departemen, role, support team (idempoten)
php artisan corporate:create-admin --email=admin@example.com --name="Admin"
```

Password sementara dimasukkan lewat prompt tersembunyi (minimal 12 karakter) dan wajib diganti saat login pertama. Akun demo sintetis (hanya local/testing): `php artisan corporate:demo-accounts` (password acak ditampilkan sekali; menjalankan ulang mereset password).

## URL

| URL | Keterangan |
|---|---|
| `/` | Alihkan ke `/login` atau `/office` |
| `/login` | Login karyawan |
| `/password/change` | Ganti password (wajib untuk password sementara) |
| `/office` | Halaman karyawan sementara (diganti scene kantor di Plan 3) |
| `/admin` | Panel admin (admin/editor/staff; kelola akun dan departemen khusus admin) |
| `/up` | Health check framework |
| `/dev/ui` | Galeri komponen (hanya local/testing) |
| `/dev/admin`, `/dev/user`, `/dev/guest` | Pratinjau template (hanya local/testing) |

## Scripts

| Perintah | Fungsi |
|---|---|
| `composer setup` | Setup awal non-destruktif |
| `composer dev` | Server, queue listener, dan Vite bersamaan |
| `npm run dev` / `npm run build` | Vite dev server / build produksi |

Skrip pengujian (`composer test`, `npm run test:ui`, `npm run lint:ui`) dan CI diselesaikan di Plan 8 sesuai [kebijakan pengujian](docs/README.md#kebijakan-pengujian).

## UI: layout, komponen, dan tema

- Layout: `x-layouts.guest`, `x-layouts.admin`, `x-layouts.user` (semuanya menyusun `x-layouts.base`).
- Komponen: `x-ui.*` dan `x-navigation.*`. Panduan lengkap dan contoh: [docs/UI_COMPONENTS.md](docs/UI_COMPONENTS.md).
- Tema: satu-satunya sumber warna/radius/shadow adalah `resources/css/theme.css` (spesifikasi di [docs/UI_FOUNDATION.md](docs/UI_FOUNDATION.md)).

Aturan dasar:

1. Pakai komponen yang ada sebelum membuat komponen baru.
2. Tambahkan token hanya di `theme.css`; jangan memakai palette Tailwind default, warna hex, atau inline style warna di view/JS.
3. Variant komponen didaftarkan di `App\Support\Ui\ComponentStyles` sebagai class literal penuh.
4. Daftarkan modul Alpine di `resources/js/ui/register.js`; `Alpine.start()` hanya ada di `resources/js/app.js`.

## Struktur penting

```text
app/Http/Middleware/      AssignRequestId, SetLocale, EnsureAccountIsActive, EnsurePasswordChanged, …
app/Modules/Identity/     ActorContext, actions akun, aturan validasi, AdminGuard
app/Support/Http/         ApiResponse, ApiException, ApiExceptionRenderer (envelope JSON /api/v1)
app/Support/Ui/           ComponentStyles, FieldState
config/corporate.php      brand, timezone tampilan, locale, retensi, aset scene
config/assistant.php      budget AI turn
routes/web.php            halaman; memuat routes/internal.php (/api/v1) dan routes/dev.php
lang/{id,en,ja}/ui.php    teks antarmuka
resources/css/            app.css, theme.css, base.css
resources/js/             app.js, ui/*, shared/api.js, shared/dom.js
docs/                     master plan, kontrak teknis, plan 1–8
```
