# Kontrak teknis bersama FTS Corporate AI

Status: Ready sebagai spesifikasi; belum diimplementasikan.
Tanggal: 5 Oktober 2026.
Acuan: [master plan](000_MASTER_PLAN.md). Dibaca bersama setiap plan teknis.

## Stack dan batas arsitektur

Laravel 13, PHP target 8.4, PostgreSQL 17, Blade, JavaScript ES modules dengan Alpine.js 3, Tailwind CSS 4, Vite 8, dan PHPUnit 12.5. Aplikasi satu perusahaan dan server-rendered multi-page seperti FTS Hotel AI. PostgreSQL merupakan penyesuaian master plan terhadap SQLite default proyek sumber; pola UI dan gateway AI tetap mengikuti sumber.

Gunakan namespace `App\Modules\Identity`, `Knowledge`, `Conversation`, `Assistant`, `Support`, dan `Experience` untuk action, query, DTO, dan kontrak. Models tetap di `app/Models`; controller, Form Request, resource, middleware, dan Policy mengikuti folder standar Laravel. Tidak membuat repository generik untuk setiap model.

Semua ID database internal memakai bigint. Conversation, AI turn, ticket draft, dan ticket juga memiliki `public_id` UUID unik sebagai ID API. Artikel menggunakan slug pada halaman dan ID bigint pada tool internal. UUID bukan pengganti pemeriksaan izin. Timestamp disimpan UTC dan dikirim ISO 8601; tampilan menggunakan timezone perusahaan `Asia/Jakarta`.

## Fondasi UI bersama

[UI_FOUNDATION.md](UI_FOUNDATION.md) menetapkan template guest/admin/user, token theme.css, props komponen Blade, bootstrap Alpine tunggal, dan event/helper bersama. Plan1 membangunnya sebelum fitur bisnis. Semua plan berikutnya memakai layout/component tersebut; perubahan warna ditambahkan di sumber tema, bukan per halaman.

Alpine mengatur state tampilan; action/Policy server tetap menentukan izin dan efek bisnis. CSS/JS dibangun sekali melalui Vite pada layout base, bukan bundle berbeda per role. Perintah npm run lint:ui dibuat dan dijalankan di Plan8.

## HTTP dan locale

Halaman: `/login`, `/password/change`, `/office`, `/office/knowledge`, `/office/hr`, `/office/it`, `/office/requests`, serta `/admin`.

Endpoint JSON berawalan `/api/v1`, didefinisikan dalam `routes/internal.php` yang dimuat oleh grup di `routes/web.php`. Dengan demikian endpoint memakai web/session/CSRF, bukan autentikasi token stateless. Middleware: auth, akun aktif, password sementara sudah diganti, dan limiter yang relevan. Request JavaScript memakai `Accept: application/json`, credentials same-origin, serta header CSRF.

Locale sah: `id`, `en`, `ja`. Urutan pilihan: query `lang` yang valid → session locale → default `id`. Nilai locale tidak valid menghasilkan 422 di JSON; halaman mengabaikannya dan memakai session/default. Simpan perubahan pilihan dalam session; input pesan AI membawa locale aktif secara eksplisit. Locale percakapan adalah locale terakhir, bukan locale tetap sejak dibuat.

Respons sukses JSON:

```json
{
  "schema_version": 1,
  "data": {},
  "meta": {"request_id": "uuid"},
  "error": null
}
```

Untuk daftar, `data` adalah array dan `meta` memuat cursor/pagination. Error memakai `data: null`, serta `error: {code, message, fields}`; fields kosong kecuali validasi. Status: 401 belum login, 403 kemampuan tidak ada, 404 resource milik pengguna/tim lain, 409 konflik/revisi/idempotensi, 419 CSRF, 422 input, 429 limiter, 503 dependensi yang diperlukan tidak tersedia. Pesan diterjemahkan. Error tidak menyertakan provider body atau secret.

Cursor riwayat memakai `after_id` ID bigint pesan, order ID ascending, maksimal 50 pesan per request, dan `meta.next_after_id`. Daftar tiket memakai pagination 20, order updated_at descending lalu ID descending.

## Identity dan scope

Satu pengguna memiliki satu departemen aktif. Peran dapat lebih dari satu: `employee`, `admin`, `editor`, `staff`. Anggota staf memiliki penugasan ke support team `hr` dan/atau `it`. Editor memiliki departemen authoring tersendiri.

Baca artikel terpublikasi melalui aplikasi/AI mengikuti audience common atau departemen pengguna; tidak ada bypass admin. CMS memberi admin kemampuan mengelola seluruh artikel, dan editor hanya artikel departemen authoring-nya. Keduanya adalah kemampuan pengelolaan yang terpisah dari pembacaan AI.

Baca conversation hanya untuk pemilik. Baca tiket hanya untuk pemohon atau staff aktif yang ditugaskan ke tim tiket. Admin tanpa penugasan staff tidak memperoleh akses tiket. Catatan internal hanya untuk staff tim, tidak untuk pemohon atau model.

## Pengetahuan dan evidence

Article memiliki owner_department. Revision menyimpan audience `common` atau `departments`; audience departments mempunyai minimal satu department yang diizinkan. Semua revisi terpublikasi memerlukan terjemahan `id/en/ja`. Konten plain text, bukan HTML bebas. Revisi published/archived immutable; perubahan membuat draft berikutnya.

Publication langsung berlaku: effective_from <= sekarang, effective_until nullable atau > effective_from dan > sekarang. Tidak ada penjadwalan publikasi masa depan pada v1. Satu revisi published per artikel; publikasi baru mengarsipkan published sebelumnya dalam transaksi.

`KnowledgeRetriever::search(User, query, locale, category?, limit=5)` menghasilkan evidence DTO: article_id, revision_id, version, locale, title, excerpt, effective_from, effective_until, dan source path server. Scope izin/status/waktu diterapkan sebelum ranking atau model menerima teks.

Evidence hasil model hanya boleh menunjuk revision yang benar-benar diberikan oleh tool pada turn itu. Server memverifikasi ulang akses sebelum respons disimpan/dikirim. Jika tidak ada evidence untuk pertanyaan kebijakan/SOP, hasilnya fallback tanpa klaim faktual perusahaan. LLM tetap memerlukan evaluasi kesesuaian isi; referensi sumber saja bukan bukti jawabannya benar.

Riwayat dengan sumber yang sudah tidak dapat diakses tidak dikirim ke model. Pada history UI, seluruh turn yang terkait sumber tersebut diganti pesan baku bahwa jawaban lama tidak tersedia; jangan mencoba membersihkan klaim sensitif dengan regex. Riwayat dengan revisi usang tetapi masih diizinkan ditandai usang dan dikeluarkan dari prompt.

## Kontrak turn dan model

Input turn: `client_turn_id` UUID, `message` 1–2000 karakter, `locale`, dan context terbatas pada scene, article_id nullable, ticket_public_id nullable. scene: reception, knowledge, hr, it, requests. Draft tiket dan PII tidak berada dalam scene context.

`ModelGateway::complete(ChatCompletionRequest, TurnDeadline)` menghasilkan `ChatCompletionResult` dengan text, tool_calls, finish_reason, usage yang tersedia; implementasi `CompatibleChatCompletionsGateway` serta fake untuk tes. Tool gateway murni tidak menentukan izin user.

Data respons turn:

```json
{
  "turn_id": "uuid",
  "status": "completed",
  "message": {"id": 12, "role": "assistant", "text": "..."},
  "sources": [],
  "ui": [],
  "actions": [],
  "operation": null
}
```

Status terminal completed/failed; processing hanya pada respons polling. Failed menyimpan pesan fallback dan kode error pada turn; tidak menghapus pesan user. `operation` hanya berisi referensi draft/tiket hasil server; tidak boleh berupa kode executable.

UI type yang diperbolehkan: source_card, ticket_list, ticket_detail, ticket_draft, ticket_confirmation. Action type: open_article, open_ticket, open_scene, review_ticket_draft. Client menggunakan named path hasil server atau ID resource, tidak mengeksekusi target arbitrary dari model.

## Tiket dan perubahan status

Tim HR: category policy_question, employee_support, other. Tim IT: account_access, hardware, software, network, other. Gunakan kode ini di enum/config dan label terjemahan; kategori tidak editable dari CMS v1.

Draft: team_code, category, subject 1–160, description 1–5000 karakter, requester dari session, revision integer mulai 1, expires_at 15 menit, conversation nullable. Edit draft menaikkan revision dan menghasilkan fingerprint baru.

Status dan actor:
- open → in_progress: staff tim; wajib assignee staff aktif tim tersebut.
- in_progress → resolved: staff tim; wajib pesan penyelesaian.
- resolved → in_progress: staff tim; wajib alasan.
- resolved → closed: pemohon atau staff tim.
- closed terminal. Pemohon meminta bantuan baru melalui tiket baru.

Pesan publik dapat dikirim pemohon/staff selama belum closed. Catatan internal hanya staff. State transition, assignment, dan audit terjadi dalam transaksi singkat dengan row lock; tidak ada HTTP model di dalam transaksi.

Idempotency key berupa UUID untuk konfirmasi draft, pesan tiket, dan transisi. Unik per actor + operation type + key. Fingerprint adalah SHA-256 JSON canonical dengan key terurut dan nilai tervalidasi, termasuk actor/resource/revision yang relevan tetapi tanpa idempotency key. Payload identik mengembalikan hasil pertama; key sama dengan payload berbeda menghasilkan 409. Tabel receipt menyimpan fingerprint dan resource result, tidak seluruh respons sensitif.

## Konfigurasi baseline

`config/corporate.php`: brand FTS Corporate AI, timezone Asia/Jakarta, supported locales, default locale, retention, serta asset scene.
`config/assistant.php`: enabled, deadline 60 detik, connect timeout 5 detik, max completion calls 7, max tool rounds 6, max tool calls per response 5, max total invocations 12, history maksimal 20 pesan dan 12000 karakter, evidence maksimal 5 kutipan masing-masing 2000 karakter, max_tokens 2048, temperature 0.3.
`config/services.php`: LOCAL_LLM_BASE_URL, LOCAL_LLM_API_KEY, LOCAL_LLM_MODEL.

Base URL wajib berupa origin/base path tanpa suffix `/v1` atau `/chat/completions`; endpoint ditambahkan tepat sekali. HTTPS wajib di production; localhost HTTP diperbolehkan local/testing. Optional reasoning_effort default tidak dikirim.

Limit default: login 5/menit per email-normalized+IP; AI 10/menit per user serta 60/menit per IP sebagai backstop; write tiket 20/menit per user. Budget concurrent AI per user satu; lease percakapan 75 detik, deadline 60 detik, client timeout 70 detik. Provider POST tidak otomatis di-retry pada v1; retry user memakai client_turn_id yang sama.

## Verifikasi dan referensi

Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), Plan1–7 hanya implementasi dan menutup tahap dengan checklist untuk pengguna. Plan8 membangun dan menjalankan pemeriksaan dari root proyek: `php artisan test --compact`, `vendor/bin/pint --test`, `npm run lint:ui`, `npm run test:ui`, dan `npm run build`. Windows dapat menjalankan Pint melalui `php vendor/bin/pint --test`. Feature/integration memakai database PostgreSQL testing terpisah; jangan menunjuk database development/production. Provider nyata tidak dipanggil CI.

Referensi framework: [routing](https://laravel.com/framework/docs/13.x/routing), [authorization](https://laravel.com/framework/docs/13.x/authorization), [HTTP client](https://laravel.com/framework/docs/13.x/http-client), [locking/transaksi](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking). Referensi menjelaskan primitives; kontrak di atas adalah keputusan proyek.
