<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\MetadataSyncServiceContract;
use App\Contracts\MovieClassificationServiceContract;
use App\Contracts\ShowClassificationServiceContract;
use App\Contracts\TmdbServiceContract;
use App\Contracts\TokenManagerContract;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MetadataSyncService implements MetadataSyncServiceContract
{
    public function __construct(
        protected TmdbServiceContract $tmdbService,
        protected ShowClassificationServiceContract $showClassificationService,
        protected MovieClassificationServiceContract $movieClassificationService,
        protected TokenManagerContract $tokenManager,
    ) {
    }

    protected function traktClient(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.trakt.base_url', 'https://api.trakt.tv'), '/');
        $token = $this->tokenManager->getTraktAccessToken();

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $token ? ('Bearer ' . $token) : '',
                'Content-Type' => 'application/json',
                'trakt-api-version' => '2',
                'trakt-api-key' => (string) config('services.trakt.client_id'),
            ])
            ->timeout((int) config('services.trakt.timeout', 15))
            ->retry(
                (int) data_get(config('services.trakt.retry'), 'times', 3),
                (int) data_get(config('services.trakt.retry'), 'sleep_ms', 250)
            );
    }

    /**
     * Fetch + persist metadata for a show (ratings, genres, external ids, and dub languages).
     */
    public function syncShowMetadata(Show $show): Show
    {
        $dirty = false;

        // TMDB: genres + TMDB rating + external ids
        if (!empty($show->tmdb_id)) {
            try {
                $details = $this->tmdbService->getTvShowDetails((int) $show->tmdb_id);

                $tmdbRating = data_get($details, 'vote_average');
                if ($tmdbRating !== null && (float) $show->tmdb_rating !== (float) $tmdbRating) {
                    $show->tmdb_rating = round((float) $tmdbRating, 1);
                    $dirty = true;
                }

                $genres = array_values(array_filter(array_map(
                    fn ($g) => is_array($g) ? ($g['name'] ?? null) : null,
                    (array) data_get($details, 'genres', [])
                )));
                if (!empty($genres) && $show->genres !== $genres) {
                    $show->genres = $genres;
                    $dirty = true;
                }

                // Backfill imdb_id if missing
                if (empty($show->imdb_id)) {
                    $external = $this->tmdbService->getTvShowExternalIds((int) $show->tmdb_id);
                    $imdbId = data_get($external, 'imdb_id');
                    if (!empty($imdbId)) {
                        $show->imdb_id = $imdbId;
                        $dirty = true;
                    }
                }
            } catch (RequestException $e) {
                // Fail-soft so one bad/missing TMDB credential doesn't take down the queue.
                report($e);
            }
        }

        // Trakt: rating + genres (fallback)
        if (!empty($show->trakt_id)) {
            $trakt = $this->traktClient()
                ->get("/shows/{$show->trakt_id}", [
                    'extended' => 'full',
                ])
                ->throw()
                ->json();

            $traktRating = data_get($trakt, 'rating');
            if ($traktRating !== null && (float) $show->trakt_rating !== (float) $traktRating) {
                $show->trakt_rating = round((float) $traktRating, 1);
                $dirty = true;
            }

            if (empty($show->genres)) {
                $genres = (array) data_get($trakt, 'genres', []);
                $genres = array_values(array_filter(array_map(fn ($g) => is_string($g) ? $g : null, $genres)));
                if (!empty($genres)) {
                    $show->genres = $genres;
                    $dirty = true;
                }
            }
        }

        // Dub languages: derived from existing episode media.
        // (Later we’ll also provide file-level dub/sub tags via MediaTaggingService.)
        $dubLangs = $show->available_dub_languages;
        if (empty($dubLangs)) {
            $langs = $show->available_dub_languages; // triggers accessor fallback
            if (!empty($langs) && $show->available_dub_languages !== $langs) {
                $show->available_dub_languages = $langs;
                $dirty = true;
            }
        }

        if ($dirty && $show->isDirty()) {
            $show->save();
        }

        // Auto-categorize + apply content warnings based on discovered metadata + keyword checks.
        $this->showClassificationService->classifyAndAttach($show);

        return $show->refresh();
    }

    /**
     * Fetch + persist metadata for a movie (ratings, genres, external ids, and dub languages).
     */
    public function syncMovieMetadata(Movie $movie): Movie
    {
        $dirty = false;

        // TMDB: genres + TMDB rating + external ids
        if (!empty($movie->tmdb_id)) {
            try {
                $details = $this->tmdbService->getMovieDetails((int) $movie->tmdb_id);

                $tmdbRating = data_get($details, 'vote_average');
                if ($tmdbRating !== null && (float) $movie->tmdb_rating !== (float) $tmdbRating) {
                    $movie->tmdb_rating = round((float) $tmdbRating, 1);
                    $dirty = true;
                }

                $genres = array_values(array_filter(array_map(
                    fn ($g) => is_array($g) ? ($g['name'] ?? null) : null,
                    (array) data_get($details, 'genres', [])
                )));
                if (!empty($genres) && $movie->genres !== $genres) {
                    $movie->genres = $genres;
                    $dirty = true;
                }

                // Backfill imdb_id if missing
                if (empty($movie->imdb_id)) {
                    $external = $this->tmdbService->getMovieExternalIds((int) $movie->tmdb_id);
                    $imdbId = data_get($external, 'imdb_id');
                    if (!empty($imdbId)) {
                        $movie->imdb_id = $imdbId;
                        $dirty = true;
                    }
                }
            } catch (RequestException $e) {
                // Fail-soft so one bad/missing TMDB credential doesn't take down the queue.
                report($e);
            }
        }

        // Trakt: rating + genres (fallback)
        if (!empty($movie->trakt_id)) {
            try {
                $trakt = $this->traktClient()
                    ->get("/movies/{$movie->trakt_id}", [
                        'extended' => 'full',
                    ])
                    ->throw()
                    ->json();

                $traktRating = data_get($trakt, 'rating');
                if ($traktRating !== null && (float) $movie->trakt_rating !== (float) $traktRating) {
                    $movie->trakt_rating = round((float) $traktRating, 1);
                    $dirty = true;
                }

                if (empty($movie->genres)) {
                    $genres = (array) data_get($trakt, 'genres', []);
                    $genres = array_values(array_filter(array_map(fn ($g) => is_string($g) ? $g : null, $genres)));
                    if (!empty($genres)) {
                        $movie->genres = $genres;
                        $dirty = true;
                    }
                }
            } catch (RequestException $e) {
                // Fail-soft so one bad/missing Trakt credential doesn't take down the queue.
                report($e);
            }
        }

        // Dub languages: derived from existing movie media.
        $dubLangs = $movie->available_dub_languages;
        if (empty($dubLangs)) {
            $langs = $movie->available_dub_languages; // triggers accessor fallback
            if (!empty($langs) && $movie->available_dub_languages !== $langs) {
                $movie->available_dub_languages = $langs;
                $dirty = true;
            }
        }

        if ($dirty && $movie->isDirty()) {
            $movie->save();
        }

        // Auto-categorize + apply content warnings based on discovered metadata + keyword checks.
        $this->movieClassificationService->classifyAndAttach($movie);

        return $movie->refresh();
    }
}


