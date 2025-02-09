<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;

/**
 * @mixin Model
 */
class Show extends Model
{
    use Searchable;
    protected $fillable = [
        'sonarr_id',
        'name',
        'aliases',
        'slug',
        'description',
        'release_year',
        'seasons',
        'episodes',
        'type',
        'path',
        'poster_image',
        'banner_image',
        'logo_image',
        'size_on_disk',
        'tmdb_id',
        'imdb_id',
        'tvdb_id',
        'trakt_id',
        'has_complete_series',
        'plex_id',
    ];

    protected $casts = [
        'aliases' => 'array',
    ];

    public function seasons()
    {
        return $this->hasMany(Season::class);
    }

    public function episodes()
    {
        return $this->hasManyThrough(Episode::class, Season::class);
    }

    public function watchers()
    {
        return $this->hasManyThrough(WatchedEpisode::class, Season::class);
    }

    #[SearchUsingPrefix(['id', 'email'])]
    #[SearchUsingFullText(['bio'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'aliases' => $this->aliases,
        ];
    }

    public function scopeComplete($query)
    {
        return $query->where('has_complete_series', true);
    }

    public function scopeEnglishOnly($query)
    {
        return $query->whereHas(
            'episodes.media',
            fn ($query) => $query->whereJsonContains('custom_properties->languages', 'English')
        );
    }

    public function scopeUnwatchedOnly($query)
    {
        return $query->whereDoesntHave(
            'watchers',
            fn ($query) => $query->where('user_id', auth()->id())
        )
            ->where('has_complete_series', false);
    }

    public function scopeWithWatchedProgress($query)
    {
        return $query->whereHas(
            'watchers',
            fn ($query) => $query->where('user_id', auth()->id())
        );
    }
}
