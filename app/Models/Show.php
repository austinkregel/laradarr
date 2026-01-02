<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;
use Overtrue\LaravelFavorite\Traits\Favoriteable;
use Spatie\Tags\HasTags;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
class Show extends Model
{
    use Searchable;
    use HasTags;
    use Favoriteable;
    protected $fillable = [
        'sonarr_id',
        'name',
        'aliases',
        'slug',
        'description',
        'release_year',
        'season_count',
        'episode_count',
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
        'imdb_rating',
        'tmdb_rating',
        'trakt_rating',
        'community_rating',
        'genres',
        'available_dub_languages',
    ];

    protected $casts = [
        'aliases' => 'array',
        'genres' => 'array',
        'available_dub_languages' => 'array',
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

    public function completed()
    {
        return $this->hasManyThrough(User::class, CompletedShow::class, null, 'id', null, 'user_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'show_categories')->withTimestamps();
    }

    public function contentWarnings()
    {
        return $this->belongsToMany(ContentWarning::class, 'show_content_warnings')
            ->withPivot(['severity'])
            ->withTimestamps();
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

    public function scopeCompleteCollection($query)
    {
        return $query->where('has_complete_series', true);
    }

    /**
     * Only include shows where every episode has at least one media record.
     *
     * Notes:
     * - Shows with no episodes are excluded.
     * - Episodes may exist in the DB without their corresponding media synced yet.
     */
    public function scopeAllEpisodesWithMedia(Builder $query)
    {
        return $query
            ->whereHas('episodes')
            ->whereDoesntHave('episodes', fn (Builder $q) => $q->whereDoesntHave('media'));
    }

    /**
     * Only include shows that have at least one episode with media, but not all episodes have media.
     *
     * Notes:
     * - Shows with no episodes are excluded.
     * - Shows with all episodes having media are excluded.
     * - Shows with no episodes having media are excluded.
     */
    public function scopeIncompleteWithSomeMedia(Builder $query)
    {
        return $query
            ->whereHas('episodes.media')
            ->whereHas('episodes', fn (Builder $q) => $q->whereDoesntHave('media'));
    }

    public function scopeEnglishOnly($query)
    {
        return $query->whereHas(
            'episodes.media',
            fn ($query) => $query->whereJsonContains('custom_properties->languages', 'English')
        );
    }

    public function scopeHasMultipleSeasons(Builder $query)
    {
        return $query->where('season_count', '>', 1);
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
        return $query->whereHas('episodes.media', fn ($q) => $q->withAnyTags(['dub']));
    }

    public function scopeHasSubFiles(Builder $query)
    {
        return $query->whereHas('episodes.media', fn ($q) => $q->withAnyTags(['sub']));
    }

    public function scopeHasBothDubAndSub(Builder $query)
    {
        return $query->hasDubFiles()->hasSubFiles();
    }

    public function scopeHasDubLanguageFiles(Builder $query, string $language)
    {
        $tag = 'dub-'.Str::slug($language);
        return $query->whereHas('episodes.media', fn ($q) => $q->withAnyTags([$tag]));
    }

    public function scopeHasSubLanguageFiles(Builder $query, string $language)
    {
        $tag = 'sub-'.Str::slug($language);
        return $query->whereHas('episodes.media', fn ($q) => $q->withAnyTags([$tag]));
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
        $this->loadMissing('seasons.episodes.media');
        foreach ($this->seasons as $season) {
            foreach ($season->episodes as $episode) {
                foreach ($episode->media as $media) {
                    $mediaLangs = data_get($media, 'custom_properties.languages', []);
                    foreach ((array) $mediaLangs as $lang) {
                        if (!in_array($lang, $langs, true)) {
                            $langs[] = $lang;
                        }
                    }
                }
            }
        }

        return $langs;
    }
}
