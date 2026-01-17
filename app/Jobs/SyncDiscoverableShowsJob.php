<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TmdbServiceContract;
use App\Contracts\TraktTvServiceContract;
use App\Models\DiscoverableShow;
use App\Models\Show;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class SyncDiscoverableShowsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $targetCount = 250,
    ) {
        $this->onQueue('discovery-sync');
    }

    public function tags(): array
    {
        return ['discovery-sync', 'discovery:shows'];
    }

    public function displayName(): string
    {
        return self::class;
    }

    public function handle(
        TmdbServiceContract $tmdb,
        TraktTvServiceContract $trakt,
    ): void {
        $minYear = now()->subYears(30)->year;
        $minDate = sprintf('%d-01-01', $minYear);
        $maxDate = now()->format('Y-m-d');

        $genreMap = $this->buildGenreMap($tmdb->getTvGenres());

        $existingTmdbIds = Show::query()
            ->whereNotNull('tmdb_id')
            ->pluck('tmdb_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->flip();

        $existingTraktIds = Show::query()
            ->whereNotNull('trakt_id')
            ->pluck('trakt_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->flip();

        /** @var array<int, array> $byTmdbId */
        $byTmdbId = [];

        $pages = max(1, (int) ceil($this->targetCount / 20));
        $pages = min(10, $pages);

        // TMDB Discover
        for ($page = 1; $page <= $pages; $page++) {
            $json = $tmdb->discoverTvShows([
                'page' => $page,
                'sort_by' => 'popularity.desc',
                'first_air_date.gte' => $minDate,
                'first_air_date.lte' => $maxDate,
                'include_null_first_air_dates' => 'false',
            ]);
            $this->ingestTmdbResults($byTmdbId, (array) data_get($json, 'results', []), $genreMap);
        }

        // TMDB Trending (adds a different mix than discover)
        $trending = $tmdb->getTrendingTvShows('week', ['page' => 1]);
        $this->ingestTmdbResults($byTmdbId, (array) data_get($trending, 'results', []), $genreMap);

        // Trakt (augment IDs + add items that TMDB didn't yield)
        $this->ingestTraktShows($byTmdbId, $trakt->getTrendingShows(100), $minYear);
        $this->ingestTraktShows($byTmdbId, $trakt->getPopularShows(100), $minYear);

        // Trakt payloads don't include images; hydrate missing posters/backdrops via TMDB details.
        $this->hydrateMissingFromTmdb($byTmdbId, $tmdb);

        // Filter out anything already in the real library.
        $rows = collect($byTmdbId)
            ->values()
            ->filter(function (array $row) use ($existingTmdbIds, $existingTraktIds) {
                $tmdbId = isset($row['tmdb_id']) ? (int) $row['tmdb_id'] : null;
                $traktId = isset($row['trakt_id']) ? (int) $row['trakt_id'] : null;

                if ($tmdbId && isset($existingTmdbIds[$tmdbId])) {
                    return false;
                }
                if ($traktId && isset($existingTraktIds[$traktId])) {
                    return false;
                }
                return true;
            })
            ->take($this->targetCount);

        $now = now();
        $rows = $rows->map(function (array $row) use ($now) {
            $row['last_synced_at'] = $now;
            $row['updated_at'] = $now;
            $row['created_at'] = $row['created_at'] ?? $now;
            return $row;
        });

        $columns = $this->columnsForUpsert();
        $rows = $rows->map(fn (array $row) => $this->normalizeRow($row, $columns));

        $withTmdb = $rows->filter(fn (array $r) => !empty($r['tmdb_id']))->values();
        $withTraktOnly = $rows->filter(fn (array $r) => empty($r['tmdb_id']) && !empty($r['trakt_id']))->values();

        if ($withTmdb->isNotEmpty()) {
            DiscoverableShow::query()->upsert(
                $withTmdb->all(),
                ['tmdb_id'],
                $this->updatableColumns(),
            );
        }

        if ($withTraktOnly->isNotEmpty()) {
            DiscoverableShow::query()->upsert(
                $withTraktOnly->all(),
                ['trakt_id'],
                $this->updatableColumns(),
            );
        }
    }

    private function buildGenreMap(array $json): array
    {
        $map = [];
        foreach ((array) data_get($json, 'genres', []) as $genre) {
            if (!is_array($genre)) {
                continue;
            }
            $id = (int) ($genre['id'] ?? 0);
            $name = (string) ($genre['name'] ?? '');
            if ($id > 0 && $name !== '') {
                $map[$id] = $name;
            }
        }
        return $map;
    }

    /** @param array<int, array> $byTmdbId */
    private function ingestTmdbResults(array &$byTmdbId, array $results, array $genreMap): void
    {
        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tmdbId = (int) data_get($row, 'id', 0);
            if ($tmdbId <= 0) {
                continue;
            }

            $firstAirDate = data_get($row, 'first_air_date');
            $releaseYear = is_string($firstAirDate) && strlen($firstAirDate) >= 4
                ? (int) substr($firstAirDate, 0, 4)
                : null;

            $genres = array_values(array_filter(array_map(
                fn ($id) => $genreMap[(int) $id] ?? null,
                (array) data_get($row, 'genre_ids', [])
            )));
            $genres = $this->normalizeGenres($genres);
            $isAnimated = collect($genres)->contains(fn ($g) => is_string($g) && strtolower($g) === 'animation');

            $candidate = [
                'tmdb_id' => $tmdbId,
                'name' => (string) (data_get($row, 'name') ?? data_get($row, 'original_name') ?? ''),
                'description' => data_get($row, 'overview'),
                'release_year' => $releaseYear,
                'first_air_date' => $firstAirDate ?: null,
                'poster_image' => $this->tmdbImage(data_get($row, 'poster_path')),
                'backdrop_image' => $this->tmdbImage(data_get($row, 'backdrop_path')),
                'genres' => !empty($genres) ? $genres : null,
                'origin_countries' => !empty(data_get($row, 'origin_country')) ? array_values((array) data_get($row, 'origin_country')) : null,
                'is_animated' => $isAnimated,
                'vote_average' => data_get($row, 'vote_average'),
                'vote_count' => data_get($row, 'vote_count'),
                'popularity' => data_get($row, 'popularity'),
                'source' => 'tmdb',
            ];

            if (isset($byTmdbId[$tmdbId])) {
                $existing = $byTmdbId[$tmdbId];
                $candidate['trakt_id'] = $existing['trakt_id'] ?? null;
                $candidate['imdb_id'] = $existing['imdb_id'] ?? null;
                $candidate['slug'] = $existing['slug'] ?? null;
                if (($existing['source'] ?? '') === 'trakt') {
                    $candidate['source'] = 'both';
                }
            }

            $byTmdbId[$tmdbId] = array_filter($candidate, fn ($v) => $v !== '' && $v !== null);
        }
    }

    /** @param array<int, array> $byTmdbId */
    private function ingestTraktShows(array &$byTmdbId, array $payload, int $minYear): void
    {
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $show = is_array($row['show'] ?? null) ? $row['show'] : $row;
            $ids = (array) data_get($show, 'ids', []);

            $tmdbId = (int) data_get($ids, 'tmdb', 0);
            $traktId = (int) data_get($ids, 'trakt', 0);
            if ($tmdbId <= 0 && $traktId <= 0) {
                continue;
            }

            $year = (int) (data_get($show, 'year') ?? 0);
            if ($year > 0 && $year < $minYear) {
                continue;
            }

            $genres = (array) data_get($show, 'genres', []);
            $genres = array_values(array_filter(array_map(fn ($g) => is_string($g) ? $g : null, $genres)));
            $genres = $this->normalizeGenres($genres);
            $isAnimated = collect($genres)->contains(fn ($g) => is_string($g) && strtolower($g) === 'animation');

            $candidate = [
                'tmdb_id' => $tmdbId > 0 ? $tmdbId : null,
                'trakt_id' => $traktId > 0 ? $traktId : null,
                'imdb_id' => data_get($ids, 'imdb'),
                'slug' => data_get($ids, 'slug'),
                'name' => (string) (data_get($show, 'title') ?? ''),
                'description' => data_get($show, 'overview'),
                'release_year' => $year > 0 ? $year : null,
                'genres' => !empty($genres) ? $genres : null,
                'is_animated' => $isAnimated,
                'source' => 'trakt',
            ];

            if ($tmdbId > 0) {
                $existing = $byTmdbId[$tmdbId] ?? null;
                if (is_array($existing)) {
                    $candidate['source'] = ($existing['source'] ?? '') === 'tmdb' ? 'both' : ($existing['source'] ?? 'both');
                    $candidate['is_animated'] = (bool) ($existing['is_animated'] ?? false) || (bool) ($candidate['is_animated'] ?? false);
                    $byTmdbId[$tmdbId] = array_filter(array_merge($existing, $candidate), fn ($v) => $v !== '' && $v !== null);
                    continue;
                }

                $byTmdbId[$tmdbId] = array_filter($candidate, fn ($v) => $v !== '' && $v !== null);
                continue;
            }

            // Trakt-only item: key it by a stable synthetic key until upsert (handled later).
            $key = -$traktId;
            $byTmdbId[$key] = array_filter($candidate, fn ($v) => $v !== '' && $v !== null);
        }
    }

    private function tmdbImage(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return 'https://image.tmdb.org/t/p/original'.$path;
    }

    private function updatableColumns(): array
    {
        return [
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
            'updated_at',
        ];
    }

    private function columnsForUpsert(): array
    {
        return array_values(array_unique(array_merge(
            ['tmdb_id', 'trakt_id'],
            $this->updatableColumns(),
            ['created_at']
        )));
    }

    private function normalizeRow(array $row, array $columns): array
    {
        $normalized = [];
        foreach ($columns as $col) {
            $value = $row[$col] ?? null;

            // Upserts bypass Eloquent casting; encode JSON columns manually.
            if (in_array($col, ['genres', 'origin_countries'], true) && is_array($value)) {
                $encoded = json_encode($value);
                $value = $encoded !== false ? $encoded : null;
            }

            $normalized[$col] = $value;
        }
        return $normalized;
    }

    private function normalizeGenres(array $genres): array
    {
        return array_values(array_unique(array_filter(array_map(function ($genre) {
            if (!is_string($genre) || $genre === '') {
                return null;
            }

            $slug = Str::slug($genre);
            return $slug !== '' ? $slug : null;
        }, $genres))));
    }

    /** @param array<int, array> $byTmdbId */
    private function hydrateMissingFromTmdb(array &$byTmdbId, TmdbServiceContract $tmdb): void
    {
        $needs = collect($byTmdbId)
            ->filter(fn ($row) => is_array($row) && !empty($row['tmdb_id']) && (empty($row['poster_image']) || empty($row['backdrop_image'])))
            ->take(min(250, $this->targetCount));

        foreach ($needs as $key => $row) {
            $tmdbId = (int) ($row['tmdb_id'] ?? 0);
            if ($tmdbId <= 0) {
                continue;
            }

            try {
                $details = $tmdb->getTvShowDetails($tmdbId);
            } catch (Throwable) {
                continue;
            }

            $poster = $this->tmdbImage(data_get($details, 'poster_path'));
            $backdrop = $this->tmdbImage(data_get($details, 'backdrop_path'));

            if (empty($row['poster_image']) && $poster) {
                $row['poster_image'] = $poster;
            }
            if (empty($row['backdrop_image']) && $backdrop) {
                $row['backdrop_image'] = $backdrop;
            }

            $firstAirDate = data_get($details, 'first_air_date');
            if (empty($row['first_air_date']) && is_string($firstAirDate) && $firstAirDate !== '') {
                $row['first_air_date'] = $firstAirDate;
            }

            if (empty($row['release_year']) && is_string($firstAirDate) && strlen($firstAirDate) >= 4) {
                $row['release_year'] = (int) substr($firstAirDate, 0, 4);
            }

            $originCountries = data_get($details, 'origin_country');
            if (empty($row['origin_countries']) && is_array($originCountries) && !empty($originCountries)) {
                $row['origin_countries'] = array_values(array_filter(array_map(
                    fn ($c) => is_string($c) && $c !== '' ? $c : null,
                    $originCountries
                )));
            }

            if (empty($row['genres'])) {
                $genreNames = array_values(array_filter(array_map(
                    fn ($g) => is_array($g) ? (data_get($g, 'name') ?: null) : null,
                    (array) data_get($details, 'genres', [])
                )));

                $genreSlugs = $this->normalizeGenres($genreNames);
                $row['genres'] = !empty($genreSlugs) ? $genreSlugs : null;
            }

            if (!empty($row['genres']) && is_array($row['genres'])) {
                $row['is_animated'] = (bool) ($row['is_animated'] ?? false)
                    || collect($row['genres'])->contains(fn ($g) => is_string($g) && strtolower($g) === 'animation');
            }

            $byTmdbId[(int) $key] = array_filter($row, fn ($v) => $v !== '' && $v !== null);
        }
    }
}

