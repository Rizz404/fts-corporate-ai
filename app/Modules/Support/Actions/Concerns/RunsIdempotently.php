<?php

namespace App\Modules\Support\Actions\Concerns;

use App\Models\OperationReceipt;
use App\Modules\Support\Support\Receipts;
use App\Support\Http\ApiException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Receipt check → transaction(operation + receipt) → replay on a concurrent
 * duplicate. A replay never re-creates events, messages, or notifications.
 */
trait RunsIdempotently
{
    /**
     * @template T
     *
     * @param  Closure(): array{0: T, 1: string, 2: string}  $operation  Returns [result, resource_type, resource_public_id].
     * @param  Closure(OperationReceipt): T  $replay
     * @return array{result: T, replayed: bool}
     */
    protected function idempotent(int $actorId, string $type, string $key, string $fingerprint, Closure $operation, Closure $replay): array
    {
        if ($receipt = Receipts::replay($actorId, $type, $key, $fingerprint)) {
            return ['result' => $replay($receipt), 'replayed' => true];
        }

        try {
            $result = DB::transaction(function () use ($actorId, $type, $key, $fingerprint, $operation) {
                [$result, $resourceType, $resourceId] = $operation();
                Receipts::record($actorId, $type, $key, $fingerprint, $resourceType, $resourceId);

                return $result;
            });

            return ['result' => $result, 'replayed' => false];
        } catch (UniqueConstraintViolationException) {
            $receipt = Receipts::replay($actorId, $type, $key, $fingerprint);
            if ($receipt === null) {
                throw new ApiException('conflict', 409);
            }

            return ['result' => $replay($receipt), 'replayed' => true];
        }
    }
}
