<?php

namespace App\Models;

use App\Modules\Identity\Enums\RoleCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'department_id', 'preferred_locale', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Emails are stored trimmed and lowercase on every write path.
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : self::normalizeEmail($value));
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return BelongsToMany<SupportTeam, $this>
     */
    public function supportTeams(): BelongsToMany
    {
        return $this->belongsToMany(SupportTeam::class);
    }

    /**
     * Departments this user may author knowledge for (editor role).
     *
     * @return BelongsToMany<Department, $this>
     */
    public function editorDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'knowledge_editor_department');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithRole(Builder $query, RoleCode $role): void
    {
        $query->whereHas('roles', fn (Builder $roles) => $roles->where('code', $role->value));
    }
}
