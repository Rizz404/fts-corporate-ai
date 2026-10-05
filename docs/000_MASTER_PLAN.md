# Corporate AI — Virtual Office untuk Karyawan

Tanggal: 5 Oktober 2026.
Status: acuan perencanaan; belum diimplementasikan.
ID plan: 000.
Dependensi: tidak ada.

## 1. Tujuan dan batas versi pertama

Membangun website internal untuk **satu perusahaan**, dapat diakses melalui internet dengan login wajib. Pengalaman virtual office mengikuti pola FTS Hotel AI: karyawan menjelajahi ruang, berinteraksi dengan karakter AI, memperoleh informasi resmi, lalu meneruskan kebutuhan ke staf.

Dasar rancangan adalah [analisis FTS Hotel AI](../../ANALISIS_FTS_HOTEL_AI.md), dengan domain baru berupa **pengetahuan perusahaan dan tiket bantuan HR/IT**.

**Alur utama:** login → memilih ruang → bertanya → membaca jawaban beserta sumber → mengonfirmasi tiket bila diperlukan → menerima balasan staf → menyelesaikan permintaan.

Keputusan yang sudah ditetapkan:

- Proyek Laravel baru bernama `corporate-ai`, terpisah dari `fts-hotel-ai`.
- Akun karyawan dibuat oleh admin.
- Pengetahuan ditulis dan diterbitkan melalui CMS.
- Konten tersedia untuk semua karyawan atau dibatasi per departemen.
- Bahasa UI, konten resmi, dan respons AI: Indonesia, Inggris, Jepang.
- Koneksi AI mengikuti adapter kompatibel chat completions FTS Hotel AI.

Versi pertama tidak mencakup pengajuan cuti, payroll, absensi, booking ruang, upload dokumen, SSO, integrasi HRIS, atau multi-perusahaan.

**Catatan penamaan folder:** atas permintaan pengguna, folder proyek menggunakan `fts-corporate-ai`. Penyebutan `corporate-ai` pada rencana awal merujuk pada proyek yang sama.

## 2. Pengalaman pengguna dan fitur

### Virtual office

| Ruang | Fungsi |
|---|---|
| Reception | Sambutan, navigasi, dan bantuan menentukan kebutuhan |
| Knowledge Center | Pencarian SOP, kebijakan, FAQ, dan artikel resmi |
| HR Desk | Informasi HR dan pengajuan tiket bantuan HR |
| IT Helpdesk | Panduan IT dan pengajuan tiket bantuan IT |
| My Requests | Daftar tiket, status, dan percakapan dengan staf |

Gunakan ilustrasi kantor dua dimensi, karakter AI, transisi halaman, serta chat dock yang bertahan saat navigasi. Implementasi memakai Blade dan JavaScript; tidak memerlukan mesin 3D.

Pada perangkat kecil, navigasi ruang menjadi menu sederhana dan chat dapat dibuka penuh. Semua fungsi harus dapat digunakan melalui keyboard. Animasi mengikuti preferensi reduced motion; narasi memakai browser speech synthesis bila tersedia dan hanya berjalan setelah diaktifkan pengguna.

### Pengetahuan dan AI

- AI memahami ruang, artikel, dan tiket yang sedang dibuka melalui konteks terstruktur yang diverifikasi server.
- Jawaban tentang perusahaan menggunakan artikel resmi yang dapat diakses pengguna.
- Tampilkan kartu sumber berisi judul, versi, tanggal berlaku, dan tautan artikel.
- Jika sumber tidak ditemukan, tidak berlaku, atau pertanyaan ambigu, AI meminta klarifikasi atau menawarkan tiket.
- Bahasa respons mengikuti pilihan bahasa aktif pengguna. Perubahan bahasa berlaku pada giliran berikutnya.
- Pencarian awal menggunakan pencocokan Unicode pada judul, kata kunci/alias, dan isi artikel dalam tiga bahasa, dengan ranking deterministik dan maksimal lima hasil. Embedding tidak digunakan pada versi pertama.

### Tiket dan bantuan manusia

Tiket berisi tim tujuan HR/IT, kategori, judul, deskripsi, pemohon, staf penanggung jawab, status, serta riwayat pesan dan perubahan.

- AI menyiapkan draft tiket; karyawan melihat ringkasan dan menekan tombol konfirmasi sebelum tiket dibuat.
- Form biasa dan alur AI memakai validasi serta action domain yang sama.
- Status: `open → in_progress → resolved → closed`. Staf dapat mengembalikan `resolved` ke `in_progress` saat masalah belum selesai.
- Pemohon dapat membalas tiket yang belum `closed`; staf terkait menangani assignment, balasan, dan perubahan status.
- Handover diwujudkan sebagai tiket dengan percakapan staf di halaman tiket. Chat AI tetap tersedia untuk pencarian informasi.
- Notifikasi tersedia di dalam aplikasi untuk tiket baru, assignment, balasan, dan perubahan status. Email dan WhatsApp ditunda.
- Polling pesan berjalan setiap lima detik hanya ketika halaman tiket aktif, menggunakan cursor ID pesan.

## 3. Fondasi teknis, data, dan hak akses

### Arsitektur

Gunakan **monolit modular Laravel 13**, PHP 8.4, PostgreSQL, Blade, JavaScript ES modules dengan Alpine.js 3, Tailwind CSS 4, dan Vite 8. Kunci dependensi melalui lockfile.

Pisahkan tanggung jawab menjadi Identity, Knowledge, Conversation, Assistant, Support, dan Experience. Controller menangani HTTP; Form Request menangani input; Policy menangani izin; action domain menangani publikasi, pembuatan tiket, dan transisi status. Laravel menyediakan Policy untuk mengatur akses terhadap resource. [Dokumentasi authorization](https://laravel.com/framework/docs/13.x/authorization).

Plan1 menyiapkan layout guest/admin/user, komponen reusable, dan satu tema Tailwind sebelum fitur bisnis. Seluruh tahap mengikuti [fondasi UI](UI_FOUNDATION.md); Alpine menangani interaksi ringan, sementara action/Policy server tetap menangani aturan bisnis.

### Data dan akses

Entitas utama: pengguna, departemen, peran, artikel beserta revisi/terjemahannya, percakapan, pesan, AI turn, draft tindakan, tiket, pesan tiket, notifikasi, dan audit perubahan.

| Peran | Hak akses |
|---|---|
| Karyawan | Artikel umum/departemennya, percakapan sendiri, dan tiket sendiri |
| Staf HR/IT | Hak karyawan ditambah antrean dan tiket tim yang ditugaskan |
| Editor | Mengelola draft artikel untuk departemen yang ditugaskan |
| Admin | Mengelola akun, departemen, peran, konfigurasi brand, dan publikasi artikel |

Satu akun dapat memiliki beberapa peran. Peran admin tidak otomatis memberi akses ke percakapan pribadi atau tiket; akses tiket memerlukan penugasan sebagai staf tim terkait.

CMS menggunakan alur `draft → published → archived`. Publikasi mensyaratkan terjemahan resmi ketiga bahasa pada revisi yang sama. Perubahan artikel terpublikasi menghasilkan revisi baru. Hanya revisi terpublikasi yang sedang berlaku dan sesuai izin pengguna yang boleh masuk hasil pencarian.

Izin diperiksa pada halaman, endpoint, tool, dan pengambilan sumber. Riwayat atau sumber yang sudah kehilangan izin tidak diteruskan ke model pada giliran berikutnya.

### Kontrak chat dan tool

Endpoint JSON same-origin memakai session login dan CSRF, dengan awalan `/api/v1`. Sediakan operasi untuk percakapan, pesan, history, pencarian artikel, draft tiket, konfirmasi tiket, pesan tiket, dan status tiket.

Respons chat memuat `schema_version`, `turn_id`, pesan, referensi sumber, kartu UI, tindakan yang tersedia, dan error terstruktur. Kartu serta tujuan navigasi dibuat server dari tipe yang diizinkan; model tidak mengirim HTML atau URL bebas.

Tool awal:

- `search_knowledge`
- `get_article`
- `list_my_tickets`
- `get_ticket`
- `prepare_ticket`

Pembuatan tiket final berlangsung melalui endpoint konfirmasi, bukan keputusan model. Draft terikat pada pengguna, payload, revisi, dan masa berlaku 15 menit. Konfirmasi memakai idempotency key agar retry mengembalikan tiket yang sama.

### Integrasi AI

Pertahankan setting `LOCAL_LLM_BASE_URL`, `LOCAL_LLM_API_KEY`, dan `LOCAL_LLM_MODEL`, dengan endpoint `/v1/chat/completions`. Nama setting mengikuti proyek referensi dan tidak menentukan lokasi hosting model.

Di checkout referensi, `.env` tidak tersedia dan nilai AI pada `.env.example` kosong. Karena itu, yang disamakan adalah **kontrak koneksi**; endpoint, model, dan secret aktual menjadi input deployment.

- Jangan mengirim parameter khusus seperti `reasoning_effort` kecuali diaktifkan melalui konfigurasi kemampuan provider.
- Default budget satu giliran: 60 detik, maksimal enam putaran tool, satu giliran aktif per percakapan.
- Batasi history dan kutipan sumber yang dikirim; identitas akun dan catatan internal staf tidak masuk prompt.
- Tangani timeout, respons malformed, dan tool tidak dikenal dengan fallback yang jelas. Timeout serta fake HTTP dapat menggunakan HTTP client Laravel. [Dokumentasi HTTP client](https://laravel.com/framework/docs/13.x/http-client).
- Kegagalan AI tetap memungkinkan pencarian artikel, form tiket, dan balasan staf.
- Render teks, sumber, dan pesan staf secara aman; jangan menyisipkan konten dinamis melalui HTML bebas.

## 4. Tahapan implementasi

Tahapan produk dipecah menjadi delapan plan teknis yang dapat dikerjakan AI satu per satu. Urutan, kontrak interface, dan statusnya tersedia di [indeks plan](README.md) dan [kontrak teknis bersama](TECHNICAL_CONTRACTS.md).

1. **[Plan 1 Setup](PLAN_01_SETUP.md):** scaffold Laravel, paket Tailwind/Alpine, PostgreSQL, template guest/admin/user, tema, komponen reusable, dan environment.
2. **[Plan 2 Akun dan akses](PLAN_02_IDENTITY_AND_ACCESS.md):** provisioning karyawan, login, password sementara, departemen, peran, dan assignment staf/editor.
3. **[Plan 3 Virtual office](PLAN_03_VIRTUAL_OFFICE.md):** scene kantor, karakter, navigasi, branding, dan tiga bahasa.
4. **[Plan 4 CMS pengetahuan](PLAN_04_KNOWLEDGE_CMS.md):** artikel/revisi/terjemahan, publikasi, audience, dan pencarian manual.
5. **[Plan 5 Percakapan dan AI](PLAN_05_CONVERSATION_AND_AI.md):** history, gateway model, jawaban bersumber, lease, serta recovery.
6. **[Plan 6 Tiket HR dan IT](PLAN_06_HR_IT_TICKETS.md):** draft/konfirmasi, antrean staf, assignment, pesan, status, notifikasi, dan idempotensi.
7. **[Plan 7 Tool AI dan workflow](PLAN_07_AI_TOOLS_AND_WORKFLOWS.md):** tool calling dan alur SOP menuju tiket sampai balasan staf.
8. **[Plan 8 Kualitas dan rilis](PLAN_08_QUALITY_AND_RELEASE.md):** seluruh pengujian dan CI, evaluasi, retensi, monitoring, staging/deployment artifacts, dan pilot.

Status Ready pada dokumen berarti spesifikasi siap dikerjakan, bukan aplikasi telah dibuat. Plan teknis tidak mengubah batas produk versi pertama.

Default deployment adalah Linux dengan Nginx/PHP-FPM, PostgreSQL, worker queue database, scheduler, dan HTTPS. Gunakan staging terpisah, `APP_DEBUG=false`, secret di environment deployment, backup harian, serta uji pemulihan. Tidak ada migrasi data hotel ke proyek baru. [Pedoman deployment Laravel](https://laravel.com/framework/docs/13.x/deployment).

Pantau latency/error AI, keberhasilan pencarian, tiket ganda, antrean belum ditangani, dan kegagalan notifikasi. Log operasional menggunakan ID korelasi tanpa menyimpan kredensial atau isi prompt mentah.

## 5. Pengujian dan kriteria selesai

Plan1–7 fokus implementasi dan ditutup dengan checklist untuk pengguna; seluruh pengujian di bawah dibangun dan dijalankan pada Plan8 (lihat [kebijakan pengujian](README.md#kebijakan-pengujian)).

- **Akses:** pengguna anonim ditolak; karyawan tidak dapat membaca tiket orang lain atau sumber departemen lain melalui halaman, API, tool, maupun manipulasi ID.
- **Pengetahuan:** draft, arsip, dan revisi tidak berlaku tidak digunakan AI; sumber menunjukkan versi yang benar dalam ketiga bahasa.
- **AI:** uji pertanyaan tanpa sumber, ambigu, prompt injection, tool invalid, timeout, dan batas iterasi menggunakan fake provider.
- **Tiket:** submit ulang atau dua konfirmasi bersamaan menghasilkan satu tiket; transisi status ilegal ditolak; balasan staf terbaca pemohon melalui endpoint history.
- **Frontend:** uji teks berbahaya, perpindahan ruang, konteks lama, pergantian bahasa, keyboard, mobile, dan reduced motion.
- **Database:** jalankan pengujian transaksi dan concurrency pada PostgreSQL.
- **Evaluasi model nyata:** gunakan sedikitnya 30 skenario per bahasa. Target pilot: minimal 90% jawaban sesuai sumber; seluruh kasus akses terlarang dan tindakan tanpa konfirmasi harus ditolak.
- **CI:** lint/format check, tes backend, build frontend, dan audit dependensi harus lulus.

Default retensi: percakapan AI 90 hari, tiket beserta pesannya 365 hari setelah ditutup, dan audit 365 hari; seluruhnya dapat dikonfigurasi.

Produk dinyatakan siap ketika karyawan dapat menyelesaikan alur **pertanyaan SOP → sumber resmi → tiket terkonfirmasi → balasan staf → penyelesaian**, dalam tiga bahasa, dengan izin yang benar dan tanpa tiket ganda.
