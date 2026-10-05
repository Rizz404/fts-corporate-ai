<?php

namespace App\Models;

use App\Modules\Identity\Enums\SupportTeamCode;
use Database\Factories\SupportTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'is_active'])]
class SupportTeam extends Model
{
    /** @use HasFactory<SupportTeamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'code' => SupportTeamCode::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @param  Builder<SupportTeam>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
