# Plan 3 Virtual office dan navigasi tiga bahasa

| Metadata | Nilai |
|---|---|
| ID | 3 |
| Status | Done |
| Dependensi | [Plan 2](PLAN_02_IDENTITY_AND_ACCESS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | [Catatan hasil implementasi](#catatan-hasil-implementasi) |

## Hasil yang harus tersedia

Karyawan dapat membuka reception, Knowledge Center, HR Desk, IT Helpdesk, dan My Requests sebagai halaman kantor dengan karakter, scene background, navigasi, serta panel konten. Gaya teknis mengikuti hotel-stage Blade dan stage.js sumber; isi bertema perusahaan.

**Keputusan pengguna (5 Oktober 2026):** gambar scene dan karakter memakai **gambar placeholder acak** dulu. Ilustrasi kantor, karakter, logo, dan favicon final tidak dibuat di tahap ini; semuanya dikerjakan di akhir proyek ([Plan8, aset visual final](PLAN_08_QUALITY_AND_RELEASE.md#aset-visual-final)). Plan3 cukup menyiapkan mekanisme agar aset dapat diganti tanpa mengubah view/JS.

## UI dan struktur

Buat:
- `resources/views/components/office-stage.blade.php`: komposisi scene di dalam x-layouts.user yang dibuat Plan1; mengisi backdrop/character/navigation/content/chat dan memakai header/language-switch yang sudah tersedia.
- `resources/views/office/{reception,knowledge,hr,it,requests}.blade.php`.
- `resources/js/office-stage.js`, `narrator.js`, `sound.js`, dan shared DOM/navigation helpers.
- `app/Modules/Experience/Queries/BuildScene.php` dan DTO SceneData berisi scene code, locale, title, intro, backdrop path, character path, menu, dan suggested topic.
- Resolver aset scene di `config/corporate.php` (`scenes`): setiap scene (reception, knowledge, hr, it, requests) dan karakter assistant mempunyai URL gambar yang dibangun server. BuildScene hanya membaca URL dari resolver ini; view/JS tidak menulis URL gambar sendiri.

### Gambar placeholder sementara

- Mode default `SCENE_IMAGE_SOURCE=placeholder`: URL gambar acak dari layanan placeholder (default `https://picsum.photos`), dibuat server dengan **seed tetap per scene/karakter** (mis. `/seed/corporate-reception/1600/900`) agar gambar tidak berganti setiap refresh. Base URL dapat diganti melalui env `SCENE_PLACEHOLDER_BASE_URL`.
- Mode `SCENE_IMAGE_SOURCE=local`: memakai file di `public/assets/corporate/{scene}.{ext}` dan `assistant.{ext}`. Mode ini dipakai Plan8 setelah aset final tersedia; tidak perlu mengubah view/JS.
- Gambar placeholder bersifat dekoratif (`alt=""`), dimuat lazy, dan tidak boleh menjadi syarat fungsi: bila gagal dimuat (offline/diblokir), tampilkan fallback latar `bg-surface-muted` dan ikon karakter sehingga menu, panel, dan chat tetap dapat dipakai.
- Placeholder remote hanya untuk pengembangan/pilot internal. Tidak mengirim data pengguna, query, atau identitas dalam URL gambar. Production tidak boleh rilis dengan mode placeholder (gate Plan8).
- Logo memakai brand mark teks dari Plan1 (`x-navigation.brand`); favicon bawaan dibiarkan sampai Plan8.

Warna UI memakai token semantic pada theme.css sesuai [UI_FOUNDATION](UI_FOUNDATION.md); palette tidak ditulis ulang di view, JS, atau config. Agar teks tetap terbaca di atas foto acak, panel konten memakai surface solid/semi-solid dan overlay token `bg-overlay` pada area teks di atas gambar. Gunakan card/button/badge/modal serta navigation component Plan1; hindari elemen visual yang mengurangi keterbacaan chat atau panel.

Desktop minimal lebar 1024: stage 100dvh, menu kanan, karakter di sisi kiri, panel konten dan chat scroll internal. Mobile <768: menu ringkas, konten scroll normal, chat dialog layar penuh tanpa horizontal overflow. Rentang di tengah menyesuaikan dua panel tanpa memotong tombol.

## Route dan konten

GET /office → reception; /office/knowledge → knowledge; /office/hr → hr; /office/it → it; /office/requests → requests. Semua protected. Gunakan named routes `office.reception`, `office.knowledge`, `office.hr`, `office.it`, `office.requests`.

Pada tahap ini knowledge/requests memiliki empty state yang menyatakan belum ada artikel/tiket dan CTA tidak menjalankan endpoint yang belum tersedia. HR/IT menampilkan penjelasan layanan sintetis dan menu menuju knowledge/requests. Jangan menampilkan data contoh sebagai kebijakan resmi.

Slot chat menunjukkan bahwa fitur akan tersedia setelah Plan 5; tidak membuat jawaban AI pura-pura pada production. Link /admin tampil hanya bagi role dengan kemampuan admin/editor/staff; detail menu staf/CMS ditambahkan sesuai tahap.

## Branding admin

Tambahkan company_settings singleton id=1, brand_name, welcome_text jsonb berisi id/en/ja, updated_by, timestamps. Seed default FTS Corporate AI dan sambutan umum tanpa klaim kebijakan.

GET/PATCH /admin/settings hanya admin, dengan nama brand 1–160 dan welcome text tiap locale maksimum 1000. Upload logo/gambar tidak disediakan. Asset dan timezone tetap berasal dari config; palette hanya pada theme.css sesuai fondasi UI; form tidak menerima HTML, URL remote, atau JavaScript. Perubahan dicatat audit.

BuildScene mengambil pengaturan satu kali per request dan tidak menjalankan write. Tidak membuat caching lintas user untuk scene yang kelak memuat data personal.

## Langkah pengerjaan

1. Gunakan middleware locale Plan1 dan perluas lang/id, lang/en, lang/ja untuk label scene/admin/settings.
2. Susun office-stage pada layout user yang ada. Register state office UI sebagai Alpine.data sebelum startup; narasi/transisi tetap ES modules. Gunakan escaped Blade/x-text/textContent untuk teks dinamis, tanpa bootstrap/event handler ganda.
3. Pasang resolver gambar placeholder/local beserta fallback jika gambar gagal dimuat. Header/menu tetap dapat dipakai saat gambar tidak tersedia. Jangan menghabiskan waktu pada desain ilustrasi; itu pekerjaan Plan8.
4. Adaptasi ide transition hotel: click link same-origin yang valid → animasi keluar maksimal 250ms → navigasi server. Tanpa JS, link tetap berfungsi. Hormati modifier click, target blank, dan reduced motion.
5. Scene mengeluarkan context JSON dari server melalui mekanisme escaping Blade yang aman; JS tidak membaca arbitrary data-user-id/departemen sebagai identitas terpercaya. Tidak menyimpan context entity lama lintas scene.
6. Language switch mempertahankan route dan query yang relevan. Switch tidak menyalin secret atau identitas resource yang belum diotorisasi.
7. Implementasikan narrator dari intro scene resmi, lang id-ID/en-US/ja-JP, memilih voice yang tersedia. Tidak ada autoplay; jika speech API/voice tidak tersedia tampilkan teks tanpa error.
8. Audio efek Web Audio dimulai hanya setelah toggle pengguna. Preferensi sound/narrator/reduced animation boleh di localStorage dalam namespace user ID; jangan menyimpan pesan/tiket/PII.
9. Tambahkan company settings/admin form dengan pembatasan akses admin.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila lima route office, office-stage, BuildScene/SceneData, resolver gambar placeholder/local dengan fallback, transisi/narrator/sound, label tiga bahasa, dan company settings tersedia. Ilustrasi/logo final tidak termasuk kriteria tahap ini. Tes otomatis tidak ditulis/dijalankan di tahap ini; skenarionya ada di [katalog Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Login lalu buka kelima ruang; tanpa login harus dialihkan ke /login.
2. Ganti bahasa id/en/ja pada setiap ruang; route dan query tetap.
3. Periksa 375x812, 768x1024, 1440x900: menu, chat slot, overflow, dan keterbacaan teks.
4. Navigasi dengan keyboard saja; aktifkan reduced motion di OS dan pastikan animasi berkurang.
5. Aktifkan narrator dan suara secara manual; tidak ada autoplay.
6. Matikan internet (atau blokir domain placeholder) lalu muat ulang ruang; fallback latar/ikon harus tampil dan semua fungsi tetap jalan. Pastikan gambar tidak berganti saat refresh.
7. Ubah brand dan welcome text di /admin/settings; isi dengan teks berisi tag HTML dan pastikan tampil sebagai teks.
8. Klarifikasi: teks sambutan resmi. Gaya ilustrasi, karakter, dan logo diputuskan nanti di Plan8.

## Catatan hasil implementasi

### Hasil implementasi (5 Oktober 2026)

**Status: Implemented.** Verifikasi otomatis menunggu Plan8.

**Route**: `GET /office` (`office.reception`), `/office/knowledge`, `/office/hr`, `/office/it`, `/office/requests` dalam middleware `account`; `GET|PATCH /admin/settings` (admin, via `CompanySettingPolicy`).

**Experience module** (`app/Modules/Experience`)
- `Enums\SceneCode` (route name, ikon, label), `Data\SceneData`, `Queries\BuildScene` (read-only; menu lima ruang, intro terjemahan, intro reception dari welcome text perusahaan bila diisi, topik saran, context `{scene, article_id, ticket_public_id}`).
- `Support\SceneImages`: resolver gambar. `SCENE_IMAGE_SOURCE=placeholder` → `{SCENE_PLACEHOLDER_BASE_URL}/seed/corporate-{scene}/1600/900` dan `.../corporate-assistant/600/800` (default picsum.photos, seed tetap); `local` → `public/assets/corporate/{name}.{SCENE_IMAGE_EXTENSION}`.
- `Support\CompanySettings` (scoped, satu query per request, default bila tabel belum ada) dan middleware `ApplyCompanySettings` yang menerapkan brand name ke `config('corporate.brand.name')` sehingga semua layout memakai nama dari admin tanpa query di komponen.

**Data**: `company_settings` (brand_name, welcome_text jsonb id/en/ja, updated_by, timestamps), baris id=1 di-seed `ReferenceSeeder`. Form settings hanya teks (menolak `<`, `>`, `javascript:`, URL), maksimal 160/1000 karakter, perubahan diaudit `company_settings.updated`.

**UI**
- `x-office-stage` (`resources/views/components/office-stage.blade.php`) menyusun `x-layouts.user`: backdrop gambar + overlay, kartu karakter dengan fallback ikon, panel judul/intro/topik, tombol narrator dan suara, slot konten, slot chat (pemberitahuan "segera tersedia"), context JSON `#office-context` lewat `@json`.
- View `office/{reception,knowledge,hr,it,requests}`: reception berisi kartu ruang; HR/IT memakai `_service-desk` (layanan umum + catatan bahwa aturan resmi ada di Pusat Pengetahuan); knowledge dan requests berisi empty state, CTA permintaan nonaktif.
- JS: `resources/js/office-stage.js` (`officeStage`: narrator, suara, transisi keluar 200ms untuk link `/office*` same-origin, menghormati modifier/target/download/reduced motion, reset saat bfcache; `sceneImage`: fallback walau error terjadi sebelum Alpine aktif), `narrator.js` (speechSynthesis, pilih voice sesuai id-ID/en-US/ja-JP, tidak autoplay), `sound.js` (Web Audio setelah toggle), `shared/navigation.js`. Didaftarkan di `ui/register.js`; preferensi suara di localStorage `fts-office:{userId}:sound`.
- Menu Pengaturan admin aktif; halaman `admin/settings/edit` memakai tabs per bahasa (tab yang berisi error terbuka otomatis).
- Halaman office sementara Plan2 dihapus.

**Perintah setup yang dijalankan**: `php artisan migrate`, `php artisan db:seed`, `php artisan view:cache`, `php artisan route:list`, `npm run build`.

**Penyimpangan dan keputusan**
- Gambar memakai placeholder acak (keputusan pengguna); ilustrasi/logo final di Plan8.
- Topik saran ditampilkan sebagai label saja; menjadi pertanyaan ke chat di Plan5.
- Hanya preferensi suara yang disimpan; narrator tidak pernah berjalan otomatis sehingga tidak perlu disimpan.

**Checklist untuk pengguna** — lihat bagian "Kriteria implementasi dan verifikasi pengguna" (langkah 1–8).
