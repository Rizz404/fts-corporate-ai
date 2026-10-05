<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency receipt: unique per actor + operation type + key. Stores the
 * payload fingerprint and a reference to the result, not the response.
 */
#[Fillable(['actor_id', 'operation_type', 'idempotency_key', 'fingerprint', 'resource_type', 'resource_public_id', 'created_at'])]
class OperationReceipt extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
