<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\TicketDraft;
use App\Models\User;
use App\Modules\Assistant\Tools\Tool;
use App\Modules\Assistant\Tools\ToolArgumentException;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Identity\Enums\SupportTeamCode;
use App\Modules\Support\Actions\PrepareTicketDraft;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Presenters\TicketCards;
use App\Modules\Support\Validation\TicketDraftRules;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Saves a reviewable draft through the Plan 6 action, never a ticket. At
 * most one draft per turn (unique originating_turn_id): the same payload
 * returns the first draft, a different payload is conflict
 * draft_already_prepared with the existing draft. The employee confirms with
 * a button (expected_revision + idempotency key); no text or tool call can.
 */
final class PrepareTicketTool implements Tool
{
    public function __construct(private readonly PrepareTicketDraft $prepare) {}

    public function name(): string
    {
        return 'prepare_ticket';
    }

    public function definition(): array
    {
        $categories = [];
        foreach (TicketCategory::BY_TEAM as $team => $codes) {
            $categories[] = $team.': '.implode(', ', $codes);
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Prepare a DRAFT request to the HR or IT team for the employee to review. It does not send anything; the employee must confirm the draft with a button. Categories per team — '.implode('; ', $categories).'.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'team_code' => ['type' => 'string', 'enum' => SupportTeamCode::values()],
                        'category' => ['type' => 'string', 'enum' => array_values(array_unique(array_merge(...array_values(TicketCategory::BY_TEAM))))],
                        'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
                        'description' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 5000],
                    ],
                    'required' => ['team_code', 'category', 'subject', 'description'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function validate(User $actor, array $arguments): array
    {
        $data = ToolArguments::validate(TicketDraftRules::normalize($arguments), TicketDraftRules::rules());

        $errors = TicketDraftRules::categoryErrors($data);
        if ($errors !== []) {
            throw new ToolArgumentException(array_map(fn (string $message) => [$message], $errors));
        }

        return [
            'team_code' => $data['team_code'],
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'],
        ];
    }

    public function execute(User $actor, array $arguments, ToolContext $context): ToolResult
    {
        $existing = $this->existing($context);
        if ($existing !== null) {
            return $this->fromExisting($existing, $actor, $arguments);
        }

        try {
            $draft = $this->prepare->handle($actor, $arguments, $context->conversation->public_id, $context->turn->id);
        } catch (UniqueConstraintViolationException) {
            // A concurrent call for this turn won the insert.
            return $this->fromExisting($this->existing($context) ?? throw new \RuntimeException('Draft for turn vanished.'), $actor, $arguments);
        }

        return $this->success($draft);
    }

    public function loggableInput(array $arguments): array
    {
        // The description stays in the draft; the log keeps a fingerprint.
        return [
            'team_code' => $arguments['team_code'],
            'category' => $arguments['category'],
            'subject_length' => mb_strlen($arguments['subject']),
            'description_length' => mb_strlen($arguments['description']),
            'payload_fingerprint' => hash('sha256', $arguments['subject']."\n".$arguments['description']),
        ];
    }

    private function existing(ToolContext $context): ?TicketDraft
    {
        return TicketDraft::query()->where('originating_turn_id', $context->turn->id)->first();
    }

    /**
     * @param  array{team_code: string, category: string, subject: string, description: string}  $arguments
     */
    private function fromExisting(TicketDraft $draft, User $actor, array $arguments): ToolResult
    {
        $same = $draft->revision === 1
            && hash_equals($draft->payload_fingerprint, PrepareTicketDraft::fingerprint($actor->id, $arguments, 1));

        if ($same) {
            return $this->success($draft);
        }

        return new ToolResult(
            ToolResult::CONFLICT,
            data: ['error' => 'draft_already_prepared', 'draft_id' => $draft->public_id],
            ui: [TicketCards::draft($draft)],
            actions: [TicketCards::reviewAction($draft)],
            operation: TicketCards::operation($draft),
            reference: ['draft_id' => $draft->public_id, 'error' => 'draft_already_prepared'],
        );
    }

    private function success(TicketDraft $draft): ToolResult
    {
        $draft->loadMissing('team:id,code');

        return new ToolResult(
            ToolResult::OK,
            data: ['draft_id' => $draft->public_id, 'confirmed' => false],
            ui: [TicketCards::draft($draft)],
            actions: [TicketCards::reviewAction($draft)],
            operation: TicketCards::operation($draft),
            reference: ['draft_id' => $draft->public_id],
            terminal: true,
            text: __('ui.assistant.tools.draft_ready', ['team' => __('ui.teams.'.$draft->team->code->value)]),
        );
    }
}
