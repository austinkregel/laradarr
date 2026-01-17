<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Overtrue\LaravelFavorite\Traits\Favoriter;

class User extends Authenticatable
{
    use HasApiTokens;
    use Favoriter;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
        ];
    }

    public function watchedEpisodes()
    {
        return $this->belongsToMany(Episode::class, 'watched_episodes')
            ->withPivot(['watched_at', 'season_id'])
            ->withTimestamps();
    }

    public function completedShows()
    {
        return $this->belongsToMany(Show::class, 'completed_shows')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    public function watchedMovies()
    {
        return $this->belongsToMany(Movie::class, 'watched_movies')
            ->withPivot('watched_at')
            ->withTimestamps();
    }

    public function completedMovies()
    {
        return $this->belongsToMany(Movie::class, 'completed_movies')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDefault(): bool
    {
        return $this->role === 'default';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Credential>
     */
    public function credentials()
    {
        return $this->hasMany(Credential::class);
    }

    public function hasTraktConnected(): bool
    {
        return Credential::where('service', 'trakt')
            ->where('key', 'access_token')
            ->where('user_id', $this->id)
            ->where('is_enabled', true)
            ->exists();
    }
}
