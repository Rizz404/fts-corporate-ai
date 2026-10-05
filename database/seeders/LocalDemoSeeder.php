<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\SupportTeamCode;
use App\Modules\Support\Actions\AppendTicketMessage;
use App\Modules\Support\Actions\AssignTicket;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use App\Modules\Support\Actions\PrepareTicketDraft;
use App\Modules\Support\Actions\TransitionTicket;
use App\Modules\Support\Enums\MessageVisibility;
use App\Modules\Support\Enums\TicketStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local preview data with a FIXED, documented password so the app can be
 * explored right away. Refuses any environment other than local/testing.
 *
 *   php artisan db:seed --class=LocalDemoSeeder
 *
 * Accounts (password: LocalDemo#2026, no forced change):
 *   demo.admin@example.test      admin
 *   demo.editor@example.test     editor (General, HR)
 *   demo.staff.hr@example.test   staff HR
 *   demo.staff.it@example.test   staff IT
 *   demo.employee@example.test   employee (Engineering)
 *
 * Re-running resets these accounts to the fixed password and adds the
 * sample tickets only once.
 */
class LocalDemoSeeder extends Seeder
{
    public const PASSWORD = 'LocalDemo#2026';

    public function run(
        ProvisionUser $provision,
        PrepareTicketDraft $prepare,
        ConfirmTicketDraft $confirm,
        AssignTicket $assign,
        TransitionTicket $transition,
        AppendTicketMessage $append,
    ): void {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('LocalDemoSeeder only runs in local or testing environments.');
        }

        $this->call(ReferenceSeeder::class);

        $departments = Department::query()->pluck('id', 'slug');
        $users = [];

        foreach ($this->accounts($departments->all()) as $key => $account) {
            $user = User::query()->where('email', $account['email'])->first()
                ?? $provision->handle(null, $account + ['password' => self::PASSWORD]);

            // Known password and no forced change, for local preview only.
            $user->forceFill([
                'password' => self::PASSWORD,
                'must_change_password' => false,
                'is_active' => true,
            ])->save();
            ProvisionUser::syncAccess($user, $account);

            $users[$key] = $user->refresh();
        }

        $this->call(KnowledgeDemoSeeder::class);

        if (Ticket::query()->where('requester_id', $users['employee']->id)->exists()) {
            return;
        }

        // 1) Open IT ticket, waiting in the queue.
        $draft = $prepare->handle($users['employee'], [
            'team_code' => 'it',
            'category' => 'hardware',
            'subject' => '[DEMO] Laptop tidak mau menyala',
            'description' => "Contoh tiket sintetis.\nLaptop kantor tidak menyala sejak pagi, lampu indikator tidak hidup.",
        ]);
        $confirm->handle($users['employee'], $draft, 1, (string) Str::uuid());

        // 2) HR ticket in progress with a staff reply and an internal note.
        $draft = $prepare->handle($users['employee'], [
            'team_code' => 'hr',
            'category' => 'policy_question',
            'subject' => '[DEMO] Pertanyaan sisa cuti tahunan',
            'description' => 'Contoh tiket sintetis. Berapa sisa cuti tahunan saya untuk tahun ini?',
        ]);
        $ticket = $confirm->handle($users['employee'], $draft, 1, (string) Str::uuid())['ticket'];
        $ticket = $assign->handle($users['staff_hr'], $ticket, $users['staff_hr']->id, $ticket->version, (string) Str::uuid())['result'];
        $ticket = $transition->handle($users['staff_hr'], $ticket, TicketStatus::InProgress, $ticket->version, null, (string) Str::uuid())['result'];
        $append->handle($users['staff_hr'], $ticket, 'Halo, kami sedang mengecek data cuti Anda. Mohon tunggu sebentar.', MessageVisibility::Public, (string) Str::uuid(), asStaff: true);
        $append->handle($users['staff_hr'], $ticket, 'Catatan internal contoh: cek data di sistem HR.', MessageVisibility::Internal, (string) Str::uuid(), asStaff: true);
    }

    /**
     * @param  array<string, int>  $departments
     * @return array<string, array<string, mixed>>
     */
    private function accounts(array $departments): array
    {
        $base = ['preferred_locale' => 'id', 'support_teams' => [], 'editor_departments' => []];

        return [
            'admin' => ['name' => 'Demo Admin', 'email' => 'demo.admin@example.test', 'department_id' => $departments['general'], 'roles' => [RoleCode::Employee->value, RoleCode::Admin->value]] + $base,
            'editor' => ['name' => 'Demo Editor', 'email' => 'demo.editor@example.test', 'department_id' => $departments['general'], 'roles' => [RoleCode::Employee->value, RoleCode::Editor->value], 'editor_departments' => [$departments['general'], $departments['hr']]] + $base,
            'staff_hr' => ['name' => 'Demo Staff HR', 'email' => 'demo.staff.hr@example.test', 'department_id' => $departments['hr'], 'roles' => [RoleCode::Employee->value, RoleCode::Staff->value], 'support_teams' => [SupportTeamCode::Hr->value]] + $base,
            'staff_it' => ['name' => 'Demo Staff IT', 'email' => 'demo.staff.it@example.test', 'department_id' => $departments['it'], 'roles' => [RoleCode::Employee->value, RoleCode::Staff->value], 'support_teams' => [SupportTeamCode::It->value]] + $base,
            'employee' => ['name' => 'Demo Employee', 'email' => 'demo.employee@example.test', 'department_id' => $departments['engineering'], 'roles' => [RoleCode::Employee->value]] + $base,
        ];
    }
}
