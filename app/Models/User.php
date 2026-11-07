<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\VenueManagerStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * NOTE: role and points are intentionally NOT mass assignable.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile',
        'date_of_birth',
        'password',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'date_of_birth',
        'mobile',
    ];

    protected $attributes = [
        'role' => 'punter',
        'points' => 0,
        'is_banned' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'role' => UserRole::class,
            'points' => 'integer',
            'is_banned' => 'boolean',
        ];
    }

    /** Contributor levels: a little fun on the profile page. */
    public const LEVELS = [
        0 => 'Pot Rookie',
        50 => 'Schooner Regular',
        150 => 'Pint Pro',
        400 => 'Jug Legend',
        1000 => 'Keg Royalty',
    ];

    /** @return array{name: string, next: ?string, progress: int, to_next: int} */
    public function level(): array
    {
        $thresholds = array_keys(self::LEVELS);
        $current = 0;
        foreach ($thresholds as $t) {
            if ($this->points >= $t) {
                $current = $t;
            }
        }
        $idx = array_search($current, $thresholds, true);
        $next = $thresholds[$idx + 1] ?? null;

        return [
            'name' => self::LEVELS[$current],
            'next' => $next !== null ? self::LEVELS[$next] : null,
            'progress' => $next === null ? 100 : (int) floor(($this->points - $current) / ($next - $current) * 100),
            'to_next' => $next === null ? 0 : $next - $this->points,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isPublican(): bool
    {
        return $this->role === UserRole::Publican;
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === ($role instanceof UserRole ? $role : UserRole::from($role))) {
                return true;
            }
        }

        return false;
    }

    /** True if the user is an approved manager of the venue (admins manage everything). */
    public function managesVenue(Venue|int $venue): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $venueId = $venue instanceof Venue ? $venue->id : $venue;

        return $this->isPublican() && $this->venueClaims()
            ->where('venue_id', $venueId)
            ->where('status', VenueManagerStatus::Approved)
            ->exists();
    }

    public function beerPrices(): HasMany
    {
        return $this->hasMany(BeerPrice::class);
    }

    public function pointsEvents(): HasMany
    {
        return $this->hasMany(PointsEvent::class);
    }

    public function submittedBeers(): HasMany
    {
        return $this->hasMany(Beer::class, 'submitted_by');
    }

    public function venueClaims(): HasMany
    {
        return $this->hasMany(VenueManager::class);
    }

    public function managedVenues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'venue_managers')
            ->using(VenueManager::class)
            ->withPivot(['id', 'status', 'business_name'])
            ->wherePivot('status', VenueManagerStatus::Approved->value)
            ->withTimestamps();
    }
}
