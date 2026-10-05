<?php

namespace App\Modules\Support\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.tickets.status.'.$this->value);
    }

    /**
     * Badge tone (domain presenter mapping, PLAN_06).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }
}
