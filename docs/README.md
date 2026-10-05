# Plan teknis FTS Corporate AI

Folder ini berisi delapan plan teknis yang dapat dikerjakan AI satu per satu. Setiap plan menentukan dependensi, paket atau skema, endpoint, urutan pekerjaan, pengujian, dan syarat selesai. Status implementasi tiap plan tercantum pada tabel di bawah.

Baca [master plan](000_MASTER_PLAN.md) untuk tujuan produk dan [kontrak teknis bersama](TECHNICAL_CONTRACTS.md) untuk interface yang dipakai antar tahap. [Fondasi UI](UI_FOUNDATION.md) menetapkan tema, template, dan komponen reusable yang dibangun Plan1. Referensi sumber adalah [analisis FTS Hotel AI](../../ANALISIS_FTS_HOTEL_AI.md) dan kode di `../fts-hotel-ai` relatif terhadap root proyek.

## Urutan pengerjaan

| Plan | Dokumen | Hasil | Dependensi | Status |
|---|---|---|---|---|
| 1 | [Setup](PLAN_01_SETUP.md) | Laravel/PostgreSQL, Tailwind/Alpine, template guest/admin/user, tema, dan komponen | Master + kontrak | Implemented |
| 2 | [Akun dan akses](PLAN_02_IDENTITY_AND_ACCESS.md) | Login, provisioning, departemen, role, staff/editor assignment, dan audit | 1 | Implemented |
| 3 | [Virtual office](PLAN_03_VIRTUAL_OFFICE.md) | Scene kantor, karakter, navigasi, branding, dan tiga bahasa | 2 | Ready |
| 4 | [CMS pengetahuan](PLAN_04_KNOWLEDGE_CMS.md) | Artikel/revisi/terjemahan, publikasi, audience, dan retrieval manual | 3 | Ready |
| 5 | [Percakapan dan AI](PLAN_05_CONVERSATION_AND_AI.md) | Chat tersimpan, gateway seperti hotel, sumber jawaban, lease, dan recovery | 4 | Ready |
| 6 | [Tiket HR dan IT](PLAN_06_HR_IT_TICKETS.md) | Draft/konfirmasi, antrean staf, pesan, status, notifikasi, dan idempotensi | 5 | Ready |
| 7 | [Tool AI dan workflow](PLAN_07_AI_TOOLS_AND_WORKFLOWS.md) | Tool calling dan perjalanan SOP sampai tiket/balasan staf | 6 | Ready |
| 8 | [Kualitas dan rilis](PLAN_08_QUALITY_AND_RELEASE.md) | Seluruh pengujian Plan1–7, CI/lint, aset visual final, evaluasi model, retensi, readiness, staging/deployment artifacts, dan pilot | 7 | Ready |

Master plan bernomor000 merupakan acuan produk, bukan Plan1. Penyebutan "Plan1", "Plan2", dan seterusnya mengacu ke tabel di atas. Urutan lama010/020/... diganti oleh dokumen aktual PLAN_01/...; file010/020 tersebut sebelumnya hanya nama yang direncanakan dan tidak pernah dibuat.

## Kebijakan pengujian

Keputusan pengguna (5 Oktober 2026): Plan1–7 fokus implementasi. Selama tahap tersebut implementer **tidak menulis maupun menjalankan** tes otomatis (PHPUnit, Node test), lint/format check (lint:ui, Pint --test), CI, atau review browser. Perintah yang diperlukan agar aplikasi dapat dipakai tetap boleh dijalankan: composer/npm install, key:generate, migrate, seed, dan npm run build.

Setiap plan ditutup dengan **checklist untuk pengguna**: langkah konkret yang perlu dicoba pengguna dan hal yang perlu diklarifikasi. Checklist ditulis pada catatan hasil implementasi plan dan disampaikan kepada pengguna di akhir pekerjaan.

Semua pengujian (infrastruktur tes, CI, lint UI, katalog skenario Plan1–7, review UI, evaluasi model) dikerjakan di [Plan8](PLAN_08_QUALITY_AND_RELEASE.md). Skenario yang semula ada di tiap plan dipindahkan ke [katalog pengujian Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan); tambahkan skenario baru di sana bila sebuah plan menemukan risiko yang perlu diuji.

## Instruksi untuk AI saat mengerjakan satu plan

1. Baca README ini, master plan, kontrak bersama, fondasi UI, dokumen plan yang diminta, serta hasil implementasi dependensinya.
2. Periksa kondisi kode dan aturan proyek yang berlaku. Status dokumen tidak menggantikan bukti kode; pastikan prasyarat tahap benar-benar tersedia.
3. Implementasikan plan yang diminta dengan interface serta keputusan yang telah ditetapkan. Paket/version API diverifikasi terhadap versi installed/lockfile, bukan ditebak dari framework terbaru.
4. Pertahankan docs ketika memasang scaffold; Plan1 menjelaskan cara bekerja pada root yang sudah berisi dokumentasi.
5. Ikuti kebijakan pengujian di atas: Plan1–7 tidak menulis/menjalankan tes; Plan8 menjalankan seluruh pengujian dengan database testing terpisah, data sintetis, dan fake model.
6. Catat perubahan, perintah setup yang dijalankan, checklist untuk pengguna, dan kendala pada bagian hasil implementasi plan.
7. Perbarui status plan (Implemented untuk Plan1–7, Done setelah verifikasi Plan8) dan tabel ini berdasarkan hasil nyata. Sampaikan hasil, checklist pengguna, dan plan berikutnya kepada pengguna; tahap berikutnya dikerjakan saat diminta.

Jika prasyarat gagal atau interface perlu berubah, jelaskan penyebabnya, kerjakan bagian yang tidak bergantung pada kendala bila ada, dan catat dampak terhadap kontrak serta plan berikutnya. Perubahan keputusan produk harus diselaraskan pada master plan; jangan meninggalkan dua spesifikasi aktif yang bertentangan.

## Contoh instruksi pengguna

```text
Kerjakan Plan 1 di docs/PLAN_01_SETUP.md untuk proyek fts-corporate-ai.
Baca docs/README.md, docs/000_MASTER_PLAN.md, docs/TECHNICAL_CONTRACTS.md,
dan docs/UI_FOUNDATION.md.
Implementasikan sampai kriteria implementasi terpenuhi tanpa menulis/menjalankan tes,
isi hasil implementasi beserta checklist untuk saya, lalu perbarui status.
Batasi pekerjaan pada Plan 1.
```

Untuk tahap selanjutnya ganti nomor dan nama file, misalnya:

```text
Kerjakan Plan 2 di docs/PLAN_02_IDENTITY_AND_ACCESS.md.
Pastikan hasil Plan 1 memenuhi prasyarat, lalu ikuti kontrak teknis bersama.
```

## Status dan penambahan plan

- Draft: spesifikasi belum lengkap.
- Ready: spesifikasi siap dikerjakan; implementasi belum selesai.
- In Progress: implementasi sedang berlangsung.
- Implemented: implementasi selesai dan checklist pengguna sudah disampaikan; verifikasi otomatis menunggu Plan8.
- Blocked: ada prasyarat yang menghambat; penyebab dan input yang dibutuhkan dicatat.
- Done: implementasi selesai dan skenario tahap tersebut lulus verifikasi Plan8 (untuk Plan8: seluruh pemeriksaannya selesai).

Gunakan [template teknis](PLAN_TEMPLATE.md) untuk plan tambahan. Nama file `PLAN_NN_NAMA_TAHAP.md`; nomor berikutnya09. Bila plan perlu disisipkan, tambahkan dependency eksplisit dan ubah urutan tabel tanpa merename file yang sudah dirujuk. Jangan menyimpan credential dalam dokumen.

Deployment hostname/database/provider credentials adalah input environment untuk eksekusi, bukan alasan mengganti stack. Rencana memakai PostgreSQL sesuai master plan; FTS Hotel AI default SQLite. Pola scene Blade, gateway chat completions, dan shared action tetap menjadi acuan teknis hotel. Corporate AI menambahkan Alpine pada ES modules, template admin/user, komponen reusable, dan token Tailwind terpusat sesuai permintaan pengguna.
