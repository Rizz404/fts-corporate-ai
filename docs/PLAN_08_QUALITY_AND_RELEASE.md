# Plan 8 Evaluasi operasional dan persiapan rilis

| Metadata | Nilai |
|---|---|
| ID | 8 |
| Status | In Progress |
| Dependensi | [Plan 7](PLAN_07_AI_TOOLS_AND_WORKFLOWS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | [Catatan hasil implementasi](#catatan-hasil-implementasi) |

## Hasil yang harus tersedia

Seluruh kemampuan v1 diverifikasi, mempunyai retensi/recovery, metrik aman, serta paket deployment staging/production yang dapat digunakan. Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), plan ini adalah satu-satunya tahap yang membangun dan menjalankan pengujian: infrastruktur tes, lint UI, CI, seluruh skenario tes Plan1–7, review UI, dan evaluasi model. Plan1–7 berstatus Implemented sampai lulus verifikasi di sini, lalu menjadi Done. Menjalankan plan ini membuat aplikasi siap rilis; publikasi nyata tidak dilakukan otomatis tanpa instruksi deployment dan target yang tersedia.

## Konfigurasi dan artefak operasional

Tambahkan:
- docs/OPERATIONS.md: setup production, create-admin, backup/restore, queue/scheduler, recovery, dan incident steps.
- deploy/nginx.conf.example: root public, PHP-FPM, hanya index.php sebagai entry PHP, HTTPS template, larang akses dotfiles/env, PHP fastcgi timeout80s.
- deploy/supervisor.conf.example: worker `php artisan queue:work --sleep=3 --tries=3 --timeout=90`, restart setelah release.
- deploy/php-production.ini.example: PHP8.4, memory_limit256M, max_execution_time75, display_errors Off.
- deploy/scripts/backup.sh dan restore-staging.sh: PostgreSQL custom-format dump, secret dari environment/service config tanpa password CLI/log. Restore hanya ke database staging/testing yang secara eksplisit ditentukan.
- docs/evals/ berisi corpus sintetis90 skenario dan hasil evaluasi bertanggal; bukan data perusahaan nyata.

Input deployment yang harus tersedia: hostname/domain, server Linux dengan PHP8.4/PostgreSQL17, database credential, APP_KEY, URL/model/key provider, TLS, dan lokasi backup private. Ini input environment, bukan keputusan arsitektur yang boleh diganti AI. Tanpa input tersebut, selesaikan artefak/runbook dan catat deployment belum dilakukan.

SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true, same_site=lax, APP_ENV=production, APP_DEBUG=false, log retention terkonfigurasi. Provider HTTPS wajib. Database hanya dapat diakses host aplikasi/worker; tidak dipublish ke internet. Jangan menyalin compose dev untuk hosting production.

## Recovery dan retensi

Implementasikan command:
- `corporate:recover-turns`: tandai processing melewati lease75s sebagai failed TURN_INTERRUPTED, tulis fallback sekali, lepaskan lease milik turn dengan pemeriksaan ownership. Jadwalkan setiap menit.
- `corporate:purge-data --dry-run`: tampilkan jumlah record yang memenuhi expiry tanpa isi personal.
- `corporate:purge-data`: jalankan tiap hari02:00 Asia/Jakarta dengan withoutOverlapping, batch200 dan transaksi pendek.

Retensi baseline: conversation90 hari sejak last_activity_at; tiket/messages365 hari sejak closed_at; audit365 hari; draft belum consumed dihapus24 jam setelah expires_at; receipt terkait tiket dihapus bersama tiket, receipt lain365 hari. Consumed draft mengikuti umur tiket. Jangan hapus tiket open/in_progress/resolved berdasarkan tanggal dibuat.

Cascade penghapusan conversation mencakup turn/messages/evidence/invocation, tetapi FK conversation/turn pada tiket/draft null-on-delete. Penghapusan tiket tertutup mencakup messages/events/draft/receipt; preserve audit sampai kebijakan umur audit. Test cleanup terhadap circular FK draft-ticket, jangan hanya mengasumsikan cascade.

Notification terkait tiket yang sudah dihapus ikut dibersihkan; metadata user tetap disimpan sampai proses deactivation/deletion yang terpisah. Tidak membuat fitur penghapusan user otomatis.

## Observabilitas dan readiness

Log terstruktur: request_id, turn_id internal, user ID internal, prompt/schema version, provider/model alias, latency, result code, tool name/status, loop count, usage jika provider mengirim, dan operation reference. Jangan log API key, bearer header, provider body, prompt penuh, transcript, atau deskripsi tiket.

Tambahkan admin-only /admin/operations dengan metrik7 hari: completed/failed turn, p50/p95 latency dari record turn, timeout, tool validation failures, retrieval no-match, open/unassigned ticket counts, serta oldest open. Aggregate tiket untuk admin hanya hitungan; jangan memberi akses isi tiket lewat metrik.

Tambah /api/v1/admin/readiness untuk admin: DB/cache, setting provider, dan status scheduler/worker heartbeat. Tidak memanggil model pada setiap health request. /up tetap health dasar; AI tidak tersedia tidak mematikan halaman artikel/tiket. Heartbeat worker hanya dianggap wajib jika proses queue memang dikonfigurasi digunakan; notifications v1 sinkron database.

Tetapkan alert pilot bila error AI >10% dari minimal20 turn dalam1 jam, p95 turn>55s dari minimal20 turn, atau tiket open tanpa assignee>24jam. Runbook menjelaskan pemeriksaan; tidak mengirim pesan/email eksternal karena kanal itu belum diintegrasikan.

## Evaluasi kualitas dan skenario end-to-end

Corpus per bahasa30:15 pertanyaan SOP dengan sumber,5 no-answer/ambiguous,5 prompt injection/akses terlarang,3 status tiket,2 draft/konfirmasi. Dataset artikel resmi sintetis memakai revision yang jelas dan audience berbeda.

Expected result setiap skenario memuat sumber/actor yang sah, jawaban atau fallback yang diharapkan, serta efek domain yang boleh/tidak boleh terjadi. Nilai correctness/grounding per skenario. Target >=27/30 benar di setiap bahasa, seluruh akses terlarang ditolak, nol tiket dibuat tanpa klik konfirmasi, dan nol duplikasi efek saat retry.

Jalankan evaluasi model nyata terpisah dari CI. Catat provider/model alias, prompt version, tanggal, usage bila tersedia, hasil, serta failure contoh tanpa secret. Jangan mempromosikan hasil fake menjadi akurasi model. Jika credential/provider belum tersedia, tandai evaluasi nyata belum dijalankan dan jangan menyatakan pilot lulus.

## Aset visual final

Sesuai keputusan pengguna, Plan3–7 memakai gambar placeholder acak dan brand mark teks. Tahap ini menyelesaikan aset visual sebelum rilis:
- Ilustrasi scene reception, knowledge, hr, it, requests dan karakter assistant di `public/assets/corporate/` (SVG atau gambar teroptimasi), konsisten dengan palet [UI_FOUNDATION](UI_FOUNDATION.md). Tidak memakai aset hotel bermerek; sumber/lisensi setiap aset dicatat.
- Logo/brand mark untuk `x-navigation.brand`, favicon, dan ikon aplikasi.
- Gaya ilustrasi, karakter, dan logo dikonfirmasi pengguna lebih dulu (ditanyakan di checklist), lalu aset ditempatkan dan `SCENE_IMAGE_SOURCE=local` diaktifkan; view/JS tidak perlu diubah karena memakai resolver Plan3.
- Gate rilis: staging/production tidak memakai mode placeholder atau URL gambar remote; fallback gambar tetap berfungsi; teks di atas gambar memenuhi kontras ≥4.5:1.

## Infrastruktur pengujian dan CI

Kerjakan sebelum menjalankan katalog:
- phpunit.xml/env testing memakai PostgreSQL17 `corporate_ai_test`; .env.testing tidak dilacak. Base TestCase menolak reset database (RefreshDatabase/DatabaseMigrations/DatabaseTruncation) bila nama database tidak berakhiran `_test`, sebelum reset trait berjalan. SQLite tidak menjadi baseline tes; jika dipakai untuk iterasi lokal cepat, hanya `:memory:`/`*_test.sqlite` dan hasilnya tidak menggantikan run PostgreSQL.
- Pastikan role/database PostgreSQL lokal tersedia (keputusan SQLite sementara Plan1 berakhir di sini); jalankan migrate pada PostgreSQL dan catat perbedaan perilaku bila ada.
- Composer script `test` (config:clear lalu artisan test) dan npm script `test:ui=node --test tests/js/api-client.test.js` (Node built-in test runner, tanpa paket browser test).
- `scripts/check-ui-conventions.mjs` dan npm script `lint:ui` sesuai aturan [UI_FOUNDATION §6](UI_FOUNDATION.md#6-pemeriksaan-konsistensi-ui): literal warna luar theme.css, palette langsung/arbitrary color, inline style warna, CDN/duplicate Alpine.start, dan class warna dinamis. Hanya Node built-ins; jangan scan docs/vendor/generated; pengecualian kecil wajib beralasan.
- GitHub Actions dengan service PostgreSQL17, PHP8.4, Node22: composer validate --strict, composer install dari lock, PHPUnit PostgreSQL, `php vendor/bin/pint --test`, npm ci, lint:ui, test:ui, build, composer audit, dan npm audit --omit=dev (temuan build dependency dicatat/ditriase).
- HTTP model memakai fake dengan URL tepat dan preventStrayRequests; waktu di-freeze untuk tes expiry. Provider nyata tidak dipanggil CI.

## Katalog pengujian per plan

Skenario berikut dipindahkan dari masing-masing plan. Setiap plan baru Done bila skenarionya lulus atau penyimpangannya dicatat. Interaksi browser yang tidak diotomasi dicatat sebagai review manual, bukan tes otomatis.

### Plan1 setup dan fondasi UI
- SetupSmokeTest: /, /up, koneksi DB testing terpisah, migration framework tersedia, locale query/session/default, header request_id.
- UiPreviewTest: /dev/ui, /dev/admin, /dev/user, /dev/guest 200 di local/testing dan 404 di production; label id/en/ja; escaping konten sintetis; satu bundle/CSRF meta/toast region per halaman; menu tanpa route nonaktif.
- UiComponentContractTest: button type/disabled/loading (aria-busy, spinner, label tetap), link-button berbagi style, password tanpa old value, label/help/error association lewat FieldState termasuk nama array, attribute forwarding, escaping prop/slot, variant tidak dikenal gagal di local/testing.
- Envelope JSON: sukses, 401/403/404/409/419/422/429/503/500 dengan kode error terjemahan, request_id, Retry-After pada 429, tanpa bocor detail internal, locale invalid 422.
- API helper (Node): same-origin/prefix restriction, header CSRF pada mutation, parsing envelope, 401/419 memancarkan event, 422 fields, 429, invalid JSON/non-envelope, network vs abort, tanpa retry write.
- Review manual gallery pada 375x812, 768x1024, 1440x900: keyboard/focus, drawer, modal (Escape, outside click, focus trap/restore), dropdown, tabs, aria-live toast, contrast teks ≥4.5:1, reduced motion, tanpa flash x-cloak.

### Plan2 akun dan akses
- IdentityAccessTest dan AccountProvisioningTest: login/logout, email case, akun nonaktif, password change wajib (halaman dan JSON 403 PASSWORD_CHANGE_REQUIRED), login throttling, admin/non-admin, assignment role/team/editor invalid, perlindungan admin terakhir dengan lock, audit tanpa password, user dengan dua team/beberapa role tanpa izin global.
- Reset password/deactivation menutup session lama (database session dan AuthenticateSession); ganti password menjaga sesi saat ini; CSRF dan session setelah logout; UI id/en/ja dan error validasi.
- Perlindungan admin terakhir dengan dua koneksi PostgreSQL bersamaan; verifikasi ulang aktor admin di dalam transaksi.
- `POST /api/v1/preferences` (validasi locale, simpan preferred_locale dan session); `?lang` pengguna login menyimpan preferred_locale; locale session diisi saat login.
- `corporate:create-admin` (prompt password, validasi bersama, reference seed) dan `corporate:demo-accounts` (menolak production); menu admin per role; checkbox array mempertahankan old input.

### Plan3 virtual office
- OfficeSceneTest: guest dialihkan, kelima route, locale valid/default/invalid, label tiga bahasa, user nonaktif, context sesuai scene.
- Resolver gambar: mode placeholder menghasilkan URL dengan seed tetap per scene dan tanpa data pengguna; mode local menunjuk `public/assets/corporate`; production menolak/men-warning mode placeholder.
- BrandingSettingsTest: hanya admin, validasi panjang/locale, escaping nama/welcome, audit.
- Review manual 375x812, 768x1024, 1440x900: navigasi, keyboard focus, contrast, image fallback, reduced motion, tombol suara/narrator tanpa autoplay, overflow.

### Plan4 CMS pengetahuan
- KnowledgePublicationTest: draft incomplete, publikasi lengkap, dua publish bersamaan (transaksi PostgreSQL), clone immutable, archive, expiry, versi unik, izin editor/admin.
- KnowledgeAccessTest: audience common, department match/mismatch, kategori, resource langsung 404, payload XSS.
- KnowledgeRetrievalTest: query id/en/ja, alias silang bahasa, normalisasi Unicode NFKC, bigram CJK, ranking dan tie-break, no match, wildcard LIKE di-escape, tidak ada evidence nonaktif.
- Partial unique index (satu published dan satu draft per artikel) di PostgreSQL; slug terkunci setelah terbit; tanggal timezone tampilan → UTC; API `/api/v1/knowledge` (422 locale/q invalid, 404 artikel tidak diizinkan); `KnowledgeDemoSeeder` menolak production; menu CMS hanya untuk admin/editor berdepartemen.

### Plan5 percakapan dan AI
- ConversationOwnershipTest, ConversationRetryTest, ModelGatewayTest, GroundedAnswerTest, ContextLifecycleTest.
- Kasus: malformed JSON, finish length, timeout, 429/500 provider, tool call saat registry kosong, source palsu, no source, locale switch, revoked access, dua tab, stale lease, duplikat submit, response hilang, account nonaktif saat model berjalan. Dedup/lease diuji pada PostgreSQL.
- Gateway membuktikan endpoint/model/format request sesuai provider. Smoke provider nyata opt-in dengan data sintetis; tanpa credential dicatat belum dijalankan.
- `reasoning_effort` hanya dikirim bila dikonfigurasi; base URL dengan `/v1` atau HTTP di production ditolak; `LOCAL_LLM_CA_BUNDLE`; balasan deterministik (salam/terima kasih/navigasi) tanpa memanggil model; history `stale`/`unavailable`; listener `UserSessionsRevoked` menggagalkan turn berjalan; chat.js (retry key sama, polling 202, logout membersihkan storage) dan safe-card-renderer (tipe/path tidak sah dibuang).
- Evaluasi khusus model `qwen3.6-35b-a3b-uncensored-hauhaucs-aggressive` (label *uncensored*): prompt injection, permintaan di luar kebijakan, dan konten tidak pantas; bandingkan dengan model instruksi standar sebelum pilot.

### Plan6 tiket HR dan IT
- TicketDraftTest, TicketWorkflowTest, StaffTicketAccessTest, TicketMessageDeliveryTest, TicketNotificationTest, TicketConcurrencyTest.
- Kasus: invalid category/team, owner mismatch, expired/edited draft, duplicate confirm dengan key sama maupun berbeda, concurrent confirm, stale version, illegal transition, staff tim lain, isolasi internal note, replay pesan, reply setelah closed, kehilangan respons.
- Concurrency memakai dua koneksi/proses PostgreSQL pada database testing nyata tanpa test transaction pembungkus; buktikan satu tiket dan satu efek notifikasi/event. Delivery diuji dengan membaca history dari session pemohon setelah staf membalas.
- Manual: alur employee→staff→employee termasuk polling 5 detik.
- Tambahan dari implementasi: edit draft memperpanjang kedaluwarsa; catatan resolve/reopen menjadi pesan publik; gate `tickets.staff` (admin tanpa penugasan 403 halaman, 404 API); notifikasi tidak memuat deskripsi/catatan dan path mengikuti akses saat ini; `x-ticket-thread` berhenti polling saat tab tersembunyi/offline; limiter `ticket-writes`; referensi `CORP-YYYYMMDD-XXXXXXXX` unik.

### Plan7 tool AI dan workflow
- ToolRegistryTest: semua schema, unknown tool/key, JSON rusak, enum/range invalid, scope, penolakan side effect.
- ToolLoopTest: protokol request/tool_call_id, limit ronde/call/invocation, deadline, provider tanpa dukungan tool (TOOL_CAPABILITY_UNAVAILABLE).
- CorporateJourneyTest: SOP → source card → minta HR/IT → draft tersimpan sekali → edit → konfirmasi → tiket → staf membalas → pemohon melihat balasan. Replay turn, provider gagal setelah draft, draft berbeda dalam satu turn, ticket card canonical, XSS seluruh card. Fake sequence membuktikan kompatibilitas wire format; manual journey tiga bahasa.

## Checklist verifikasi teknis

1. Bangun infrastruktur di atas, tulis tes dari katalog, lalu jalankan seluruh PHPUnit suite terhadap PostgreSQL testing, termasuk concurrency integration terpisah, composer validate, Pint check, npm run lint:ui, npm run test:ui, frontend build, composer audit, dan audit npm runtime dependencies. Temuan audit ditriase berdasarkan paket/jalur aktual, bukan diabaikan dengan alasan dev.
2. Jalankan fresh install pada database staging kosong menggunakan migrate + reference seed, bukan migrate:fresh pada database berisi data. Buat admin melalui command secret prompt.
3. Verifikasi restore backup ke staging dan ulangi journey SOP→tiket→balasan→closed tiga bahasa, termasuk role/team isolation dan source revoked.
4. Verifikasi /dev/*404 production, aset visual final aktif tanpa placeholder remote, dan theme/component reuse; periksa mobile/desktop, keyboard, reduced motion, dialog focus, CSRF/session expiry, XSS cards/staff text, offline/poll recovery, dan no-provider fallback.
5. Build/config/route cache pada staging, restart worker jika ada, dan uji scheduler/retensi dry-run sebelum purge dijadwalkan.
6. Jalankan pilot dengan akun perwakilan employee/editor/admin/staff HR/staff IT dan data sintetis dulu. Catat masalah dan perbaikannya.

## Kriteria selesai dan rollout

Artefak deployment, runbook, recovery/retensi tests, katalog pengujian Plan1–7, suite CI lokal, dan UI review harus selesai; status Plan1–7 diperbarui ke Done sesuai hasilnya. Pilot/production deployment merupakan hasil terpisah yang hanya dapat dicatat lulus jika benar-benar dijalankan pada target.

Rollout: staging → pilot terbatas → perluasan akses setelah quality gate lulus. Perubahan aplikasi rollback menggunakan release sebelumnya; migration data menggunakan forward fix jika rollback menghapus data. Lakukan backup sebelum migrasi production. Jangan otomatis membuat commit, push, atau publish dari instruksi menulis plan.

[Deployment Laravel](https://laravel.com/framework/docs/13.x/deployment) menjelaskan kebutuhan server serta caching; nilai timeout, jadwal, dan gate di atas adalah keputusan proyek.

## Catatan hasil implementasi

### Hasil (5 Oktober 2026)

**Status: In Progress.** Semua artefak dan pemeriksaan yang bisa dijalankan lokal selesai. Tersisa: run suite di PostgreSQL (menunggu `.env.testing` dari pengguna), CI di GitHub, review manual mobile/aksesibilitas, staging, dan pilot (menunggu input deployment).

**Keputusan pengguna**: database tes PostgreSQL diisi pengguna di `.env.testing` (tidak dilacak); aset visual final berupa ilustrasi SVG flat buatan sendiri.

#### Artefak yang dibuat

- Infrastruktur tes: `.env.testing.example` (+ `.env.testing` lokal tanpa password), `TestCase::withoutVite()`, trait `BuildsCorporateData` dan `TalksToAssistant`, worker `tests/Support/concurrent-action.php` untuk race antarproses. `npm run test:ui` kini menjalankan semua `tests/js/*.test.js`.
- `scripts/check-ui-conventions.mjs` (`npm run lint:ui`, hanya Node built-ins): literal warna, palette Tailwind, bg-white/text-black, arbitrary color, inline style warna, Alpine CDN/`Alpine.start()` ganda, class warna dinamis. Tidak ada pengecualian yang diperlukan.
- CI `.github/workflows/ci.yml`: PostgreSQL 17, PHP 8.4, Node 22; composer validate, install dari lock, PHPUnit, Pint, npm ci, lint:ui, test:ui, build, composer audit, npm audit --omit=dev.
- Operasional: `corporate:recover-turns` (tiap menit, juga heartbeat scheduler), `corporate:purge-data [--dry-run]` (02:00 Asia/Jakarta, withoutOverlapping, onOneServer, batch 200; tiket dihapus sebelum draft karena FK sirkular), `/admin/operations` (admin; metrik 7 hari + tiga alert pilot), `/api/v1/admin/readiness` (admin; DB, cache, setting provider tanpa memanggil model, heartbeat scheduler/worker). Kolom baru `ai_turns.outcome` (migrasi `000010`) untuk menghitung retrieval no-match. Modul baru `App\Modules\Operations` (retensi lintas modul tidak cocok di satu modul yang ada).
- `docs/OPERATIONS.md`; `deploy/nginx.conf.example`, `supervisor.conf.example`, `php-production.ini.example`, `env.production.example`, `deploy/scripts/backup.sh`, `restore-staging.sh`.
- Aset visual final: `scripts/generate-visual-assets.py` → `public/assets/corporate/{reception,knowledge,hr,it,requests,assistant,logo}.svg`, `public/favicon.svg|ico`, `apple-touch-icon.png`, ikon 192/512, `site.webmanifest`; brand mark di `x-navigation.brand` (SVG inline memakai token tema). Semua dibuat sendiri, tanpa lisensi pihak ketiga. `SCENE_IMAGE_SOURCE` default `local`; production selalu `local` walau dikonfigurasi placeholder. Ikon `chart-bar` ditambahkan ke whitelist `ComponentStyles` (perubahan komponen bersama).
- Evaluasi: `docs/evals/` (10 artikel sintetis, 90 skenario, `build_corpus.py`), perintah `corporate:eval` (hanya local/testing, database bernama `*eval*`).

#### Pemeriksaan otomatis (lokal, Windows)

| Pemeriksaan | Hasil |
|---|---|
| PHPUnit pada SQLite `:memory:` | 190 lulus, 5 dilewati (grup `postgres`), 885 asersi |
| PHPUnit pada PostgreSQL 17 | **Belum dijalankan**: `.env.testing` belum berisi password |
| Pint `--test` | Lulus (setelah memperbaiki format beberapa file Plan 2/6) |
| `composer validate --strict` | Lulus |
| `npm run lint:ui` | Lulus (96 file) |
| `npm run test:ui` | 26 lulus |
| `npm run build` | Lulus |
| `composer audit`, `npm audit` (runtime dan semua) | 0 kerentanan |
| CI GitHub Actions | Belum dijalankan (repo belum di-push) |

Cakupan katalog: SetupSmoke/UiPreview/UiComponentContract/ApiEnvelope (Plan1, sudah ada), `IdentityAccessTest` (Plan2), `OfficeSceneTest` (Plan3), `KnowledgeTest` (Plan4), `ModelGatewayTest` + `ConversationTest` (Plan5), `TicketWorkflowTest` (Plan6), `AssistantToolsTest` (Plan7 registry, loop, journey), `OperationsTest` (retensi, metrik, readiness, jadwal), `PostgresConcurrencyTest` (konfirmasi bersamaan, dua admin saling menurunkan, publish bersamaan, partial unique index; dua proses PHP nyata). Node: `api-client`, `safe-card-renderer`, `ui-conventions`.

Temuan saat menulis tes: `public/hot` membuat view menunjuk Vite dev server sehingga tes kini memanggil `withoutVite()`; tes smoke lama masih menguji landing page yang dihapus Plan 3 dan diperbarui.

#### Review manual (browser, akun demo lokal)

Diperiksa di desktop: ilustrasi resepsionis, karakter, dan logo baru tampil; chat "printer macet, buatkan permintaan ke IT" → kartu draft → konfirmasi dua langkah → kartu "Permintaan terkirim" dengan nomor referensi dan status Terbuka; tidak ada error console. Perbaikan dari review: tombol "Periksa dan ubah" ganda di bawah kartu draft disembunyikan. Review 375×812/768×1024, keyboard/focus, reduced motion, dan kontras belum dilakukan menyeluruh (lihat checklist).

#### Evaluasi model nyata

`docs/evals/results/2026-10-05-qwen36-35b-a3b-uncensored-hauhaucs-aggressive.md`: provider LM Studio, model `qwen3.6-35b-a3b-uncensored-hauhaucs-aggressive`, prompt `corporate-v2`, tools aktif, `reasoning_effort=none`. **90/90** (30/30 per bahasa), semua akses terlarang ditolak, 0 tiket tanpa klik, 0 efek ganda saat retry, latensi p50 3,4 s / p95 7,0 s, sekitar 162 ribu token. Pemeriksaan bersifat otomatis (sumber, fakta kunci, efek domain); jawaban perlu dibaca manusia. Corpus kecil dan sintetis: hasil ini bukan jaminan akurasi pada knowledge perusahaan nyata. Label model *uncensored/aggressive* tetap menjadi risiko kebijakan yang perlu diputuskan sebelum pilot.

#### Staging, production, dan pilot

Belum dilakukan. Input yang belum ada: hostname/TLS, server PHP 8.4 + PostgreSQL 17, kredensial database, lokasi backup privat. Langkahnya ada di `docs/OPERATIONS.md` bagian 2, 4, dan 7.

#### Status plan

Plan1, Plan3, Plan7 menjadi **Done** (seluruh skenario katalognya lulus dan tidak bergantung PostgreSQL). Plan2, Plan4, Plan5, Plan6 tetap **Implemented**: skenario non-konkurensi lulus, tetapi skenario wajib PostgreSQL (lock admin terakhir, publish bersamaan, dedup/lease, konfirmasi bersamaan) baru bisa dinyatakan lulus setelah `composer test` di PostgreSQL. Plan8 menjadi Done setelah run PostgreSQL, CI hijau, review manual lengkap, dan staging/pilot.

#### Checklist untuk pengguna

1. Isi `DB_PASSWORD` di `.env.testing`, buat role `corporate_ai` dan database `corporate_ai_test`, lalu jalankan `composer test` (atau minta saya menjalankannya).
2. Tinjau gaya ilustrasi/logo di `/office`; minta revisi bila perlu (`scripts/generate-visual-assets.py`).
3. Cek tampilan di ponsel (375×812) dan tablet: chat dock, kartu draft, wizard `?draft=`, `/admin/operations`.
4. Baca jawaban di laporan evaluasi; putuskan apakah model *uncensored* boleh dipakai untuk pilot atau perlu model instruksi standar (jalankan ulang `corporate:eval`).
5. Siapkan input deployment (bagian 1 `docs/OPERATIONS.md`) bila ingin lanjut ke staging.
