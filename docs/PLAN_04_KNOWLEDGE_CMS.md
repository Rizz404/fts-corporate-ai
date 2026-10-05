# Plan 4 CMS SOP dan pencarian pengetahuan

| Metadata | Nilai |
|---|---|
| ID | 4 |
| Status | Ready |
| Dependensi | [Plan 3](PLAN_03_VIRTUAL_OFFICE.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | Belum dikerjakan |

## Hasil yang harus tersedia

Editor/admin dapat membuat artikel resmi tiga bahasa, menerbitkan revisi, mengarsipkan, dan mengatur audience. Karyawan dapat mencari/membaca artikel yang berlaku sesuai departemennya tanpa AI. KnowledgeRetriever siap digunakan Plan 5 dan 7.

## Skema dan invariant

- knowledge_articles: id, slug unique, owner_department_id, created_by, timestamps.
- knowledge_revisions: id, article_id, version integer, status draft/published/archived, audience common/departments, category general/hr/it/onboarding, effective_from, effective_until nullable, created_by, published_by nullable, published_at nullable, timestamps. Unique(article_id, version); partial unique index article_id WHERE status='published'.
- knowledge_translations: id, revision_id, locale id/en/ja, title max 180, body text max 20000, tags jsonb, aliases jsonb, search_title/search_body/search_keywords text turunan. Unique(revision_id, locale).
- knowledge_revision_department: revision_id + department_id unique.

Tag/alias masing-masing array maksimal 50 string, setiap string 1–80; normalisasi Unicode NFKC dan lowercase melalui intl/mbstring. Draft boleh belum lengkap. Publish memerlukan title/body nonkosong tiga locale, audience departments minimal satu departemen aktif, dan tanggal sesuai kontrak.

Versi diberikan server dalam transaksi yang mengunci article. Draft tunggal per artikel: jika draft sudah ada, edit draft tersebut. Revisi published/archived tidak dapat diubah; action editPublished membuat clone draft berikutnya. Publication mengarsipkan published lama dan mempublikasi draft secara atomik. Archive menghapus artikel dari retrieval tetapi mempertahankan record historis.

## Controller dan route

CMS:
- GET/POST /admin/knowledge; GET /admin/knowledge/create.
- GET /admin/knowledge/{article}/edit; PATCH /admin/knowledge/{article}/draft.
- POST /admin/knowledge/{article}/publication untuk publish; DELETE path publication untuk archive.
- POST /admin/knowledge/{article}/revisions untuk membuat draft dari published.
- GET /admin/knowledge/{article}/revisions untuk history.

Editor boleh membuat/mengedit pada owner_department assignment-nya. Hanya admin menerbitkan/archive. Admin dapat mengelola semua artikel melalui CMS; hak baca aplikasi/AI tetap mengikuti audience.

Karyawan:
- GET /office/knowledge?q=...&category=...&lang=... menampilkan pencarian dan kategori.
- GET /office/knowledge/{articleSlug} menampilkan revisi published yang berlaku.
- GET /api/v1/knowledge?q=...&category=...&locale=... untuk hasil JSON.
- GET /api/v1/knowledge/{article} untuk artikel aktif terotorisasi.

Slug kebijakan ASCII lowercase/hyphen, dibuat dari input admin max120, unik dan immutable setelah artikel pertama diterbitkan. Resource tidak tersedia/tidak diizinkan menghasilkan 404. Tidak menyediakan endpoint public tanpa login atau download dokumen.

## Retrieval deterministik

Kontrak KnowledgeRetriever menggunakan actor/locale dari server. Query 1–500 karakter, category optional enum, maksimal lima evidence.

Langkah ranking yang ditetapkan:
1. Filter audience pengguna, status published, effective_from <= now, dan effective_until kosong atau > now.
2. Cari terjemahan semua locale agar alias multibahasa dapat menemukan artikel; teks yang dikembalikan selalu locale pilihan pengguna.
3. Normalisasi query NFKC/lowercase. Token Latin dipisah whitespace/punctuation; rangkaian Han/Hiragana/Katakana menjadi bigram Unicode. Hilangkan duplikasi; maksimal 12 token nonkosong. Query satu karakter memakai token tunggal.
4. Score title full phrase +30, keywords/alias full phrase +20, body full phrase +10; tiap token berbeda yang cocok memberi +3 pada title, +2 keywords, +1 body. Substring case normalized; escape wildcard SQL LIKE dan gunakan binding.
5. Untuk setiap revision ambil score maksimum antar terjemahan, hanya score>0; urut score desc, article_id asc. Implementasikan filter dan scoring di query PostgreSQL agar tidak mengambil seluruh konten ke memori PHP. Tidak menggunakan English stemming untuk teks Jepang.
6. Excerpt dari locale aktif, maksimum 2000 karakter. Resource hasil pencarian untuk UI boleh excerpt pendek 300; evidence internal mempertahankan batas 2000. Sertakan revision/version/tanggal dan named route sumber.

KnowledgePolicy memisahkan manage/create/edit/publish/viewPublished. Jangan membuat admin bypass global. Retriever dan halaman detail memakai scope serta aturan yang sama.

## Langkah pengerjaan dan area perubahan

Buat models, migrations/indexes, factory, revision/locale enums, Form Request, KnowledgePolicy, serta actions CreateArticle, SaveDraft, CloneRevision, PublishRevision, ArchiveRevision. Gunakan transaksi singkat dan audit publication/archive; jangan menyalin giant HotelPageController.

Buat CMS plain textarea tiga bahasa dengan indikator kelengkapan. Render body sebagai escaped text dengan whitespace-preserving CSS; rich HTML editor tidak dipasang.

Hubungkan hasil pencarian dan detail ke office-stage. Context detail membawa article_id sah, bukan seluruh isi artikel. Seed contoh lokal berlabel DEMO dalam tiga bahasa untuk general, HR, IT, onboarding, serta satu artikel department-only; tidak menjadi kebijakan perusahaan sungguhan.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila skema/indexes, actions, KnowledgePolicy, CMS, halaman/API karyawan, KnowledgeRetriever, dan seed DEMO tersedia sehingga pencarian manual dan sumber resmi berfungsi tanpa provider AI. Partial unique index dan scoring ditulis untuk PostgreSQL; bila lokal masih SQLite, catat bagian yang belum dapat dijalankan. Tes otomatis tidak ditulis/dijalankan di tahap ini; skenarionya ada di [katalog Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Sebagai editor buat artikel dengan draft belum lengkap; publish harus ditolak sampai tiga bahasa lengkap. Pastikan editor tidak bisa publish.
2. Sebagai admin publish, edit artikel terpublikasi (membuat draft baru), publish ulang, lalu archive; cek riwayat revisi.
3. Sebagai karyawan departemen lain, pastikan artikel department-only tidak muncul di pencarian maupun lewat URL langsung.
4. Cari dengan kata Indonesia, Inggris, dan Jepang serta alias; cek urutan hasil masuk akal.
5. Isi body dengan tag HTML/script; harus tampil sebagai teks.
6. Klarifikasi: kategori awal, siapa editor tiap departemen, dan contoh SOP nyata yang akan dipakai pilot.

## Pemakaian fondasi UI

CMS menyusun x-layouts.admin dan x-ui.tabs/tab-panel untuk tiga bahasa; gunakan field/input/textarea/select, card, table, badge, dan confirm-dialog bersama. Halaman karyawan memakai office-stage dari Plan3 di atas x-layouts.user. Publication status ke badge tone dipetakan presenter Knowledge, bukan membuat warna baru pada view. Semua warna/font/radius berasal dari UI_FOUNDATION dan theme.css.

## Catatan hasil implementasi

Belum dikerjakan. Dokumentasikan action/retriever publik, migration/indexes, contoh hasil ranking, serta checklist untuk pengguna.
