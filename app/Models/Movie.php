<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;
use Overtrue\LaravelFavorite\Traits\Favoriteable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
class Movie extends Model implements HasMedia
{
    use Searchable;
    use HasTags;
    use Favoriteable;
    use InteractsWithMedia;

    protected $fillable = [
        'radarr_id',
        'name',
        'aliases',
        'slug',
        'description',
        'release_year',
        'released_at',
        'runtime',
        'movie_file_id',
        'is_available',
        'in_cinemas',
        'imdb_id',
        'tmdb_id',
        'tvdb_id',
        'trakt_id',
        'plex_id',
        'added_at',
        'path',
        'poster_image',
        'banner_image',
        'logo_image',
        'size_on_disk',
        'imdb_rating',
        'tmdb_rating',
        'trakt_rating',
        'community_rating',
        'genres',
        'available_dub_languages',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'added_at' => 'datetime',
        'in_cinemas' => 'datetime',
        'released_at' => 'datetime',
        'aliases' => 'array',
        'genres' => 'array',
        'available_dub_languages' => 'array',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'movie_categories')->withTimestamps();
    }

    public function contentWarnings()
    {
        return $this->belongsToMany(ContentWarning::class, 'movie_content_warnings')
            ->withPivot(['severity'])
            ->withTimestamps();
    }

    public function watchers()
    {
        return $this->belongsToMany(User::class, 'watched_movies')
            ->withPivot(['watched_at'])
            ->withTimestamps();
    }

    public function completed()
    {
        return $this->belongsToMany(User::class, 'completed_movies')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    #[SearchUsingPrefix(['id', 'name'])]
    #[SearchUsingFullText(['description'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'aliases' => $this->aliases,
        ];
    }

    public function scopeWithCategory(Builder $query, int $categoryId)
    {
        return $query->whereHas('categories', fn (Builder $q) => $q->where('categories.id', $categoryId));
    }

    public function scopeWithContentWarning(Builder $query, int $contentWarningId)
    {
        return $query->whereHas('contentWarnings', fn (Builder $q) => $q->where('content_warnings.id', $contentWarningId));
    }

    public function scopeMinRating(Builder $query, float $rating, string $source = 'imdb')
    {
        $column = match ($source) {
            'tmdb' => 'tmdb_rating',
            'trakt' => 'trakt_rating',
            'community' => 'community_rating',
            default => 'imdb_rating',
        };

        return $query->whereNotNull($column)->where($column, '>=', $rating);
    }

    public function scopeUnwatchedOnly($query)
    {
        return $query->whereDoesntHave(
            'watchers',
            fn ($query) => $query->where('user_id', auth()->id())
        );
    }

    public function scopeWithWatchedProgress(Builder $query)
    {
        return $query->whereHas(
            'watchers',
            fn ($query) => $query->where('user_id', auth()->id())
        )
            ->whereDoesntHave(
                'completed',
                fn ($query) => $query->where('user_id', auth()->id())
            );
    }

    public function scopeCompletedOnly(Builder $query)
    {
        return $query->whereHas(
            'completed',
            fn ($q) => $q->where('user_id', auth()->id())
        );
    }

    public function scopeHasDubFiles(Builder $query)
    {
        return $query->whereHas('media', fn ($q) => $q->withAnyTags(['dub']));
    }

    public function scopeHasSubFiles(Builder $query)
    {
        return $query->whereHas('media', fn ($q) => $q->withAnyTags(['sub']));
    }

    public function scopeHasBothDubAndSub(Builder $query)
    {
        return $query->hasDubFiles()->hasSubFiles();
    }

    public function scopeHasDubLanguageFiles(Builder $query, string $language)
    {
        $tag = 'dub-'.Str::slug($language);
        return $query->whereHas('media', fn ($q) => $q->withAnyTags([$tag]));
    }

    public function scopeHasSubLanguageFiles(Builder $query, string $language)
    {
        $tag = 'sub-'.Str::slug($language);
        return $query->whereHas('media', fn ($q) => $q->withAnyTags([$tag]));
    }

    /**
     * Convenience accessor used by UI/filters; prefers cached column, falls back to scanning media.
     */
    public function getAvailableDubLanguagesAttribute($value)
    {
        if (!empty($value)) {
            return is_array($value) ? $value : json_decode($value, true);
        }

        $langs = [];
        $this->loadMissing('media');
        foreach ($this->getMedia('movies') as $media) {
            $mediaLangs = data_get($media, 'custom_properties.languages', []);
            foreach ((array) $mediaLangs as $lang) {
                if (!in_array($lang, $langs, true)) {
                    $langs[] = $lang;
                }
            }
        }

        return $langs;
    }
}
