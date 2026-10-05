# Plan 2 Akun karyawan dan hak akses

| Metadata | Nilai |
|---|---|
| ID | 2 |
| Status | Ready |
| Dependensi | [Plan 1](PLAN_01_SETUP.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | Belum dikerjakan |

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

Belum dikerjakan. Catat skema yang dibuat, akun sintetis tanpa password di docs, checklist untuk pengguna, dan keterbatasan. Status Implemented setelah implementasi selesai; Done setelah verifikasi Plan8.
