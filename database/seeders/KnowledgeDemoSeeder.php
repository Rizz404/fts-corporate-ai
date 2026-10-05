<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/testing only: synthetic DEMO articles in id/en/ja covering general,
 * HR, IT, onboarding, and one department-only article. Not company policy.
 *
 *   php artisan db:seed --class=KnowledgeDemoSeeder
 */
class KnowledgeDemoSeeder extends Seeder
{
    public function run(CreateArticle $create, PublishRevision $publish): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('KnowledgeDemoSeeder only runs in local or testing environments.');
        }

        $this->call(ReferenceSeeder::class);

        $admin = User::query()->active()->withRole(RoleCode::Admin)->orderBy('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Create an admin first (corporate:create-admin or corporate:demo-accounts).');
        }

        $departments = Department::query()->pluck('id', 'slug');

        foreach ($this->articles() as $article) {
            if (KnowledgeArticle::query()->where('slug', $article['slug'])->exists()) {
                continue;
            }

            $created = $create->handle($admin, [
                'slug' => $article['slug'],
                'owner_department_id' => $departments[$article['owner']],
                'category' => $article['category'],
                'audience' => $article['audience'],
                'department_ids' => array_map(fn ($slug) => $departments[$slug], $article['departments'] ?? []),
                'effective_from' => null,
                'effective_until' => null,
                'translations' => $article['translations'],
            ]);

            $publish->handle($admin, $created);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        $t = fn (string $title, string $body, array $tags = [], array $aliases = []) => compact('title', 'body', 'tags', 'aliases');

        return [
            [
                'slug' => 'demo-company-overview',
                'owner' => 'general',
                'category' => 'general',
                'audience' => 'common',
                'translations' => [
                    'id' => $t('[DEMO] Gambaran umum perusahaan', "Contoh artikel sintetis.\n\nJam kerja kantor contoh: Senin–Jumat 09.00–18.00. Informasi ini hanya untuk demo dan bukan kebijakan resmi.", ['demo', 'perusahaan'], ['jam kerja', 'working hours', '勤務時間']),
                    'en' => $t('[DEMO] Company overview', "Synthetic sample article.\n\nSample office hours: Monday–Friday 09:00–18:00. This is demo content, not official policy.", ['demo', 'company'], ['office hours', 'jam kerja']),
                    'ja' => $t('[DEMO] 会社概要', "合成のサンプル記事です。\n\nサンプルの勤務時間：月曜〜金曜 9:00〜18:00。デモ用の内容であり、公式の規程ではありません。", ['デモ', '会社'], ['勤務時間', 'working hours']),
                ],
            ],
            [
                'slug' => 'demo-leave-request',
                'owner' => 'hr',
                'category' => 'hr',
                'audience' => 'common',
                'translations' => [
                    'id' => $t('[DEMO] Cara mengajukan cuti', "Contoh SOP sintetis.\n\n1. Diskusikan tanggal cuti dengan atasan.\n2. Ajukan permintaan ke HR minimal 7 hari sebelumnya.\n3. Tunggu konfirmasi HR.", ['cuti', 'hr'], ['leave', 'izin', '休暇']),
                    'en' => $t('[DEMO] How to request leave', "Synthetic sample SOP.\n\n1. Agree the dates with your manager.\n2. Send a request to HR at least 7 days ahead.\n3. Wait for HR confirmation.", ['leave', 'hr'], ['annual leave', 'cuti', '休暇']),
                    'ja' => $t('[DEMO] 休暇の申請方法', "合成のサンプル SOP です。\n\n1. 上司と日程を相談します。\n2. 7 日前までに人事へ申請します。\n3. 人事からの確認を待ちます。", ['休暇', '人事'], ['有給', 'leave', 'cuti']),
                ],
            ],
            [
                'slug' => 'demo-password-reset',
                'owner' => 'it',
                'category' => 'it',
                'audience' => 'common',
                'translations' => [
                    'id' => $t('[DEMO] Reset kata sandi akun', "Contoh panduan sintetis.\n\nJika lupa kata sandi aplikasi kantor, hubungi IT Helpdesk. Jangan membagikan kata sandi kepada siapa pun.", ['kata sandi', 'akun'], ['password', 'パスワード', 'lupa password']),
                    'en' => $t('[DEMO] Resetting your account password', "Synthetic sample guide.\n\nIf you forget an office application password, contact the IT Helpdesk. Never share your password.", ['password', 'account'], ['reset password', 'kata sandi', 'パスワード']),
                    'ja' => $t('[DEMO] アカウントのパスワードリセット', "合成のサンプルガイドです。\n\n社内アプリのパスワードを忘れた場合は IT ヘルプデスクに連絡してください。パスワードは誰とも共有しないでください。", ['パスワード', 'アカウント'], ['password', 'kata sandi']),
                ],
            ],
            [
                'slug' => 'demo-first-week',
                'owner' => 'general',
                'category' => 'onboarding',
                'audience' => 'common',
                'translations' => [
                    'id' => $t('[DEMO] Minggu pertama karyawan baru', "Contoh panduan onboarding sintetis.\n\nHari 1: aktivasi akun dan perkenalan tim. Hari 2–5: membaca SOP dasar di Pusat Pengetahuan.", ['onboarding'], ['karyawan baru', 'new hire', '新入社員']),
                    'en' => $t('[DEMO] Your first week', "Synthetic onboarding sample.\n\nDay 1: account activation and team introductions. Days 2–5: read the basic SOPs in the Knowledge Center.", ['onboarding'], ['new hire', 'karyawan baru', '新入社員']),
                    'ja' => $t('[DEMO] 入社 1 週目', "合成のオンボーディングサンプルです。\n\n1 日目：アカウントの有効化とチーム紹介。2〜5 日目：ナレッジセンターで基本的な SOP を読みます。", ['オンボーディング'], ['新入社員', 'new hire']),
                ],
            ],
            [
                'slug' => 'demo-engineering-code-review',
                'owner' => 'engineering',
                'category' => 'general',
                'audience' => 'departments',
                'departments' => ['engineering'],
                'translations' => [
                    'id' => $t('[DEMO] Panduan code review (khusus Engineering)', "Contoh artikel khusus departemen Engineering.\n\nSetiap perubahan ditinjau minimal satu rekan sebelum digabungkan.", ['engineering'], ['review kode', 'code review', 'コードレビュー']),
                    'en' => $t('[DEMO] Code review guide (Engineering only)', "Sample article for the Engineering department only.\n\nEvery change is reviewed by at least one peer before merging.", ['engineering'], ['code review', 'review kode']),
                    'ja' => $t('[DEMO] コードレビューガイド（エンジニアリング部のみ）', "エンジニアリング部向けのサンプル記事です。\n\nすべての変更はマージ前に少なくとも 1 名がレビューします。", ['エンジニアリング'], ['コードレビュー', 'code review']),
                ],
            ],
        ];
    }
}
