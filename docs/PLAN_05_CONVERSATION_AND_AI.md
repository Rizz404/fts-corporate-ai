# Plan 5 Percakapan dan AI berbasis sumber

| Metadata | Nilai |
|---|---|
| ID | 5 |
| Status | Ready |
| Dependensi | [Plan 4](PLAN_04_KNOWLEDGE_CMS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | Belum dikerjakan |

## Hasil yang harus tersedia

Chat AI pada virtual office dapat menjawab SOP/kebijakan dari retrieval resmi, menampilkan sumber, menyimpan history, dan pulih dari error. Tahap ini belum memakai tool domain tiket; retrieval knowledge dilakukan server sebelum completion. Registry tool disiapkan dengan implementasi kosong sampai Plan 7.

## Persistence

- conversations: id, public_id UUID unique, user_id, locale, scene_context jsonb, context_revision integer default1, last_activity_at, timestamps.
- ai_turns: id, public_id UUID unique, conversation_id, client_turn_id UUID, request_fingerprint string64, status processing/completed/failed, locale, context_snapshot jsonb, started_at, finished_at nullable, deadline_at, error_code nullable, provider/model alias nullable, prompt_version string, usage jsonb nullable. Unique(conversation_id, client_turn_id).
- conversation_messages: id, conversation_id, turn_id nullable FK ai_turns, role user/assistant/system, text, ui jsonb nullable, created_at. Index(conversation_id,id).
- turn_evidence: turn_id, revision_id, locale, excerpt_hash, unique(turn_id, revision_id, locale). Evidence body tidak diduplikasi ke log.
- tool_invocations: id, turn_id, call_id, name, status, validated_input jsonb, result_reference jsonb nullable, started_at, finished_at nullable. Diisi Plan 7; jangan simpan raw credential/input yang tidak diizinkan.

User hanya mengakses conversation sendiri. Tidak ada guest token di localStorage. Browser boleh menyimpan conversation public_id per user untuk resume; server tetap memeriksa pemilik. Logout membersihkan ID percakapan dari storage browser.

## Routes dan kontrak

- POST /api/v1/conversations dengan locale dan scene; menghasilkan conversation resource 201.
- POST /api/v1/conversations/{conversation}/messages dengan client_turn_id, message, locale, context; completed 200, processing duplikat 202.
- GET /api/v1/conversations/{conversation}/messages?after_id=N untuk history yang aman.
- GET /api/v1/conversations/{conversation}/turns/{turn} untuk status/hasil turn setelah response hilang.

POST pesan menggunakan envelope dari kontrak bersama. client_turn_id diciptakan browser sekali per submit dan tetap sama pada retry. Input berubah harus key baru; key sama+payload berbeda 409.

Context hanya scene/article_id/ticket_public_id; Plan 5 menolak ticket context nonnull karena resource belum tersedia. Nullable explicit menghapus selected article lama saat berpindah scene. Validasi article melalui reader policy. Jangan menerima user_id/role/departemen dari request.

## Orkestrasi dan gateway

Buat action StartConversation, AppendTurn, ExecuteTurn, ReadHistory, RecoverStaleTurn; PromptComposer, AnswerValidator, ModelGateway, CompatibleChatCompletionsGateway, serta FakeModelGateway. Bind gateway melalui container; config tidak memerlukan SDK.

Alur:
1. Validasi input/ownership lalu acquire database-backed cache lock user dan conversation dengan lease75s. Jika turn lain aktif, 409 TURN_IN_PROGRESS. Deduplikasi client_turn_id terlebih dahulu agar retry turn selesai langsung mendapatkan hasil tersimpan.
2. Simpan turn processing dan pesan user sekali dalam transaksi pendek. Tidak memegang transaksi SQL selama HTTP model.
3. Resolve context dan retrieval paling banyak lima evidence. Greeting/navigasi sederhana memakai respons deterministik scene. Pertanyaan SOP tanpa evidence mendapat no-source fallback dan link knowledge/helpdesk; jangan memanggil model untuk mengisi kebijakan yang tidak ada.
4. Susun system prompt terversi corporate-v1, locale, context terverifikasi, evidence, dan history maksimal20 pesan/12000 karakter dari turn selesai. Sumber revoked/usang dikeluarkan sesuai kontrak; jangan memotong teks di tengah Unicode.
5. Gateway POST base URL + /v1/chat/completions dengan model, messages, temperature0.3, max_tokens2048. Jangan mengirim tools ketika registry kosong atau reasoning_effort default. Bearer key hanya header, connect timeout5s, request timeout sesuai sisa deadline60s. Jangan otomatis retry provider POST.
6. Instruksikan model mengembalikan content JSON: `{"text":"...","source_revision_ids":[1,2]}`. Ini format content proyek, bukan persyaratan provider response_format. Parse strict; code fence/malformed/ID di luar evidence/finish_reason length/null mendapat fallback. Semua klaim SOP memerlukan minimal satu evidence dari turn. Server membangun source cards dari revision terotorisasi, bukan URL/title buatan model.
7. Verifikasi ulang account/access dan lease ownership sebelum menyimpan response. Jika lease hilang, jangan commit output lama. Simpan pesan assistant, source references, dan status terminal.
8. Bila provider gagal, simpan turn failed dengan fallback deterministik dan error_code, jangan hapus pesan user. Retry key yang sama mengembalikan terminal result, tidak memanggil model ulang. Pengguna dapat mencoba giliran baru dengan key baru setelah membaca fallback.

Turn processing yang melewati lease75s diberi failed TURN_INTERRUPTED melalui polling/request berikutnya dan scheduler Plan 8. Proses lama tidak boleh menyimpan response setelah recovery. In-flight AI bukan alasan menahan lock DB.

## Frontend dan konfigurasi

Buat resources/js/chat.js sebagai Alpine.data corporateChat yang diregistrasikan sebelum satu Alpine.start() Plan1; gunakan shared/api.js dan shared/dom.js yang sudah tersedia serta tambah safe-card-renderer.js. Pasang slot chat Plan3 menggunakan modal/button/input/spinner/empty-state bersama; jangan membuat API helper atau bootstrap Alpine kedua. Implementasikan loading, error 401/419/429, retry/poll status, scroll, pembatasan input2000, dan recovery conversation ID yang tidak sah. Satu submit aktif per tab dan server per user.

Provider disabled/setting kosong menghasilkan fallback AI_UNAVAILABLE dengan navigasi/search manual; tidak membuat halaman gagal boot. .env.example tetap tanpa secret. Pembuktian endpoint/model/tool format terhadap provider dilakukan di Plan8.

Reset/deactivation user Plan 2 menghapus lock yang dimilikinya dan menggagalkan turn; verifikasi account active mencegah response lama terkirim. History revoked mengirim placeholder, bukan source text lama.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila persistence, routes, actions orkestrasi, PromptComposer/AnswerValidator, CompatibleChatCompletionsGateway, FakeModelGateway (disiapkan untuk Plan8 dan pengembangan lokal), chat.js, dan fallback AI_UNAVAILABLE tersedia. Tes otomatis tidak ditulis/dijalankan di tahap ini; skenarionya ada di [katalog Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Dengan ASSISTANT_ENABLED=false, buka chat; harus muncul fallback dan tautan pencarian/helpdesk, halaman tetap jalan.
2. Isi LOCAL_LLM_* dengan provider uji; tanyakan SOP dari artikel DEMO dan pastikan kartu sumber muncul dengan versi/tanggal.
3. Tanyakan hal yang tidak ada di knowledge; harus fallback tanpa klaim kebijakan.
4. Ganti bahasa lalu kirim pesan berikutnya; jawaban mengikuti bahasa baru.
5. Buka chat di dua tab dan kirim bersamaan; satu harus ditolak/menunggu. Refresh saat menunggu jawaban; hasil tetap bisa diambil.
6. Nonaktifkan akun saat menunggu jawaban; jawaban tidak terkirim.
7. Klarifikasi: provider/model yang dipakai, apakah mendukung tool calling, dan batas biaya/usage.

[HTTP client Laravel](https://laravel.com/framework/docs/13.x/http-client) dan [atomic locks](https://laravel.com/framework/docs/13.x/cache#atomic-locks) adalah referensi primitives gateway/lease.

## Catatan hasil implementasi

Belum dikerjakan. Catat kontrak gateway/response yang dibuat, recovery yang diimplementasikan, checklist untuk pengguna, dan apakah model nyata sudah atau belum dicoba.
