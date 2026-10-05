# Fondasi UI Tailwind dan Alpine FTS Corporate AI

Status: Ready sebagai spesifikasi; belum diimplementasikan.
Dibangun pada [Plan 1](PLAN_01_SETUP.md), dipakai oleh seluruh tahap berikutnya.
Keputusan ini menambahkan Alpine.js pada stack referensi FTS Hotel AI.

## 1. Layout dan pembagian tanggung jawab

Template dibuat internal dengan Blade, Tailwind, Alpine, dan Heroicons. Tidak mengambil template dashboard eksternal yang membawa sistem CSS/component kedua.

| Blade component | Tanggung jawab dan slot |
|---|---|
| x-layouts.base | html/head/body, lang, title, meta CSRF, satu Vite bundle, skip link, toast container; slot utama dan head tambahan |
| x-layouts.guest | Frame guest/login berpusat, brand, language switch, panel form, footer; title dan slot utama |
| x-layouts.admin | Sidebar, mobile drawer, topbar, breadcrumb, judul/actions, main content; title, navigation, breadcrumbs, actions, slot utama |
| x-layouts.user | Shell karyawan, header brand, language switch, navigasi kantor, area scene/content dan chat dock; title, navigation, backdrop, character, slot utama, chat |

Semua layout selain base menyusun x-layouts.base. Masing-masing halaman memasang satu layout teratas, sehingga head/Vite/Alpine tidak terduplikasi. Admin dipakai juga editor/staff sesuai menu yang diotorisasi server; nama layout tidak menentukan role.

Plan 1 membangun frame template dan preview sintetis. Plan 2 memasang login serta pembatasan akses. Plan 3 menyusun office-stage di dalam layout user, lalu menambahkan ilustrasi, narrator, dan scene transition. Layout user tidak diganti menjadi dashboard administratif.

Desktop admin: sidebar 16rem, topbar 4rem, konten utama berjarak konsisten. Pada lebar <1024 sidebar menjadi drawer; tombol pembuka mempunyai aria-expanded, drawer dapat ditutup dengan Escape/outside click dan focus kembali ke pemicu. User mengikuti mobile-first dengan slot stage yang dapat dikembangkan Plan 3. Breakpoint Tailwind bawaan dipertahankan.

Default tema light. Halaman admin memakai sidebar secondary dan panel surface; user serta guest memakai token sama. Semua menu bisnis yang route-nya belum tersedia ditampilkan sebagai preview nonaktif; jangan membuat endpoint domain kosong hanya untuk memenuhi navigation.

## 2. Sumber tunggal tema

Setelah implementasi, `resources/css/theme.css` menjadi sumber nilai visual. Dokumen ini adalah spesifikasi awalnya. Tema tidak diduplikasi ke config PHP, JavaScript, inline style, atau stylesheet per halaman.

`resources/css/app.css`:

```css
@import 'tailwindcss';
@import './theme.css';
@import './base.css';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';

@plugin '@tailwindcss/forms' {
    strategy: 'class';
}
```

theme.css mendefinisikan nilai dasar melalui `@theme static` agar seluruh token tersedia; alias on-primary/focus didefinisikan dalam blok `@theme inline` yang merujuk token dasar. Reset hanya namespace warna melalui `--color-*: initial`; spacing/breakpoint/utility layout bawaan tetap tersedia. Dengan demikian fitur memakai semantic color utility, bukan palette default red/blue/gray.

| Token warna | Nilai awal | Pemakaian |
|---|---|---|
| primary | #0F766E | CTA utama dan pilihan aktif |
| primary-hover | #115E59 | Hover CTA utama |
| secondary | #142337 | Sidebar, header scene |
| secondary-hover | #203551 | Hover di permukaan secondary |
| accent | #2563EB | Aksen/link bila diperlukan |
| canvas | #F4F7FB | Latar aplikasi |
| surface | #FFFFFF | Panel dan input |
| surface-muted | #EDF2F7 | Area sekunder/readonly |
| text | #172A3A | Teks utama |
| muted | #526579 | Teks pendukung |
| border | #D7E0EA | Garis pemisah |
| success | #15803D | Indikasi sukses |
| success-soft | #F0FDF4 | Latar sukses |
| warning | #B45309 | Indikasi perhatian |
| warning-soft | #FFFBEB | Latar warning |
| danger | #B91C1C | Error/destructive action |
| danger-hover | #991B1B | Hover destructive action |
| danger-soft | #FEF2F2 | Latar error |
| info | #1D4ED8 | Informasi |
| info-soft | #EFF6FF | Latar informasi |
| on-primary | alias var(--color-surface) | Teks pada primary/secondary/danger |
| focus | alias var(--color-primary) | Focus ring |
| transparent | transparent | Permukaan transparan |
| current | currentColor | SVG mengikuti warna teks |
| overlay | rgb(20 35 55 / 0.6) | Backdrop modal |

Nama variable `--color-primary` menghasilkan utility `bg-primary`, `text-primary`, dan seterusnya. Literal tabel hanya ditulis ke theme.css. Contoh consumer: `bg-surface text-text border-border`, `bg-primary hover:bg-primary-hover text-on-primary`, `focus-visible:ring-focus`.

Token tambahan:
- `--font-sans`: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans JP", sans-serif. Font tidak harus diunduh dari jaringan.
- `--radius-control: 0.5rem`, `--radius-card: 1rem`, `--radius-dialog: 1rem`.
- `--shadow-card: 0 1px 3px rgb(20 35 55 / 0.08)`.
- `--shadow-overlay: 0 20px 40px rgb(20 35 55 / 0.16)`.
- `--text-page-title: 1.5rem` dengan line-height 2rem; section-title 1.125rem/1.75rem; body 0.875rem/1.5rem; caption 0.75rem/1rem.
- `--spacing` tetap 0.25rem. Gunakan gap-2/3/4/6/8; panel p-4 mobile dan md:p-6 desktop.
- Motion normal maksimum200ms untuk kontrol; class motion-reduce menghilangkan animasi yang tidak perlu.

base.css hanya memuat base/layout rules lintas halaman: x-cloak, focus/skip link, reduced motion, default body, dan kelas dasar kontrol bila perlu. Warna di base.css merujuk token, bukan literal baru. Style scene khusus tetap dapat dibuat dengan selector terlingkup dan variable tema.

Ilustrasi SVG di public/assets/corporate dapat memakai literal fill karena stylesheet luar tidak diwarisi oleh gambar img. Warna visual utamanya mengikuti palette ini; ilustrasi tidak menjadi sumber warna UI baru. Asset library/vendor dan binary tidak masuk pemeriksaan source color.

## 3. Kontrak komponen reusable

Komponen anonymous Blade di `resources/views/components/ui`, layout di components/layouts, dan navigasi di components/navigation. Props tampilan tervalidasi; unknown variant tidak membuat nama class dinamis, melainkan fallback default atau error yang jelas pada development.

| Komponen | Props/slot utama | Perilaku |
|---|---|---|
| x-ui.button | variant primary/secondary/outline/ghost/danger, size sm/md/lg, type default button, loading, disabled; slot label | Native button, loading menonaktifkan submit ulang, aria-busy, spinner dan label tetap terbaca |
| x-ui.link-button | href server, variant/size sama button; label | Anchor untuk navigasi; memakai resolver style button yang sama |
| x-ui.icon | name whitelist, size sm/md/lg, label optional | Heroicons outline; dekoratif aria-hidden, icon bermakna mempunyai label |
| x-ui.field | name, id wajib, label, required, help, error optional; slot control | Label for=id, help/error ID stabil, menampilkan validation error dari error bag |
| x-ui.input | name, id, type default text, value, invalid, describedby, disabled | form-input, escaped value; password tidak menggunakan old value |
| x-ui.textarea | name, id, value, rows, invalid, describedby | form-textarea; isi plain text |
| x-ui.select | name, id, options value/label, value, invalid, describedby | Native form-select; pilihan diterjemahkan oleh pemanggil |
| x-ui.checkbox | name, id, checked, disabled; slot label | Native form-checkbox; keyboard dan label association |
| x-ui.card | padding sm/md, title optional; header/footer/slot utama | Surface/border/radius/shadow seragam |
| x-ui.badge | tone neutral/success/warning/danger/info; label | Warna status dan typography konsisten |
| x-ui.alert | tone info/success/warning/danger, dismissible; title/slot utama | role status; error mendesak boleh role alert secara eksplisit |
| x-ui.modal | name unik, title, size sm/md/lg; slot utama/footer | Alpine modal, x-trap.inert.noscroll, Escape, focus restore, x-cloak |
| x-ui.confirm-dialog | name, title, confirm-label, variant; slot deskripsi | Menyusun modal dan button; emit ui:confirmed, tidak melakukan write bisnis |
| x-ui.dropdown | align left/right; trigger dan slot item | Open/close Alpine, outside/Escape, keyboard focus; menu navigasi memakai links native |
| x-ui.tabs + x-ui.tab-panel | tabs id, items id/label; panel name/tabs-id | Tablist/tabpanel, aria-selected, ArrowLeft/Right/Home/End; state Alpine lokal |
| x-ui.table + row/cell | headers, caption optional; slot rows | Wrapper overflow-x, semantic table/th/td, style header/cell sama |
| x-ui.pagination | paginator Laravel | View pagination corporate memakai link-button/tokens yang sama |
| x-ui.empty-state | title, description, icon optional; slot action | Empty state terjemahan, tidak memalsukan data |
| x-ui.spinner | size, label | Loading status accessible, dekoratif jika label induk ada |
| x-ui.skeleton | bentuk line/card, count | Placeholder tanpa fake angka; motion-reduce |
| x-ui.toast-container | tanpa data bisnis; store ui.toasts | Toast text-only, aria-live polite, dismiss, maximum5 toast |

Kontrak field explicit: caller memberikan id yang sama ke field/control; help dan error memakai suffix -help/-error. Control receives invalid dan describedby dari halaman/view presenter yang memakai helper bersama, bukan perhitungan berulang yang berbeda. Implementasikan helper `FieldState` untuk error/help IDs dari name/id/error bag. Di form berulang, pemanggil memberi id unik.

Semua komponen meneruskan attributes yang sah melalui attribute bag, termasuk autocomplete, wire-free Alpine x-model/x-on, maxlength, dan data attributes. Default input name/value tidak berasal dari model AI. Layout/control tidak mengandung query DB atau pemeriksaan izin bisnis terselubung.

Style button/size/tone berada dalam `App\Support\Ui\ComponentStyles` berisi mapping class literal penuh; button dan link-button berbagi mapping. Mapping status tiket/publikasi ke badge tone berada dalam presenter domain, bukan di komponen badge.

Jangan menyusun `bg-${tone}-500` atau class Tailwind dari data API; class variant harus statis agar masuk build. Warna literal tidak diizinkan melalui props seperti color="#...". Slot berisi markup Blade terpercaya; model/pesan/teks staf dirender escaped/x-text/textContent, tidak melalui x-html.

## 4. Alpine dan JavaScript bersama

Install Alpine melalui npm/Vite. Core, Focus, dan Collapse dipasang pada rilis3 yang sama dan di-lock. Daftarkan plugins, data providers, dan store sebelum satu Alpine.start() di resources/js/app.js. Jangan menambahkan CDN Alpine atau start kedua pada halaman/admin.

Struktur:
- resources/js/ui/register.js mendaftarkan data providers.
- resources/js/ui/admin-shell.js, user-shell.js, modal.js, dropdown.js, tabs.js, toasts.js.
- resources/js/shared/api.js untuk fetch/envelope/error/CSRF.
- resources/js/shared/dom.js untuk safe DOM dan lookup token/type yang diizinkan.
- Modul domain chat.js/office-stage.js dan lainnya ditambahkan pada plan masing-masing; tidak mengganti bootstrap Alpine.

Alpine menyimpan state tampilan: drawer, dropdown, tabs, modal, loading, toast. API client/action server tetap menentukan autentikasi, izin, revision, dan idempotensi. Alpine.store('ui') tidak memuat transcript, role sebagai otorisasi, PII, atau credential.

Event terstandar:
- ui:modal-open / ui:modal-close: detail {name}.
- ui:confirmed: detail {name}; pemanggil domain menyiapkan konfirmasi server yang sah.
- ui:toast: detail {message, tone}; tone whitelist dan message text, tidak HTML.
- ui:auth-required / ui:session-expired: dipancarkan API client untuk penanganan session.

`apiRequest(url, {method='GET', body, signal})` hanya same-origin API proyek. Membaca meta CSRF, credentials same-origin, Accept JSON; body JSON dan X-CSRF-TOKEN untuk mutation. Mengembalikan envelope atau melempar typed ApiError {status,code,message,fields,requestId}. Bedakan fetch network error/abort dari HTTP error; tidak auto-retry write. Gunakan factory createApiClient({fetchImpl, csrfTokenReader, origin}) untuk injection pada tes Node; apiRequest adalah instance default untuk browser, dengan interface pemanggil yang tetap sama.

Shared component menjaga focus management dan lifecycle; gunakan init()/destroy() untuk menghapus listener/timer saat elemen dilepas. State persistent hanya pilihan UI nonpersonal yang memang disetujui, tidak otomatis memakai Alpine Persist.

## 5. Gallery dan aturan reuse

Plan 1 menyediakan /dev/ui, /dev/admin, /dev/user, /dev/guest pada APP_ENV local/testing; production404. Halaman berisi data sintetis, tidak memerlukan user database, dan tidak melakukan write bisnis. Memakai kontrol nyata untuk drawer/modal/dropdown/tabs/toast; form demo hanya menampilkan hasil lokal sebagai preview.

Gallery menampilkan seluruh variant tombol, validation input/help/error, badge/alert tones, table pagination demo, empty/loading states, dan dialog. Penggantian token di theme.css harus mengubah semua preview admin/user/guest dengan build yang sama.

Plan berikutnya:
1. Pilih layout dan komponen yang sudah tersedia.
2. Tambahkan action/presenter/domain UI pada slot, bukan menyalin wrapper/header/sidebar.
3. Jika kebutuhan benar-benar baru, perluas komponen/props atau tambahkan komponen bersama yang bernama jelas; dokumentasikan di UI_COMPONENTS.md setelah implementasi.
4. Jika token baru diperlukan, tambahkan pada theme.css dan spesifikasi ini; jangan memberi warna lokal diam-diam.
5. Bila komponen lintas fitur berubah, cantumkan peninjauan gallery di checklist pengguna; pengujiannya dilakukan Plan8.

## 6. Pemeriksaan konsistensi UI

Plan8 membuat scripts/check-ui-conventions.mjs dan npm script `lint:ui`, hanya memakai Node built-ins, lalu menjalankannya untuk seluruh kode Plan1–7 (lihat [kebijakan pengujian](README.md#kebijakan-pengujian)). Ini lint source, bukan bukti seluruh perilaku/accessibility benar.

Scan resources/views, resources/js, resources/css milik aplikasi. Kecualikan hanya theme.css, asset/vendor/generated dan fixtures tes yang disengaja. Pemeriksaan:
- Literal hex/rgb/hsl/oklch sebagai warna UI di luar theme.css.
- Palette Tailwind langsung seperti bg-blue-500, text-gray-700, border-red-500, bg-white/text-black; gunakan token semantik.
- Arbitrary color seperti bg-[#...] serta inline style color/background literal.
- CDN Alpine atau Alpine.start() di luar bootstrap resmi.
- Contoh class variant dinamis yang membangun nama warna dari payload.

Jangan melarang arbitrary dimensions atau background image yang sah. Jika static scan mempunyai false positive, gunakan pengecualian kecil dengan alasan dan path/line spesifik; bukan mengecualikan seluruh modul. Jangan scan docs karena dokumen ini sengaja memuat contoh warna terlarang.

Gallery review pada375x812,768x1024,1440x900: keyboard/focus, drawer, modal, outside click, Escape, validation errors, contrast body text minimal4.5:1, reduced motion, serta no flash x-cloak. Review ini dilakukan pengguna lewat checklist tiap plan dan diformalkan di Plan8; catat manual review terpisah dari tes backend.

## Referensi

[Theme Tailwind](https://tailwindcss.com/docs/theme) mendukung namespace warna dan semantic utilities. [Alpine installation](https://alpinejs.dev/essentials/installation) mendukung module bootstrap; [Focus](https://alpinejs.dev/plugins/focus) dan [Collapse](https://alpinejs.dev/plugins/collapse) menangani interaksi UI. [Blade components](https://laravel.com/framework/docs/13.x/blade#components) dan [Heroicons](https://github.com/driesvints/blade-heroicons) menjadi acuan rendering reusable.
