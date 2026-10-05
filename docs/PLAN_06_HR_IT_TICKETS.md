# Plan 6 Tiket HR dan IT serta panel staf

| Metadata | Nilai |
|---|---|
| ID | 6 |
| Status | Implemented |
| Dependensi | [Plan 5](PLAN_05_CONVERSATION_AND_AI.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | [Catatan hasil implementasi](#catatan-hasil-implementasi) |

## Hasil yang harus tersedia

Karyawan membuat tiket melalui form dengan review/konfirmasi, membaca pesan/status, dan staf menangani antrean timnya. Seluruh alur bekerja tanpa AI; Plan 7 hanya memasang tool AI pada action yang sudah tersedia.

## Skema data

- ticket_drafts: id, public_id UUID unique, requester_id, conversation_id nullable, originating_turn_id nullable, team_id, category, subject string160, description text, revision integer default1, payload_fingerprint string64, expires_at, consumed_ticket_id nullable, timestamps. Unique originating_turn_id bila nonnull memastikan maksimal satu draft per AI turn; FK turn baru dipakai Plan 7.
- tickets: id, public_id UUID unique, reference string unique, requester_id, team_id, category, subject, description, assignee_id nullable, status default open, version integer default1, draft_id unique FK ticket_drafts, conversation_id nullable, resolved_at nullable, closed_at nullable, timestamps.
- ticket_messages: id, ticket_id, author_id, visibility public/internal, text max5000, created_at; index(ticket_id,id).
- ticket_events: id, ticket_id, actor_id, event_code, previous_status nullable, next_status nullable, metadata jsonb, created_at. Tidak menyimpan body pesan sebagai event.
- operation_receipts: id, actor_id, operation_type, idempotency_key UUID, fingerprint string64, resource_type, resource_public_id string, created_at. Unique(actor_id, operation_type, idempotency_key).
- notifications memakai migration database notifications Laravel: UUID id, notifiable morph, type, data, read_at, timestamps.

Circular draft/ticket FK dibuat terpisah setelah kedua tabel ada; draft_id tickets unique menjadi jaminan satu tiket per draft. tickets.draft_id memakai restrict-on-delete; ticket_drafts.consumed_ticket_id memakai null-on-delete. Pada purge, simpan ID draft, hapus tiket terlebih dahulu, baru draft; jangan mencoba cascade circular. Conversation/turn link nullable dengan null-on-delete agar retensi chat tidak menghapus tiket. Ticket/messages tidak hard delete melalui UI.

Reference `CORP-YYYYMMDD-XXXXXXXX` dibuat server dengan random suffix dan unique constraint; retry collision terbatas tiga kali. Reference hanya label, bukan identifier otorisasi.

## Form, API, dan panel

Karyawan:
- GET /office/requests, GET /office/requests/create, GET /office/requests/{ticket:public_id}.
- POST /api/v1/ticket-drafts menerima team_code/category/subject/description dan conversation_public_id optional milik pemohon.
- GET/PATCH /api/v1/ticket-drafts/{draft:public_id}; edit membawa expected_revision.
- POST /api/v1/ticket-drafts/{draft}/confirm dengan expected_revision dan idempotency_key.
- GET /api/v1/tickets untuk tiket milik pemohon; GET /api/v1/tickets/{ticket}.
- GET/POST /api/v1/tickets/{ticket}/messages; POST membawa text dan idempotency_key.
- PATCH /api/v1/tickets/{ticket}/status; pemohon hanya resolved→closed.

Staf:
- GET /admin/tickets dan /admin/tickets/{ticket} memakai staff membership, bukan role admin.
- PATCH /api/v1/staff/tickets/{ticket}/assignment dengan assignee_id integer user ID internal yang valid, expected_version, idempotency_key.
- PATCH /api/v1/staff/tickets/{ticket}/status dengan target status, expected_version, reason bila diperlukan, idempotency_key.
- GET/POST /api/v1/staff/tickets/{ticket}/messages; staff boleh memilih public/internal.
- GET /api/v1/notifications dan PATCH /api/v1/notifications/{notification}/read hanya pemilik.

API karyawan mengembalikan pesan public saja dan hanya tiket sendiri. API staf memerlukan role staff serta team membership saat ini. User yang staff sekaligus pemohon tetap dapat melihat miliknya melalui endpoint karyawan, tetapi catatan internal hanya melalui endpoint staf. Admin tanpa staff membership mendapat 403 menu staf dan 404 tiket yang bukan miliknya.

## Action dan transaksi

Buat PrepareTicketDraft, UpdateTicketDraft, ConfirmTicketDraft, AssignTicket, TransitionTicket, AppendTicketMessage, dan ReadTicketHistory. Reusable domain validator dipakai Form Request serta tool Plan 7; actor selalu session, bukan requester_id body.

Draft confirmation:
1. Periksa receipt actor/type/key terlebih dahulu; identik return hasil pertama, payload berbeda409.
2. Lock draft, periksa owner, expected_revision, expires_at, dan fingerprint.
3. Jika draft sudah consumed, kembalikan tiket yang sama bagi pemilik untuk revision/fingerprint identik; jangan buat tiket kedua walau key berbeda.
4. Insert tiket open, set consumed_ticket_id, receipt, event, dan database notifications dalam satu transaksi; hasil 201 pertama dan200 pada replay.
5. Hasil persisted tiket tidak bergantung completion model. Draft expired yang belum consumed ditolak409 DRAFT_EXPIRED.

State/assignment memakai row lock tiket, expected_version, dan matriks kontrak. Assignment hanya user aktif staff team tiket. Ubah version saat status atau assignment berubah. Pesan mengunci tiket untuk mencegah balasan setelah close, tetapi tidak menaikkan version state. Message ID menjadi cursor.

Notification channel database saja dan ditulis sinkron di transaksi yang sama: tiket baru ke semua staff aktif tim; assignment ke assignee; pesan public ke pemohon/assignee yang bukan author (atau staff tim jika belum assigned); internal note ke staff terkait tanpa pemohon; perubahan status ke pemohon. Dedup recipient ID. Data notification hanya ticket public_id, event_code, dan actor ID; tidak menyimpan description/note.

Receipt replay tidak menciptakan event/message/notification lagi. Constraint dan transaksi digunakan untuk perlombaan proses, bukan sekadar validasi awal. [Pessimistic locking Laravel](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking).

## Frontend

Buat wizard tiga langkah: tim/kategori → isi permintaan → review/confirm. Draft disimpan server; browser hanya menyimpan public_id nonrahasia jika perlu, tanpa description/PII di storage.

Hubungkan My Requests dan HR/IT CTA dari Plan 3. Halaman detail menampilkan status, pesan staf public, form reply, dan tombol close saat resolved. Staf melihat filter tim/status dan assignment.

Polling setiap5s hanya pada halaman aktif; gunakan after_id dan dedup by message ID. Pause pada document hidden/offline; resume dengan cursor terakhir. Internal note tidak pernah masuk renderer pemohon. Badge notifikasi menggunakan data aman dan memeriksa permission saat membuka tiket.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila skema, actions dengan transaksi/row lock/receipt, API karyawan dan staf, notifikasi database, wizard, My Requests, panel staf, dan polling tersedia. Tes otomatis (termasuk concurrency PostgreSQL) tidak ditulis/dijalankan di tahap ini; skenarionya ada di [katalog Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

[Database notifications Laravel](https://laravel.com/framework/docs/13.x/notifications#database-notifications) menjadi acuan implementasi kanal.

Checklist untuk pengguna:
1. Sebagai karyawan buat tiket HR dan IT lewat wizard; review lalu konfirmasi. Klik konfirmasi dua kali cepat atau refresh saat submit; harus tetap satu tiket.
2. Biarkan draft lebih dari 15 menit lalu konfirmasi; harus ditolak.
3. Sebagai staff HR, lihat antrean, assign, balas publik, tulis catatan internal, ubah status open→in_progress→resolved. Staff IT tidak boleh melihat tiket HR.
4. Sebagai pemohon, lihat balasan muncul dalam ±5 detik tanpa refresh; catatan internal tidak terlihat. Tutup tiket saat resolved; balasan setelah closed ditolak.
5. Admin tanpa penugasan staff tidak bisa membuka tiket orang lain.
6. Cek badge notifikasi untuk tiket baru, assignment, balasan, dan perubahan status.
7. Klarifikasi: kategori HR/IT final dan siapa staff awal tiap tim.

## Pemakaian fondasi UI

Antrean/detail staf menyusun x-layouts.admin; halaman karyawan memakai office-stage/x-layouts.user. Form memakai field/control, card, button, modal/confirm-dialog, table, badge, empty-state, dan toast yang sudah tersedia. Wizard didaftarkan sebagai Alpine.data ticketWizard sebelum startup tunggal; state tidak menduplikasi native event listener. Status open→info, in_progress→warning, resolved→success, closed→neutral dipetakan presenter Support. Konfirmasi UI tetap memanggil action server dengan revision/idempotency; event ui:confirmed tidak mengotorisasi operasi.

## Catatan hasil implementasi

### Hasil implementasi (5 Oktober 2026)

**Status: Implemented.** Verifikasi otomatis (termasuk concurrency PostgreSQL) menunggu Plan8.

**Skema** (`2026_10_05_000009_create_ticket_tables`): `ticket_drafts` (originating_turn_id unique + FK ai_turns null-on-delete), `tickets` (reference unique, draft_id unique restrict, conversation null-on-delete, index tim/status/updated), FK sirkular `ticket_drafts.consumed_ticket_id` → tickets null-on-delete ditambahkan setelah kedua tabel ada, `ticket_messages`, `ticket_events`, `operation_receipts` (unique actor+type+key), `notifications` (standar Laravel).

**Modul Support** (`app/Modules/Support`)
- Enum `TicketStatus` (pemetaan tone open→info, in_progress→warning, resolved→success, closed→neutral), `TicketCategory` (kategori tetap per tim), `MessageVisibility`.
- `Validation\TicketDraftRules`: dipakai Form Request dan siap untuk tool `prepare_ticket` Plan7.
- Actions: `PrepareTicketDraft` (revision 1, 15 menit), `UpdateTicketDraft` (expected_revision, revision+1, fingerprint baru, perpanjang 15 menit), `ConfirmTicketDraft` (receipt → lock draft → owner/revision/expiry → draft consumed dengan revisi sama mengembalikan tiket yang sama walau key berbeda → tiket + consumed + receipt + event + notifikasi dalam satu transaksi; 201 pertama, 200 replay), `AssignTicket`, `TransitionTicket` (matriks kontrak, assignee wajib untuk open→in_progress, catatan wajib untuk resolve/reopen yang disimpan sebagai pesan publik), `AppendTicketMessage` (lock tiket, tolak setelah closed, tidak menaikkan version), `ReadTicketHistory` (cursor id, 50/halaman, internal hanya staf).
- `Actions\Concerns\RunsIdempotently` + `Support\Receipts`: receipt dulu, operasi + receipt dalam satu transaksi, replay saat terjadi race (unique violation); replay tidak membuat event/pesan/notifikasi lagi.
- `Support\TicketAccess` (pemohon atau staff aktif tim; admin tanpa penugasan tidak mendapat akses), `TicketReference` (`CORP-YYYYMMDD-XXXXXXXX`, maksimal 3 percobaan), `TicketNotifier` + `App\Notifications\TicketActivity` (database, sinkron di transaksi; data hanya ticket public_id, event_code, actor_id; penerima di-dedup dan tidak termasuk aktor).
- Presenter `TicketPresenter` dan `NotificationPresenter` (path notifikasi dihitung sesuai akses saat ini).

**HTTP**
- Karyawan: `GET /office/requests`, `GET /office/requests/create?team=`, `GET /office/requests/{ticket}`; API `POST /api/v1/ticket-drafts`, `GET|PATCH /api/v1/ticket-drafts/{draft}`, `POST .../confirm`, `GET /api/v1/tickets`, `GET /api/v1/tickets/{ticket}`, `GET|POST .../messages`, `PATCH .../status` (pemohon hanya `closed`).
- Staf: `GET /admin/tickets` (filter tim/status/milik saya; gate `tickets.staff` → admin tanpa penugasan 403), `GET /admin/tickets/{ticket}`; API `PATCH /api/v1/staff/tickets/{ticket}/assignment`, `PATCH .../status`, `GET|POST .../messages`.
- Notifikasi: `GET /api/v1/notifications`, `PATCH /api/v1/notifications/{id}/read` (pemilik saja).
- Tiket orang lain atau tim lain → 404; parameter UUID; limiter `ticket-writes` 20/menit per user.

**UI**: wizard tiga langkah (`ticketWizard`), daftar dan detail permintaan di scene My Requests, antrean dan detail staf di layout admin (riwayat event), komponen `x-ticket-thread` (polling 5 s hanya saat halaman terlihat/online, cursor `after_id`, dedup id, pause saat hidden/offline), `x-navigation.notification-bell` di header admin dan user, CTA "Permintaan HR/IT baru" di meja HR/IT, menu Tiket admin untuk staf.

**Perintah setup yang dijalankan**: `php artisan migrate`, `php artisan view:cache`, `php artisan route:list`, `npm run build`. Alur tiket belum dijalankan sama sekali (tidak ada tes/eksekusi manual oleh implementer).

**Penyimpangan dan keputusan**
- Kode error memakai snake_case: `draft_expired`, `draft_consumed`, `revision_conflict`, `version_conflict`, `ticket_closed`, `invalid_transition` (422), `idempotency_conflict` (409).
- Edit draft memperpanjang masa berlaku 15 menit dari edit terakhir.
- Catatan resolve/reopen disimpan sebagai pesan publik agar pemohon tahu alasannya.
- Halaman detail staf memuat ulang setelah assign/transisi (state server menjadi sumber kebenaran).
- Row lock (`lockForUpdate`) tidak berefek di SQLite lokal; jaminan satu tiket per draft tetap dipegang unique `tickets.draft_id`, tetapi uji race wajib di PostgreSQL (Plan8).

**Checklist untuk pengguna** — lihat bagian "Kriteria implementasi dan verifikasi pengguna" (langkah 1–7). Akun staf demo: `demo.staff.hr@example.test` dan `demo.staff.it@example.test` (password dari `php artisan corporate:demo-accounts`).
