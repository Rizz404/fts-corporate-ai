<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revision used as evidence by a turn. Stores a hash of the excerpt, never
 * the excerpt itself.
 */
#[Fillable(['turn_id', 'revision_id', 'locale', 'excerpt_hash'])]
class TurnEvidence extends Model
{
    protected $table = 'turn_evidence';

    public $timestamps = false;

    /**
     * @return BelongsTo<AiTurn, $this>
     */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(AiTurn::class, 'turn_id');
    }

    /**
     * @return BelongsTo<KnowledgeRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(KnowledgeRevision::class, 'revision_id');
    }
}
