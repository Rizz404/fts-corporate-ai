<?php

namespace App\Modules\Support\Support;

use App\Models\Ticket;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Display reference CORP-YYYYMMDD-XXXXXXXX (random suffix). The unique
 * index is the real guarantee; generation retries at most three times.
 */
final class TicketReference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 8; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $reference = 'CORP-'.now(config('corporate.timezone'))->format('Ymd').'-'.$suffix;

            if (! Ticket::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique ticket reference.');
    }

    public static function isValid(string $reference): bool
    {
        return (bool) preg_match('/^CORP-\d{8}-['.preg_quote(self::ALPHABET, '/').']{8}$/', $reference)
            && Str::length($reference) === 22;
    }
}
