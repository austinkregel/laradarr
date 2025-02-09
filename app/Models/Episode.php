<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Overtrue\LaravelFavorite\Traits\Favoriteable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Episode extends Model implements HasMedia
{
    use InteractsWithMedia, Favoriteable;
    protected $fillable = [
        'name',
        'description',
        'episode_number',
        'has_file',
        'runtime',
        'tvdb_id',
        'sonarr_episode_file_id',
        'sonarr_episode_id',
        'sonarr_series_id',
        'season_id',
        'aired_at',
    ];

    protected $casts = [
        'aired_at' => 'datetime',
    ];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function show()
    {
        return $this->belongsToMany(
            Show::class,
            'seasons',
            'id',
            'show_id',
            'season_id',
        );
    }

    public function watchers()
    {
        return $this->belongsToMany(User::class, 'watched_episodes')
            ->withPivot('watched_at')
            ->withTimestamps();
    }

    public function watchedBy(User $user)
    {
        return $this->watchers()->where('user_id', $user->id);
    }
}
