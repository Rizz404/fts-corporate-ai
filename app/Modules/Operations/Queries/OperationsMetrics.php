<?php

namespace App\Modules\Operations\Queries;

use App\Models\AiTurn;
use App\Models\Ticket;
use App\Models\ToolInvocation;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Support\Enums\TicketStatus;
use Carbon\CarbonImmutable;

/**
 * Admin metrics (counts and latencies only; never ticket or message
 * content) plus the pilot alert rules from PLAN_08:
 *  - AI error rate > 10% of at least 20 turns in the last hour,
 *  - p95 turn latency > 55 s over at least 20 turns in the last hour,
 *  - an open ticket without assignee for more than 24 hours.
 */
class OperationsMetrics
{
    public const WINDOW_DAYS = 7;

    public const ALERT_MIN_TURNS = 20;

    public const ALERT_ERROR_RATE = 0.10;

    public const ALERT_P95_MS = 55000;

    public const ALERT_UNASSIGNED_HOURS = 24;

    /** Outcomes that mean retrieval found nothing to ground an answer. */
    public const NO_MATCH_OUTCOMES = ['no_source', 'model_no_source'];

    /**
     * @return array<string, mixed>
     */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->subDays(self::WINDOW_DAYS);

        $turns = $this->turnStats($since);
        $lastHour = $this->turnStats($now->subHour());

        $openStatuses = [TicketStatus::Open->value, TicketStatus::InProgress->value];
        $unassignedCutoff = $now->subHours(self::ALERT_UNASSIGNED_HOURS);
        $staleUnassigned = Ticket::query()
            ->where('status', TicketStatus::Open->value)
            ->whereNull('assignee_id')
            ->where('created_at', '<', $unassignedCutoff)
            ->count();

        $oldestOpen = Ticket::query()->whereIn('status', $openStatuses)->min('created_at');

        return [
            'window_days' => self::WINDOW_DAYS,
            'turns' => $turns,
            'tool_validation_failures' => ToolInvocation::query()
                ->where('started_at', '>=', $since)
                ->where('status', 'validation_failed')
                ->count(),
            'tickets' => [
                'open' => Ticket::query()->whereIn('status', $openStatuses)->count(),
                'unassigned' => Ticket::query()->where('status', TicketStatus::Open->value)->whereNull('assignee_id')->count(),
                'unassigned_over_24h' => $staleUnassigned,
                'oldest_open_at' => $oldestOpen ? CarbonImmutable::parse($oldestOpen)->toIso8601String() : null,
            ],
            'alerts' => [
                'error_rate' => $lastHour['total'] >= self::ALERT_MIN_TURNS && $lastHour['error_rate'] > self::ALERT_ERROR_RATE,
                'latency_p95' => $lastHour['total'] >= self::ALERT_MIN_TURNS && ($lastHour['p95_ms'] ?? 0) > self::ALERT_P95_MS,
                'unassigned_tickets' => $staleUnassigned > 0,
            ],
            'last_hour' => $lastHour,
        ];
    }

    /**
     * @return array{total: int, completed: int, failed: int, error_rate: float, timeouts: int, no_match: int, p50_ms: ?int, p95_ms: ?int}
     */
    private function turnStats(CarbonImmutable $since): array
    {
        $rows = AiTurn::query()
            ->where('started_at', '>=', $since)
            ->whereIn('status', [TurnStatus::Completed->value, TurnStatus::Failed->value])
            ->get(['status', 'error_code', 'outcome', 'started_at', 'finished_at']);

        $failed = $rows->filter(fn (AiTurn $turn) => $turn->status === TurnStatus::Failed)->count();
        $durations = $rows
            ->filter(fn (AiTurn $turn) => $turn->finished_at !== null)
            ->map(fn (AiTurn $turn) => (int) $turn->started_at->diffInMilliseconds($turn->finished_at))
            ->sort()
            ->values()
            ->all();

        return [
            'total' => $rows->count(),
            'completed' => $rows->count() - $failed,
            'failed' => $failed,
            'error_rate' => $rows->isEmpty() ? 0.0 : round($failed / $rows->count(), 4),
            'timeouts' => $rows->whereIn('error_code', [GatewayException::TIMEOUT, 'turn_interrupted'])->count(),
            'no_match' => $rows->whereIn('outcome', self::NO_MATCH_OUTCOMES)->count(),
            'p50_ms' => self::percentile($durations, 0.50),
            'p95_ms' => self::percentile($durations, 0.95),
        ];
    }

    /**
     * Nearest-rank percentile of sorted values.
     *
     * @param  list<int>  $sorted
     */
    public static function percentile(array $sorted, float $p): ?int
    {
        if ($sorted === []) {
            return null;
        }

        $rank = (int) ceil($p * count($sorted));

        return $sorted[max(0, min(count($sorted) - 1, $rank - 1))];
    }
}
