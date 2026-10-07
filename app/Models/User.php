<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Sortable;
use App\Support\LikePattern;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasApiTokens<PersonalAccessToken|TransientToken> A cookie session (admin panel) has a TransientToken. */
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, Sortable;

    public const DEFAULT_SORT = 'name';

    public const SORTABLE = ['name', 'email', 'created_at'];

    /**
     * Mirrors the database default, so a freshly created user has the attribute without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Case-insensitive fragment of the name or email; % and _ in the term match literally.
     *
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $pattern = LikePattern::contains($term);

        $query->where(function (Builder $query) use ($pattern): void {
            $query
                ->whereRaw($query->qualifyColumn('name')." LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw($query->qualifyColumn('email')." LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    /**
     * @param  Builder<User>  $query
     * @param  'admin'|'user'  $role
     */
    public function scopeRole(Builder $query, string $role): void
    {
        $query->where('is_admin', $role === 'admin');
    }
}
