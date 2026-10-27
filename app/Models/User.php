<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ProjectType;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rule;

#[Fillable(['first_name', 'middle_name', 'last_name', 'username', 'email', 'password', 'role', 'water_district_id', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Same defaults as the table, so a user created in this request is already active.
     */
    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Validation for a username: lowercase letters, numbers, dots, dashes and underscores.
     * It can't contain "@", which is how the login form tells a username from an email.
     *
     * @return list<mixed>
     */
    public static function usernameRules(?self $ignore = null): array
    {
        return ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique(self::class)->ignore($ignore)];
    }

    /**
     * @return HasMany<ProjectLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ProjectLog::class);
    }

    /**
     * @return BelongsTo<WaterDistrict, $this>
     */
    public function waterDistrict(): BelongsTo
    {
        return $this->belongsTo(WaterDistrict::class);
    }

    /**
     * "Juan M. Dela Cruz"
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([
            $this->first_name,
            $this->middle_name ? mb_substr($this->middle_name, 0, 1).'.' : null,
            $this->last_name,
        ])->filter()->implode(' '));
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Only water district users can request, and emergency loans are for Category C & D only.
     */
    public function canRequest(ProjectType $type): bool
    {
        return $this->hasRole(Role::Lwd)
            && $this->waterDistrict !== null
            && in_array($this->waterDistrict->category, $type->allowedCategories(), true);
    }
}
