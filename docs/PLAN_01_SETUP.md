# Plan 1 Setup proyek template admin user dan fondasi UI

| Metadata | Nilai |
|---|---|
| ID | 1 |
| Status | Ready |
| Diperbarui | 5 Oktober 2026 |
| Dependensi | [Master](000_MASTER_PLAN.md), [kontrak teknis](TECHNICAL_CONTRACTS.md), [fondasi UI](UI_FOUNDATION.md) |
| Hasil implementasi | Belum dikerjakan |

## 1. Hasil akhir tahap

Root fts-corporate-ai menjadi aplikasi Laravel yang dapat boot, terhubung ke database, menjalankan migration, dan build asset. Sebelum fitur bisnis dibuat, tersedia:
- Layout guest/login, admin, dan user berbasis Blade.
- Tailwind4 dengan satu tema semantik untuk seluruh layout.
- Alpine3 dengan Focus/Collapse, state UI reusable, serta satu bootstrap.
- Komponen tombol/form/kartu/badge/alert/tabel/modal/dropdown/tabs/loading/toast yang siap dipakai plan selanjutnya.
- Halaman preview/gallery untuk meninjau komponen/layout tanpa fitur akun.
- API/DOM helpers dan aturan reuse.
- Lockfile, environment example, dan dokumentasi cara menggunakan komponen.

Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), Plan1 tidak menulis/menjalankan tes otomatis, lint, CI, atau review browser. Infrastruktur tes (PHPUnit PostgreSQL, guard database testing, tes Node), lint UI, dan CI dikerjakan Plan8.

Plan1 membangun template serta fondasi. Login/provisioning dan policy bisnis dikerjakan Plan2; office scene final Plan3; CMS/chat/tiket tetap pada plan masing-masing. Preview menggunakan data sintetis, bukan data perusahaan.

## 2. Runtime dan paket

Target PHP8.4, Composer2, Node22 minimal22.12, npm, PostgreSQL17. Extension PHP: mbstring, intl, openssl, PDO/pdo_pgsql, fileinfo, XML/DOM, ctype, curl, tokenizer. Runtime PHP yang tersedia boleh diverifikasi tanpa mengubah instalasi sistem otomatis; target CI8.4.

### Composer require

| Paket | Constraint awal | Tujuan |
|---|---|---|
| php | ^8.4 | Target aplikasi |
| laravel/framework | ^13.17 | Framework seperti sumber |
| laravel/tinker | ^3.0 | Console development |
| blade-ui-kit/blade-heroicons | ^2.7 | SVG icon server-rendered untuk komponen bersama |

Heroicons menarik blade-ui-kit/blade-icons sebagai dependency; tidak perlu mendeklarasikan paket yang sama dua kali. Verifikasi Composer resolve kompatibel Laravel13. Jangan meng-copy composer.lock hotel karena dependency proyek baru berbeda.

### Composer require-dev

fakerphp/faker:^1.23, laravel/pint:^1.27, mockery/mockery:^1.6, nunomaduro/collision:^8.6, phpunit/phpunit:^12.5.12.

### npm dependencies

| Paket | Constraint awal | Tujuan |
|---|---|---|
| alpinejs | ^3.17.4 | State/interaksi ringan dalam Blade |
| @alpinejs/focus | ^3.17.4 | Modal/drawer focus trap dan restoration |
| @alpinejs/collapse | ^3.17.4 | Expand/collapse menu atau panel |

Core/plugins harus memakai rilis resolved yang sama pada lockfile. Snapshot registry saat perencanaan menunjukkan3.17.4; ini acuan awal, bukan klaim sudah terpasang. Update ketiganya bersama setelah verifikasi.

### npm devDependencies

vite:^8.0.0, tailwindcss:^4.0.0, @tailwindcss/vite:^4.0.0, @tailwindcss/forms:^0.5.11, laravel-vite-plugin:^3.1, concurrently:^10.0.3.

Forms plugin memakai strategy class agar reset hanya berlaku pada kontrol komponen. Fetch API native dipakai untuk HTTP; tidak membutuhkan Axios. Auth/Policy/Notifications memakai Laravel native yang sudah ditetapkan kontrak.

Tidak meng-copy Anthropic SDK, PAO, Multiplex, Pail, atau Boost sebagai dependensi wajib karena fondasi produk tidak memerlukan paket tersebut. Tidak memasang template CSS lain yang bersaing dengan tema Tailwind. Skeleton Laravel tanpa starter-kit React/Vue/Livewire.

Resolve paket sekali, simpan composer.lock/package-lock.json, lalu gunakan composer install/npm ci pada instalasi berikutnya. Catat versi resolved, jangan hanya menyalin versi pada dokumen.

## 3. Bootstrap tanpa menimpa docs

1. Baca dokumentasi dan aturan proyek, periksa root. Jika artisan/composer.json sudah ada, audit dan lengkapi setup; jangan scaffold ulang.
2. Jika belum ada, dari direktori induk buat skeleton pada folder sementara kosong: `composer create-project laravel/laravel fts-corporate-ai-bootstrap "^13.0" --no-install --no-scripts`. Jangan menimpa folder sementara yang sudah ada.
3. Copy isi skeleton ke fts-corporate-ai setelah memeriksa setiap target; pertahankan docs. Di Windows gunakan PowerShell native dengan path absolut terverifikasi. Tidak membuat Git commit/push otomatis.
4. Sesuaikan manifest dan hook framework. Jalankan composer install serta npm install untuk menghasilkan lockfile. Paket tambahan di atas harus tercantum di manifest.
5. .gitignore mengecualikan vendor, node_modules, env aktual, env testing, runtime storage/log/cache, dan public/build. Manifest/lockfile, .env.example, compose/init script, resources, dan docs dilacak.

## 4. PostgreSQL dan environment

Buat compose.yaml: service postgres:17, volume corporate_pgdata, port127.0.0.1:5432, healthcheck pg_isready, database corporate_ai, role corporate_ai. Password dari environment lokal. Init script juga membuat corporate_ai_test untuk role yang sama. Jika Docker tidak tersedia, PostgreSQL native17 dengan database/role setara. Baseline target bukan SQLite; lihat keputusan pengguna di bawah untuk pengecualian lokal sementara.

.env.example:
- APP_NAME="FTS Corporate AI", APP_ENV=local, APP_URL=http://localhost:8000, APP_TIMEZONE=Asia/Jakarta.
- DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=5432, DB_DATABASE=corporate_ai, DB_USERNAME=corporate_ai, DB_PASSWORD kosong.
- SESSION_DRIVER=database, CACHE_STORE=database, QUEUE_CONNECTION=database, MAIL_MAILER=log.
- ASSISTANT_ENABLED=false; LOCAL_LLM_BASE_URL, LOCAL_LLM_API_KEY, LOCAL_LLM_MODEL bernilai kosong.

Buat env aktual lokal, generate APP_KEY hanya bila kosong, lalu migrate saat database siap. Tidak membaca/menyalin env hotel. .env.testing serta helper yang menolak database tanpa suffix _test dikerjakan Plan8.

**Keputusan pengguna (5 Oktober 2026):** selama pengembangan lokal, env aktual boleh memakai SQLite sementara (`database/database.sqlite`, tidak dilacak) karena role PostgreSQL lokal belum disiapkan. Target tetap PostgreSQL17: .env.example, compose.yaml, dan Plan8 (tes/CI/staging) memakai PostgreSQL. Kode tahap1–7 tidak boleh bergantung pada perilaku khusus SQLite; fitur yang memerlukan PostgreSQL (jsonb, partial index, row lock) tetap ditulis untuk PostgreSQL dan dicatat bila belum dapat dijalankan di lokal.

Migration framework users/password_reset_tokens/sessions, cache/cache_locks, jobs/job_batches/failed_jobs tersedia satu kali. Tidak ada migration data bisnis pada tahap ini.

## 5. Konfigurasi aplikasi dan entry point

Siapkan config/corporate.php dan config/assistant.php sesuai kontrak; services.local_llm tetap mengikuti pola hotel. Baca env hanya pada config.

routes/web.php memuat routes/internal.php di grup /api/v1 dengan web/session/CSRF. Tambahkan request_id middleware, JSON success/error formatter, locale resolver id/en/ja, serta lang/{id,en,ja}/ui.php. Endpoint bisnis baru ditambahkan tahapnya, bukan dummy endpoint sukses.

Route / menampilkan landing setup sederhana dengan brand; /up health framework. routes/dev.php dimuat hanya APP_ENV local/testing:
- GET /dev/ui: gallery seluruh komponen.
- GET /dev/admin: template admin dengan mock content.
- GET /dev/user: template user/office frame dengan mock content.
- GET /dev/guest: guest/login frame dengan form preview tanpa submit bisnis.

Pada production semua /dev/*404. Preview tidak memakai akun/database personal dan tidak mengklaim policy/admin auth sudah tersedia. Plan2 mengambil alih root redirect serta route autentikasi yang nyata.

## 6. Struktur asset dan bootstrap Alpine

```text
resources/
  css/
    app.css
    theme.css
    base.css
  js/
    app.js
    ui/
      register.js
      admin-shell.js
      user-shell.js
      modal.js
      dropdown.js
      tabs.js
      toasts.js
    shared/
      api.js
      dom.js
  views/
    components/
      layouts/{base,guest,admin,user}.blade.php
      navigation/{admin-sidebar,user-menu,language-switch,breadcrumbs}.blade.php
      ui/...
    dev/{gallery,admin,user,guest}.blade.php
    vendor/pagination/corporate.blade.php
app/Support/Ui/{ComponentStyles,FieldState}.php
scripts/check-ui-conventions.mjs   (dibuat Plan8)
```

Vite input hanya resources/css/app.css dan resources/js/app.js. Plugin laravel-vite-plugin dan @tailwindcss/vite; satu @vite di layout base. Tidak memuat CDN Tailwind/Alpine atau bundle berbeda per role.

app.css mengimpor Tailwind, theme.css, base.css, dan forms plugin strategy class sesuai UI_FOUNDATION. Tailwind config CSS-first; tidak membuat tailwind.config.js. Source class berada pada Blade/JS dan vendor pagination yang relevan.

Urutan app.js:
1. Import Alpine, Focus, Collapse.
2. Register plugins.
3. Register reusable Alpine.data/providers/store ui dari ui/register.js.
4. Expose window.Alpine untuk development/integrasi bila diperlukan.
5. Alpine.start() tepat sekali.

Modul baru pada tahap berikutnya didaftarkan sebelum start; tidak memanggil start lagi. Lifecycle component membersihkan listener/timer melalui destroy. Pakai x-text/escaped Blade untuk data; bukan x-html untuk pesan/konten model.

## 7. Tema dan template yang harus dibangun lebih dulu

Baca [UI_FOUNDATION.md](UI_FOUNDATION.md) dan implementasikan token/mapping di sana secara tepat. Semua literal warna berasal dari theme.css. Reset namespace warna default dan pertahankan spacing/breakpoint. Contoh utility: bg-primary, text-text, bg-surface, border-border, rounded-control, shadow-card.

Bangun x-layouts.base lebih dulu, lalu guest/admin/user. Admin sidebar dan user menu memakai navigation components yang sama di setiap halaman dalam layoutnya. Header brand, language switch, breadcrumbs, serta actions disediakan melalui komponen/slot.

Menu preview boleh berisi label Overview, Karyawan, Pengetahuan, Tiket, Pengaturan, dan ruang office, tetapi item tanpa route nyata nonaktif. Sidebar/layout tidak menebak role dari JS. Plan2 memasang server-side ability dan menu visibility.

User shell menyediakan slot backdrop/character/scene content/navigation/chat; belum menggambar ulang virtual office lengkap. Plan3 mengisi slot tersebut. Guest shell nantinya dipakai login/password change, bukan membuat layout auth baru di Plan2.

## 8. Implementasikan library komponen reusable

Ikuti props, states, slots, dan perilaku pada UI_FOUNDATION. Urutan pembuatan:
1. icon, spinner, ComponentStyles, FieldState.
2. button/link-button, field/input/textarea/select/checkbox.
3. card/badge/alert/empty-state/skeleton.
4. table/row/cell, pagination, breadcrumbs/navigation.
5. dropdown/modal/confirm-dialog/tabs/tab-panel/toast-container.
6. Integrasikan ke tiga layout dan gallery.

Semua class variant berupa literal penuh di mapping bersama. button/link-button berbagi style resolver. Control form memakai reset plugin class dan override semantik. UI tidak menjalankan query DB; label diterjemahkan server/caller. Props warna bebas tidak disediakan.

Tambahkan document hasil implementasi docs/UI_COMPONENTS.md yang berisi contoh pemakaian aktual, props/default, accessibility, event, dan lokasi source komponen. Dokumen tersebut dibuat saat implementasi Plan1, bukan dianggap sudah tersedia sekarang. Spesifikasi nilai tema tetap UI_FOUNDATION.

Minimal contoh pemakaian yang harus dibuktikan di gallery:

```blade
<x-ui.button variant="primary" type="submit">
    {{ __('ui.save') }}
</x-ui.button>

<x-ui.card :title="__('ui.request_details')">
    <x-ui.badge tone="info">{{ __('ui.preview') }}</x-ui.badge>
</x-ui.card>

<x-ui.field name="email" id="preview-email" :label="__('ui.email')">
    <x-ui.input name="email" id="preview-email" type="email"
                autocomplete="email" />
</x-ui.field>
```

Contoh form error di gallery menggunakan FieldState untuk invalid/describedby dan error bag. Error/helper ID harus terkait control; jangan menyalin pola form dengan perhitungan ID yang berbeda pada fitur berikutnya.

## 9. Helpers dan script development

Implementasikan shared/api.js dengan apiRequest/ApiError dan shared/dom.js untuk text node/safe attributes. Provider/fitur belum dipanggil. Template memakai Alpine untuk state UI; request ke server tetap melalui helper bersama.

composer script:
- dev: concurrently menjalankan artisan serve, queue:listen --tries=3, npm run dev; --kill-others menghentikan proses pasangan bila salah satu berakhir.
- setup: composer install, env bila belum ada, key bila kosong, migrate non-destruktif, npm ci setelah lock tersedia, npm build.

npm scripts: dev=vite, build=vite build. Script `lint:ui` (scripts/check-ui-conventions.mjs), `test:ui`, dan composer `test` ditambahkan Plan8.

Buat README root untuk setup, URL preview, daftar scripts, penggunaan layout/component, dan cara menambah komponen/token. Instruksi dasar: pakai komponen yang ada sebelum membuat komponen baru; tambahkan token hanya pada sumber tema.

## 10. Kriteria implementasi dan verifikasi pengguna

Langkah setup yang boleh dijalankan implementer agar aplikasi dapat dipakai (bukan tes): composer install/update, npm install, key:generate, migrate, dan npm run build.

Implementasi Plan1 dianggap selesai (status Implemented) bila:
- Manifest/lockfile tersedia; Alpine core/plugins satu rilis dan Alpine.start() tunggal.
- Config, middleware request_id/locale, JSON formatter, routes web/internal/dev, dan lang id/en/ja tersedia.
- Tiga template, seluruh komponen baseline, gallery/preview, README root, dan docs/UI_COMPONENTS.md tersedia.
- compose.yaml, init script, dan .env.example PostgreSQL tersedia.

Checklist yang disampaikan ke pengguna untuk dicek atau diklarifikasi:
1. `composer setup` atau `php artisan migrate` + `npm run build` berhasil di mesin pengguna; `php artisan serve` membuka / dan /up.
2. Buka /dev/ui, /dev/admin, /dev/user, /dev/guest; ganti bahasa id/en/ja dan pastikan label berubah.
3. Gallery: semua varian tombol/badge/alert tampil, form error menampilkan pesan di bawah field, tabel dan pagination berfungsi.
4. Interaksi: drawer admin di layar <1024, modal/confirm (Escape, klik luar, fokus kembali ke tombol), dropdown (panah/Escape), tabs (panah/Home/End), toast muncul dan dapat ditutup, chat dock user.
5. Tampilan pada 375x812, 768x1024, 1440x900 tanpa overflow yang menghalangi fungsi; tidak ada kedipan elemen x-cloak.
6. Klarifikasi: palet/brand awal, label menu preview, dan kapan role PostgreSQL lokal disiapkan (saat ini SQLite sementara).

Skenario tes otomatis Plan1 (smoke, preview, kontrak komponen, envelope JSON, API helper Node), lint UI, dan CI tercatat di [Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan). Tidak ada fitur bisnis dianggap selesai hanya dari mock preview.

## 11. Referensi dan catatan implementasi

FTS Hotel AI menjadi acuan Laravel/Blade/Vite/Tailwind dan gateway; Alpine/components/themes merupakan penambahan yang diminta pengguna, PostgreSQL mengikuti master.

[Instalasi Laravel](https://laravel.com/framework/docs/13.x/installation), [Tailwind theme](https://tailwindcss.com/docs/theme), [Alpine module setup](https://alpinejs.dev/essentials/installation), [Forms plugin](https://github.com/tailwindlabs/tailwindcss-forms), [Heroicons](https://github.com/driesvints/blade-heroicons), [Blade components](https://laravel.com/framework/docs/13.x/blade#components).

Belum dikerjakan. Isi hasil perubahan, versi resolved, perintah setup yang dijalankan, checklist untuk pengguna, dan kendala setelah implementasi. Ready menunjukkan dokumen siap, bukan setup telah dilaksanakan.
