<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
class DiscoverableShow extends Model
{
    protected $fillable = [
        'tmdb_id',
        'trakt_id',
        'imdb_id',
        'name',
        'slug',
        'description',
        'release_year',
        'first_air_date',
        'poster_image',
        'backdrop_image',
        'genres',
        'origin_countries',
        'is_animated',
        'vote_average',
        'vote_count',
        'popularity',
        'status',
        'source',
        'last_synced_at',
    ];

    protected $casts = [
        'first_air_date' => 'date',
        'last_synced_at' => 'datetime',
        'genres' => 'array',
        'origin_countries' => 'array',
        'is_animated' => 'boolean',
        'vote_average' => 'float',
        'vote_count' => 'int',
        'popularity' => 'float',
    ];

    /**
     * Exclude shows already present in the real library (`shows` table).
     */
    public function scopeNotInLibrary(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('shows')
                ->where(function ($where) {
                    $where
                        ->whereColumn('shows.tmdb_id', 'discoverable_shows.tmdb_id')
                        ->orWhereColumn('shows.trakt_id', 'discoverable_shows.trakt_id')
                        ->orWhereColumn('shows.imdb_id', 'discoverable_shows.imdb_id');
                });
        });
    }
}

