# Plan 5 Percakapan dan AI berbasis sumber

| Metadata | Nilai |
|---|---|
| ID | 5 |
| Status | Implemented |
| Dependensi | [Plan 4](PLAN_04_KNOWLEDGE_CMS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | [Catatan hasil implementasi](#catatan-hasil-implementasi) |

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

### Hasil implementasi (5 Oktober 2026)

**Status: Implemented.** Verifikasi otomatis menunggu Plan8.

**Provider (input pengguna)**: LM Studio terhosting `https://local.fts-tech.co.id` (OpenAI-compatible), model `qwen3.6-35b-a3b-uncensored-hauhaucs-aggressive`. Nilai dan API key hanya di `.env` lokal (tidak dilacak). Model ini adalah *thinking model*: tanpa `reasoning_effort=none` seluruh `max_tokens` habis untuk penalaran dan `content` kosong (`finish_reason=length`), sehingga `ASSISTANT_REASONING_EFFORT=none` diaktifkan sebagai konfigurasi kemampuan provider (default tetap tidak dikirim).

**Persistence** (`2026_10_05_000008_create_conversation_tables`): `conversations`, `ai_turns` (unique conversation+client_turn_id, fingerprint, deadline, error_code, provider/model, prompt_version, usage), `conversation_messages` (index conversation+id, `ui` berisi descriptor server), `turn_evidence` (hash excerpt saja), `tool_invocations` (kosong sampai Plan7).

**Modul Assistant** (`app/Modules/Assistant`)
- `Gateway\ModelGateway`, `ChatCompletionRequest`, `ChatCompletionResult`, `TurnDeadline`, `GatewayException` (kode `ai_unavailable`, `ai_timeout`, `ai_rate_limited`, `ai_bad_request`, `ai_bad_response`).
- `CompatibleChatCompletionsGateway`: POST `{base}/v1/chat/completions` (base tanpa `/v1`, HTTPS wajib di luar local/testing), bearer header, connect timeout 5 s, timeout = sisa deadline, tanpa retry, `tools` hanya bila registry berisi, `reasoning_effort` hanya bila dikonfigurasi, opsi `LOCAL_LLM_CA_BUNDLE` untuk verifikasi TLS.
- `FakeModelGateway` (antrian hasil/exception, mencatat request) untuk Plan8/pengembangan.
- `Prompt\PromptComposer` (`corporate-v1`: aturan grounding, anti-injection, bahasa aktif, sumber dalam tag `<source revision_id>`, riwayat ≤20 pesan/12000 karakter tanpa memotong pesan) dan `Prompt\AnswerValidator` (JSON ketat `{"text","source_revision_ids"}`; menolak tool call, finish_reason ≠ stop, code fence/teks tambahan, key lain, id di luar evidence, tanpa sumber).
- `Tools\ToolRegistry` kosong.

**Modul Conversation** (`app/Modules/Conversation`)
- Actions `StartConversation`, `AppendTurn` (dedup client_turn_id + fingerprint SHA-256 kanonik, 409 `idempotency_conflict`, validasi article context via retriever, lease user+percakapan 75 s → 409 `turn_in_progress`, transaksi pendek turn+pesan user+context/locale terbaru), `ExecuteTurn`, `ReadHistory` (cursor `after_id`, 50/halaman, placeholder untuk sumber yang tidak bisa diakses, flag `stale`), `RecoverStaleTurn` (`turn_interrupted`).
- `ExecuteTurn`: balasan deterministik (salam/terima kasih/navigasi) → retrieval ≤5 (artikel context lebih dulu, kategori scene HR/IT lalu tanpa kategori) → tanpa evidence: fallback tanpa model → provider mati: `ai_unavailable` + kartu artikel terkait → gateway → validator → sebelum simpan: akun aktif, lease masih dimiliki, sumber masih bisa dibaca. Kartu sumber dibangun server. Semua kegagalan menyimpan turn failed + fallback; pesan user tidak dihapus; lease selalu dilepas; log `assistant.turn` tanpa isi prompt.
- `TurnRecorder` (finish sekali dengan row lock), `TurnLease`, `TurnCards`, `QuickReplies`, `EvidenceAvailability`, `TurnPresenter`.
- Listener `CancelTurnsWhenSessionsRevoked` untuk event Plan2 `UserSessionsRevoked`: force release lease dan gagalkan turn (`account_revoked`).

**HTTP**: `POST /api/v1/conversations` (201), `POST /api/v1/conversations/{id}/messages` (200 terminal / 202 processing, throttle `assistant` 10/menit per user + 60/menit per IP), `GET .../messages?after_id=`, `GET .../turns/{turn}`; parameter UUID, percakapan milik orang lain 404; `context.ticket_public_id` harus null.

**Frontend**: `resources/js/chat.js` (`Alpine.data('corporateChat')`: satu submit aktif, retry dengan client_turn_id sama, polling turn tiap 2 s sampai 70 s, penanganan 401/419/429/409/404, batas 2000 karakter, Enter kirim/Shift+Enter baris baru, resume percakapan per user di localStorage, dibersihkan saat logout), `safe-card-renderer.js` (whitelist tipe kartu/aksi, path relatif same-origin), komponen `x-office-chat` di chat dock semua ruang; topik saran di scene menjadi tombol yang bertanya ke chat.

**Perintah setup yang dijalankan**: `php artisan migrate`, `php artisan view:cache`, `php artisan route:list`, `npm run build`; satu panggilan `/v1/models` dan dua request sintetis langsung ke provider untuk menentukan model dan kebutuhan `reasoning_effort`; satu turn integrasi sekali jalan (pertanyaan cuti, user demo) → `completed`, sumber "[DEMO] Cara mengajukan cuti", ±400 token, ±2–3 detik. Ini pemeriksaan konfigurasi, bukan tes otomatis atau evaluasi model.

**Penyimpangan dan keputusan**
- Turn dijalankan sinkron di request POST (seperti hotel); 202 hanya untuk retry saat turn masih berjalan.
- Pertanyaan tanpa evidence selalu mendapat fallback tanpa memanggil model; jawaban model tanpa sumber juga diganti fallback.
- Blok `<think>…</think>` di awal content dibuang sebelum parsing.
- PHP lokal (scoop, tanpa php.ini) tidak punya CA bundle sehingga HTTPS gagal (`cURL error 60`); diatasi dengan `LOCAL_LLM_CA_BUNDLE` (bundle Git) di `.env`, tanpa mengubah instalasi PHP.
- Risiko yang perlu diputuskan pengguna: model yang dipakai berlabel *uncensored/aggressive*. Grounding dan validator membatasi jawaban pada sumber, tetapi untuk pilot sebaiknya dipertimbangkan model instruksi standar (evaluasi di Plan8).

**Checklist untuk pengguna** — lihat bagian "Kriteria implementasi dan verifikasi pengguna" (langkah 1–7).
