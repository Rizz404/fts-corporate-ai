# Runbook operasional FTS Corporate AI

Untuk tim yang memasang dan menjalankan aplikasi di staging/production. Keputusan arsitektur ada di [kontrak teknis](TECHNICAL_CONTRACTS.md); dokumen ini berisi langkah kerja. Artefak contoh ada di folder [`deploy/`](../deploy).

## 1. Input yang harus tersedia

Hostname/domain dan sertifikat TLS, server Linux dengan PHP 8.4 (FPM + CLI) dan nginx, PostgreSQL 17 yang hanya dapat diakses host aplikasi/worker, `APP_KEY`, URL/model/API key provider AI (HTTPS), dan lokasi backup privat. Tanpa input ini deployment belum bisa dilakukan; jangan mengganti stack.

## 2. Setup production (release pertama)

1. Struktur: `/var/www/corporate-ai/releases/<tanggal>`, `/var/www/corporate-ai/shared/{.env,storage}`, symlink `current` → release aktif. `storage` dan `.env` di `shared` di-symlink ke setiap release.
2. `.env` dari [`deploy/env.production.example`](../deploy/env.production.example) (mode 0600). Wajib: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `LOG_STACK=daily` + `LOG_DAILY_DAYS`, base URL provider HTTPS tanpa `/v1`.
3. PHP: pasang [`deploy/php-production.ini.example`](../deploy/php-production.ini.example) untuk FPM dan CLI (memory 256M, max_execution_time 75, display_errors Off).
4. nginx: [`deploy/nginx.conf.example`](../deploy/nginx.conf.example) (root `public`, hanya `index.php`, dotfiles ditolak, `fastcgi_read_timeout 80s`).
5. Di direktori release:
   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci && npm run build
   php artisan migrate --force
   php artisan db:seed --class=Database\\Seeders\\ReferenceSeeder --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   Jangan menjalankan `migrate:fresh`, `corporate:demo-accounts`, atau seeder demo di production (keduanya menolak production).
6. Admin pertama (password lewat prompt tersembunyi, wajib diganti saat login pertama):
   ```bash
   php artisan corporate:create-admin --email=admin@perusahaan.tld --name="Admin"
   ```
7. Ganti symlink `current`, reload PHP-FPM (opcache `validate_timestamps=0`), lalu `php artisan queue:restart` bila worker dipakai.
8. Periksa `/up` (200) dan `/api/v1/admin/readiness` sebagai admin, lalu `/admin/operations`.

## 3. Scheduler dan worker

Cron (user aplikasi):

```cron
* * * * * cd /var/www/corporate-ai/current && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal: `corporate:recover-turns` setiap menit (juga menulis heartbeat scheduler) dan `corporate:purge-data` pukul 02:00 Asia/Jakarta (`withoutOverlapping`, `onOneServer`).

Worker: notifikasi v1 ditulis sinkron sehingga worker opsional. Bila job antrean dipakai, pasang [`deploy/supervisor.conf.example`](../deploy/supervisor.conf.example) (`queue:work --sleep=3 --tries=3 --timeout=90`), set `QUEUE_WORKER_REQUIRED=true`, dan restart worker setiap release.

## 4. Backup dan restore

- Backup harian dengan [`deploy/scripts/backup.sh`](../deploy/scripts/backup.sh): `pg_dump` format custom, diverifikasi dengan `pg_restore --list`, checksum SHA-256, retensi `BACKUP_KEEP` (default 14). Kredensial dari `PGPASSFILE` (0600) atau peer auth; skrip menolak `PGPASSWORD`.
- Restore hanya ke database staging/testing dengan [`deploy/scripts/restore-staging.sh`](../deploy/scripts/restore-staging.sh); nama target wajib berakhiran `_staging` atau `_test`.
- Latihan restore minimal sebelum pilot dan setiap kuartal: restore ke staging → `php artisan migrate --force` → ulangi journey (bagian 7).
- Lakukan backup tepat sebelum setiap migrasi production.

## 5. Retensi data

| Data | Umur | Catatan |
|---|---|---|
| Percakapan (turn, pesan, evidence, tool invocation) | 90 hari sejak aktivitas terakhir | Tiket/draft tetap ada; tautan percakapan menjadi null |
| Tiket closed beserta pesan, event, draft, receipt, notifikasi | 365 hari sejak `closed_at` | Tiket open/in_progress/resolved tidak pernah dihapus berdasar umur |
| Draft belum dikonfirmasi | 24 jam setelah `expires_at` | |
| Audit | 365 hari | |
| Receipt lain | 365 hari | |

Sebelum mengaktifkan jadwal di lingkungan baru: `php artisan corporate:purge-data --dry-run` (hanya jumlah, tanpa data pribadi). Pengguna tidak dihapus otomatis.

## 6. Observabilitas dan alert pilot

Log terstruktur (`assistant.turn`, `assistant.tool`, `retention.purge`) memuat request_id, turn/user ID internal, prompt version, provider/model alias, latensi, hasil, counter loop/tool, dan usage. Log tidak memuat API key, header bearer, body provider, prompt lengkap, transkrip, atau deskripsi tiket.

`/admin/operations` (admin saja) menampilkan metrik 7 hari dan tiga alert:

| Alert | Ambang | Pemeriksaan |
|---|---|---|
| Error AI | >10% dari minimal 20 turn dalam 1 jam | `/api/v1/admin/readiness` bagian assistant; log `assistant.turn` per `result` (mis. `ai_timeout`, `ai_rate_limited`, `tool_capability_unavailable`); status provider; bila provider mati, set `ASSISTANT_ENABLED=false` sementara (halaman artikel/tiket tetap jalan) |
| Latensi p95 | >55 dtk dari minimal 20 turn dalam 1 jam | Beban provider/model, `tool_rounds` di usage turn, jaringan ke provider |
| Tiket tanpa assignee | open >24 jam | Hubungi koordinator tim HR/IT; assign lewat `/admin/tickets` |

Alert tidak dikirim ke kanal eksternal (belum diintegrasikan); admin memeriksa halaman operasional setiap hari kerja selama pilot.

## 7. Verifikasi staging dan journey

1. Fresh install pada database staging kosong (bagian 2), bukan `migrate:fresh` pada database berisi data.
2. Restore backup ke staging lalu jalankan journey dalam id/en/ja: tanya SOP → kartu sumber → minta bantuan HR/IT → draft → edit → konfirmasi tombol → tiket → staf membalas → pemohon melihat balasan → tutup tiket. Periksa isolasi role/tim dan sumber yang dicabut (artikel diarsipkan → riwayat diganti pesan baku).
3. `/dev/*` harus 404, gambar scene dari `/assets/corporate/*.svg` (tanpa URL remote), dan readiness `ready: true`.
4. `config:cache`/`route:cache` aktif, scheduler berjalan (heartbeat "OK"), `corporate:purge-data --dry-run` sesuai harapan.

## 8. Insiden

| Gejala | Langkah |
|---|---|
| AI lambat/gagal | Cek readiness dan provider; turn macet dipulihkan otomatis setiap menit (`turn_interrupted`). Matikan sementara dengan `ASSISTANT_ENABLED=false` lalu `php artisan config:cache`. |
| Provider tidak mendukung tool | Turn gagal `tool_capability_unavailable`; set `ASSISTANT_TOOLS_ENABLED=false` (chat kembali menjawab dari retrieval, tiket lewat formulir). |
| Akun disalahgunakan | Nonaktifkan atau reset password dari `/admin/users`; sesi lama dan turn yang berjalan dibatalkan. |
| Data perlu dipulihkan | Restore ke staging, ekstrak data yang dibutuhkan; production dipulihkan dengan forward fix, bukan rollback yang menghapus data. |
| Release bermasalah | Arahkan `current` ke release sebelumnya, reload PHP-FPM, `queue:restart`. Migrasi data memakai forward fix. |

Rollout: staging → pilot terbatas (akun employee/editor/admin/staff HR/staff IT, data sintetis dulu) → perluasan akses setelah quality gate (bagian 7 dan [evaluasi model](evals/README.md)) lulus.
