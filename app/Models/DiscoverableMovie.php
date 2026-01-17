<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
class DiscoverableMovie extends Model
{
    protected $fillable = [
        'tmdb_id',
        'trakt_id',
        'imdb_id',
        'name',
        'slug',
        'description',
        'release_year',
        'release_date',
        'runtime',
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
        'release_date' => 'date',
        'last_synced_at' => 'datetime',
        'genres' => 'array',
        'origin_countries' => 'array',
        'is_animated' => 'boolean',
        'vote_average' => 'float',
        'vote_count' => 'int',
        'popularity' => 'float',
        'runtime' => 'int',
    ];

    /**
     * Exclude movies already present in the real library (`movies` table).
     */
    public function scopeNotInLibrary(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('movies')
                ->where(function ($where) {
                    $where
                        ->whereColumn('movies.tmdb_id', 'discoverable_movies.tmdb_id')
                        ->orWhereColumn('movies.trakt_id', 'discoverable_movies.trakt_id')
                        ->orWhereColumn('movies.imdb_id', 'discoverable_movies.imdb_id');
                });
        });
    }
}

