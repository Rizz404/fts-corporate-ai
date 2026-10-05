# Plan 3 Virtual office dan navigasi tiga bahasa

| Metadata | Nilai |
|---|---|
| ID | 3 |
| Status | Ready |
| Dependensi | [Plan 2](PLAN_02_IDENTITY_AND_ACCESS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | Belum dikerjakan |

## Hasil yang harus tersedia

Karyawan dapat membuka reception, Knowledge Center, HR Desk, IT Helpdesk, dan My Requests sebagai halaman kantor dengan karakter, scene background, navigasi, serta panel konten. Gaya teknis mengikuti hotel-stage Blade dan stage.js sumber; isi serta ilustrasi bertema perusahaan.

## UI dan struktur

Buat:
- `resources/views/components/office-stage.blade.php`: komposisi scene di dalam x-layouts.user yang dibuat Plan1; mengisi backdrop/character/navigation/content/chat dan memakai header/language-switch yang sudah tersedia.
- `resources/views/office/{reception,knowledge,hr,it,requests}.blade.php`.
- `resources/js/office-stage.js`, `narrator.js`, `sound.js`, dan shared DOM/navigation helpers.
- `app/Modules/Experience/Queries/BuildScene.php` dan DTO SceneData berisi scene code, locale, title, intro, backdrop path, character path, menu, dan suggested topic.
- `public/assets/corporate/{reception,knowledge,hr,it,requests}.svg` serta assistant.svg. Buat ilustrasi SVG kantor dua dimensi yang konsisten dan karakter sederhana; tidak bergantung gambar remote, aset hotel bermerek, atau proses AI image generation.

Semua asset menggunakan path tetap milik server. Warna UI memakai token semantic pada theme.css sesuai [UI_FOUNDATION](UI_FOUNDATION.md); palette tidak ditulis ulang di view, JS, atau config. Ilustrasi mengikuti palet yang sama. Gunakan card/button/badge/modal serta navigation component Plan1; hindari elemen visual yang mengurangi keterbacaan chat atau panel.

Desktop minimal lebar 1024: stage 100dvh, menu kanan, karakter di sisi kiri, panel konten dan chat scroll internal. Mobile <768: menu ringkas, konten scroll normal, chat dialog layar penuh tanpa horizontal overflow. Rentang di tengah menyesuaikan dua panel tanpa memotong tombol.

## Route dan konten

GET /office → reception; /office/knowledge → knowledge; /office/hr → hr; /office/it → it; /office/requests → requests. Semua protected. Gunakan named routes `office.reception`, `office.knowledge`, `office.hr`, `office.it`, `office.requests`.

Pada tahap ini knowledge/requests memiliki empty state yang menyatakan belum ada artikel/tiket dan CTA tidak menjalankan endpoint yang belum tersedia. HR/IT menampilkan penjelasan layanan sintetis dan menu menuju knowledge/requests. Jangan menampilkan data contoh sebagai kebijakan resmi.

Slot chat menunjukkan bahwa fitur akan tersedia setelah Plan 5; tidak membuat jawaban AI pura-pura pada production. Link /admin tampil hanya bagi role dengan kemampuan admin/editor/staff; detail menu staf/CMS ditambahkan sesuai tahap.

## Branding admin

Tambahkan company_settings singleton id=1, brand_name, welcome_text jsonb berisi id/en/ja, updated_by, timestamps. Seed default FTS Corporate AI dan sambutan umum tanpa klaim kebijakan.

GET/PATCH /admin/settings hanya admin, dengan nama brand 1–160 dan welcome text tiap locale maksimum 1000. Asset dan timezone tetap berasal dari config; palette hanya pada theme.css sesuai fondasi UI; form tidak menerima HTML, URL remote, atau JavaScript. Perubahan dicatat audit.

BuildScene mengambil pengaturan satu kali per request dan tidak menjalankan write. Tidak membuat caching lintas user untuk scene yang kelak memuat data personal.

## Langkah pengerjaan

1. Gunakan middleware locale Plan1 dan perluas lang/id, lang/en, lang/ja untuk label scene/admin/settings.
2. Susun office-stage pada layout user yang ada. Register state office UI sebagai Alpine.data sebelum startup; narasi/transisi tetap ES modules. Gunakan escaped Blade/x-text/textContent untuk teks dinamis, tanpa bootstrap/event handler ganda.
3. Buat ilustrasi scene/karakter dan fallback jika gambar gagal. Header/menu tetap dapat dipakai saat gambar tidak tersedia.
4. Adaptasi ide transition hotel: click link same-origin yang valid → animasi keluar maksimal 250ms → navigasi server. Tanpa JS, link tetap berfungsi. Hormati modifier click, target blank, dan reduced motion.
5. Scene mengeluarkan context JSON dari server melalui mekanisme escaping Blade yang aman; JS tidak membaca arbitrary data-user-id/departemen sebagai identitas terpercaya. Tidak menyimpan context entity lama lintas scene.
6. Language switch mempertahankan route dan query yang relevan. Switch tidak menyalin secret atau identitas resource yang belum diotorisasi.
7. Implementasikan narrator dari intro scene resmi, lang id-ID/en-US/ja-JP, memilih voice yang tersedia. Tidak ada autoplay; jika speech API/voice tidak tersedia tampilkan teks tanpa error.
8. Audio efek Web Audio dimulai hanya setelah toggle pengguna. Preferensi sound/narrator/reduced animation boleh di localStorage dalam namespace user ID; jangan menyimpan pesan/tiket/PII.
9. Tambahkan company settings/admin form dengan pembatasan akses admin.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila lima route office, office-stage, BuildScene/SceneData, asset SVG, transisi/narrator/sound, label tiga bahasa, dan company settings tersedia. Tes otomatis tidak ditulis/dijalankan di tahap ini; skenarionya ada di [katalog Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Login lalu buka kelima ruang; tanpa login harus dialihkan ke /login.
2. Ganti bahasa id/en/ja pada setiap ruang; route dan query tetap.
3. Periksa 375x812, 768x1024, 1440x900: menu, chat slot, overflow, dan keterbacaan teks.
4. Navigasi dengan keyboard saja; aktifkan reduced motion di OS dan pastikan animasi berkurang.
5. Aktifkan narrator dan suara secara manual; tidak ada autoplay.
6. Hapus/rename satu SVG sementara untuk melihat fallback gambar.
7. Ubah brand dan welcome text di /admin/settings; isi dengan teks berisi tag HTML dan pastikan tampil sebagai teks.
8. Klarifikasi: gaya ilustrasi kantor/karakter dan teks sambutan resmi.

## Catatan hasil implementasi

Belum dikerjakan. Simpan daftar asset/route dan checklist untuk pengguna; screenshot bila tersedia untuk memudahkan review scene.
