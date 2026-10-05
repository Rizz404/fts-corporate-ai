# Plan 2 Akun karyawan dan hak akses

| Metadata | Nilai |
|---|---|
| ID | 2 |
| Status | Implemented |
| Dependensi | [Plan 1](PLAN_01_SETUP.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | [Catatan hasil implementasi](#catatan-hasil-implementasi) |

## Hasil yang harus tersedia

Admin dapat membuat/menonaktifkan akun, menetapkan departemen, peran, penugasan staf, dan authoring editor. Karyawan login dengan session; password sementara wajib diganti. Hak akses menjadi action/Policy yang dipakai plan berikutnya.

## Skema data

Tambahkan:
- departments: id, slug unique, name, is_active default true, timestamps.
- roles: id, code unique dengan employee/admin/editor/staff.
- role_user: user_id + role_id unique.
- users: department_id required FK departments, preferred_locale default id, is_active default true, must_change_password default true. Email dinormalisasi lowercase pada semua jalur create/update/login.
- support_teams: id, code unique hr/it, name, is_active.
- support_team_user: support_team_id + user_id unique; member harus role staff.
- knowledge_editor_department: user_id + department_id unique; user harus role editor.
- audit_events: id, actor_id nullable, action, subject_type, subject_id string, metadata jsonb, occurred_at. Tidak menyimpan password, raw transcript, atau credential.

Tidak hard delete user/departemen yang sudah dirujuk; nonaktifkan. Hindari cascade yang menghapus tiket/audit. Pivot boleh cascade saat record induk benar-benar dihapus oleh proses yang sah. Model/Factory ditambahkan untuk skema baru.

ReferenceSeeder idempoten membuat departemen general/hr/it/engineering, role empat kode, dan support team hr/it. Nama reference seed bukan data karyawan produksi.

## Autentikasi dan endpoint

- GET/POST /login; POST /logout; GET/POST /password/change.
- / mengalihkan guest ke login dan user siap pakai ke /office. Sampai Plan 3, /office memakai halaman authenticated sederhana.
- Admin HTML: GET /admin, resource /admin/users kecuali destroy, resource /admin/departments kecuali destroy. Perubahan aktif melalui update, bukan delete.
- Admin update user mengatur role, team membership, dan editor departments dalam satu transaksi. Assignment staff/editor tanpa role terkait ditolak 422.
- POST /admin/users/{user}/reset-password menghasilkan password sementara baru dengan input secret admin, bukan email delivery. Jangan menampilkan password di daftar atau log.
- POST /api/v1/preferences menerima locale id/en/ja, menyimpan preferred_locale dan session.

Login memakai Auth::attempt dengan syarat user aktif, regenerasi session setelah sukses, dan limiter kontrak. Logout invalidasi session serta regenerate CSRF. Password minimal 12 karakter, confirmed, bcrypt/default Laravel hashing; tidak memerlukan pemeriksaan password ke layanan eksternal.

Middleware EnsureAccountIsActive memeriksa DB user tiap request dan logout user nonaktif. Middleware EnsurePasswordChanged mengecualikan halaman pergantian password dan logout; JSON mengembalikan 403 PASSWORD_CHANGE_REQUIRED. Semua halaman/API bisnis terlindung kedua middleware.

## Langkah pengerjaan

1. Buat migration, models, factory, serta enum/konstanta role dan scope yang disepakati.
2. Implementasikan action ProvisionUser, UpdateUserAccess, DisableUser, ResetTemporaryPassword, ChangePassword, dan WriteAuditEvent. Validasi bersama seluruh jalur admin/CLI.
3. Buat UserPolicy dan DepartmentPolicy untuk admin; tidak memakai super-admin before() yang membypass seluruh policy resource.
4. Sediakan service actor context yang membaca user, departemen, role, team, dan editor scope dari DB. Identitas tidak boleh berasal dari body context/tool. Ambil snapshot per request dan verifikasi ulang operasi perubahan pada commit.
5. Implementasikan routes, controller, Form Request, serta Blade login/password/admin. Semua form POST/PATCH memakai CSRF, error terjemahan, dan old input tanpa password.
6. Tambahkan `php artisan corporate:create-admin --email=... --name=...` dengan password secret prompt, department general, role employee+admin, dan must_change_password true. Command memastikan reference seed tersedia. Password tidak menjadi argumen CLI.
7. Tambahkan command demo local/testing yang membuat akun sintetis employee, editor, staff HR, staff IT, dan admin dengan password sementara acak; tolak production. Jangan seed akun demo otomatis saat deploy.
8. Lindungi admin terakhir aktif: role admin tidak boleh dihapus atau akunnya dinonaktifkan jika menjadi satu-satunya admin aktif. Enforcement mengunci baris seluruh admin aktif dalam transaksi sebelum menghitung admin tersisa, bukan hanya tombol UI.
9. Reset password menghapus session user yang tersimpan dan membatalkan lease AI aktif ketika modul conversation sudah tersedia; Plan 5 memasang integrasi pembatalan. Role/team/departemen berubah dicatat audit tanpa data sensitif.
10. Update README menjalankan reference seed/create-admin. Root /office placeholder dihapus/diganti Plan 3.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila migration/model/factory, actions, Policy, middleware, routes/halaman login/password/admin, command create-admin dan demo, perlindungan admin terakhir, serta audit tersedia. Tidak perlu membangun Policy knowledge/ticket yang belum memiliki model; kemampuan actor siap digunakan tahap berikutnya. Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), tes otomatis tidak ditulis/dijalankan di tahap ini; skenarionya ada di [katalog Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Jalankan reference seed dan `corporate:create-admin`; login, lalu wajib ganti password sementara.
2. Login dengan email huruf besar/kecil berbeda; coba password salah berulang hingga throttle.
3. Sebagai admin buat user, ubah departemen/role/team/editor, nonaktifkan user, reset password; pastikan user tersebut langsung keluar dari session lama.
4. Coba hapus role admin dari admin terakhir atau nonaktifkan akunnya; harus ditolak.
5. Login sebagai non-admin dan buka /admin; harus ditolak.
6. Periksa halaman login/admin dalam id/en/ja serta pesan validasi.
7. Klarifikasi: daftar departemen awal, aturan password, dan siapa admin pertama.

## Pemakaian template dan komponen Plan1

Login/password memakai x-layouts.guest; halaman pengguna/departemen/admin memakai x-layouts.admin. Gunakan x-ui.field/input/select/checkbox, button, card, alert, dan table/pagination; FieldState menghubungkan validasi dengan control. Pasang ability menu pada navigation component yang sudah tersedia, bukan sidebar baru. Label id/en/ja diperluas dari lang ui yang dibuat Plan1.

## Catatan hasil implementasi

### Hasil implementasi (5 Oktober 2026)

**Status: Implemented.** Verifikasi otomatis menunggu Plan8.

**Skema** (migration `2026_10_05_00000{1..5}`)
- `departments` (slug unique, name, is_active); `users` + `department_id` (wajib, restrict on delete), `preferred_locale` (default id), `is_active`, `must_change_password` (default true).
- `roles` (code unique) dan `role_user` (PK user_id+role_id); `support_teams` dan `support_team_user`; `knowledge_editor_department`. Pivot cascade hanya saat induk benar-benar dihapus; UI tidak menghapus user/departemen.
- `audit_events` (actor nullable null-on-delete, action, subject_type, subject_id, metadata jsonb, occurred_at timestampTz).
- Model: Department, Role, SupportTeam, AuditEvent; User diperluas (normalisasi email lowercase lewat mutator). Factory: Department, Role, SupportTeam, User (state `inactive`, `withTemporaryPassword`, `withRoles`).
- `ReferenceSeeder` idempoten (departemen general/hr/it/engineering, empat role, team hr/it); `DatabaseSeeder` hanya memanggil ReferenceSeeder.

**Modul Identity** (`app/Modules/Identity`)
- Enum `RoleCode`, `SupportTeamCode`.
- `ActorContext` (snapshot DB: departemen, role, team aktif hanya dengan role staff, editor scope hanya dengan role editor) dan `CurrentActor` (cache scoped per request).
- Actions: `ProvisionUser`, `UpdateUserAccess`, `DisableUser`, `ResetTemporaryPassword`, `ChangePassword`, `WriteAuditEvent` (menghapus key sensitif dari metadata).
- `UserAccessRules`: aturan validasi bersama Form Request, CLI, dan action; staff team/editor department tanpa role terkait → 422.
- `AdminGuard`: verifikasi ulang aktor admin di dalam transaksi (row lock) dan perlindungan admin terakhir yang mengunci semua baris admin aktif sebelum menghitung.
- `SessionRevoker`: hapus sesi database, rotasi remember token, dispatch event `UserSessionsRevoked` setelah commit (hook pembatalan lease AI untuk Plan5).
- `AdminNavigation`: item sidebar berdasarkan kemampuan; modul tahap berikutnya tampil nonaktif hanya untuk role terkait.

**HTTP**
- Routes: `/` (redirect login/office), `GET|POST /login` (guest), `POST /logout`, `GET|POST /password/change`, `/office` sementara (`office.reception`), `/admin` (gate `admin.access` = admin/editor/staff), `admin/users` dan `admin/departments` (resource tanpa show/destroy), `POST admin/users/{user}/reset-password`, `POST /api/v1/preferences`.
- Middleware group `account.active` (auth + AuthenticateSession + EnsureAccountIsActive) untuk ganti password/logout dan `account` (+ EnsurePasswordChanged) untuk semua halaman/API bisnis. JSON: 401 `unauthenticated`, 403 `password_change_required`.
- Login: email dinormalisasi, `Auth::attempt` dengan `is_active=true`, limiter 5/menit per email+IP, regenerasi session, locale session dari `preferred_locale`. Logout invalidasi session + regenerasi CSRF. Ganti password memperbarui hash sesi saat ini sehingga sesi lain keluar.
- Policy `UserPolicy` dan `DepartmentPolicy` (admin saja, tanpa before() bypass).
- `SetLocale`: pilihan `?lang` pengguna login juga disimpan ke `preferred_locale`.

**CLI**
- `corporate:create-admin --email= --name=`: menjalankan ReferenceSeeder, password lewat prompt tersembunyi, departemen general, role employee+admin, wajib ganti password.
- `corporate:demo-accounts`: hanya local/testing; employee, editor (general+hr), staff HR, staff IT, admin dengan password acak yang ditampilkan sekali (menjalankan ulang mereset password). Tidak ada password di docs.

**UI**
- Halaman login, ganti password (x-layouts.guest), office sementara (x-layouts.user), dashboard, daftar/form akun dengan filter dan reset password via confirm-dialog, daftar/form departemen (x-layouts.admin).
- Komponen baru/diperluas: `x-navigation.account-menu`; `x-ui.dropdown` prop `trigger-variant`; `x-ui.dropdown.item` prop `type="submit"`; checkbox mendukung nama array dengan old input; layout admin/guest menampilkan `session('status')`; layout user menerima `adminHref`.
- Terjemahan id/en/ja di `lang/*/ui.php`; `lang/{id,ja}/validation.php` (aturan yang dipakai) dan `lang/{en,id,ja}/auth.php`.

**Perintah setup yang dijalankan**: `php artisan migrate`, `php artisan db:seed`, `php artisan route:list`, `php artisan view:cache`, `npm run build` (SQLite lokal).

**Penyimpangan dan keputusan**
- Resource admin tanpa `show` (halaman edit memuat detail) dan tanpa `destroy`.
- Halaman landing Plan1 dihapus; `/` sekarang redirect.
- Kode error JSON memakai snake_case huruf kecil (`password_change_required`); konvensi dicatat di TECHNICAL_CONTRACTS.
- Slug departemen immutable setelah dibuat; hanya nama dan status aktif yang dapat diubah.
- `lockForUpdate` tidak berefek di SQLite lokal; perlindungan admin terakhir terhadap race baru efektif di PostgreSQL (diuji Plan8).

**Checklist untuk pengguna** — lihat bagian "Kriteria implementasi dan verifikasi pengguna" (langkah 1–7). Mulai dengan `php artisan migrate`, `php artisan db:seed`, lalu `php artisan corporate:create-admin` atau `php artisan corporate:demo-accounts`.
