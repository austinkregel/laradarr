<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\TmdbServiceContract;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Services\Auth\CredentialStore;

class TmdbService implements TmdbServiceContract
{
    private function defaultParams(array $params = [], bool $includeLanguage = true): array
    {
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('tmdb', 'api_key') ?? (string) config('services.tmdb.api_key');
        $bearer = $store->get('tmdb', 'bearer_token') ?? (string) config('services.tmdb.bearer_token', '');

        if ($includeLanguage && !array_key_exists('language', $params)) {
            $params['language'] = 'en-US';
        }

        // If we're not using v4 bearer auth, fall back to v3 api_key query param.
        if ($bearer === '' && $apiKey !== '' && !array_key_exists('api_key', $params)) {
            $params['api_key'] = $apiKey;
        }

        return $params;
    }

    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.tmdb.base_url'), '/');
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $bearer = $store->get('tmdb', 'bearer_token') ?? (string) config('services.tmdb.bearer_token', '');

        $client = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->when($bearer !== '', fn (PendingRequest $r) => $r->withToken($bearer))
            ->timeout((int) config('services.tmdb.timeout', 15))
            ->retry(
                (int) data_get(config('services.tmdb.retry'), 'times', 3),
                (int) data_get(config('services.tmdb.retry'), 'sleep_ms', 250)
            );

        return $client;
    }

    public function getTvShowDetails(int $tmdbId): array
    {
        return $this->client()
            ->get("/tv/{$tmdbId}", $this->defaultParams())
            ->throw()
            ->json();
    }

    public function getTvShowExternalIds(int $tmdbId): array
    {
        return $this->client()
            ->get("/tv/{$tmdbId}/external_ids", $this->defaultParams(includeLanguage: false))
            ->throw()
            ->json();
    }

    public function getMovieDetails(int $tmdbId): array
    {
        return $this->client()
            ->get("/movie/{$tmdbId}", $this->defaultParams())
            ->throw()
            ->json();
    }

    public function getMovieExternalIds(int $tmdbId): array
    {
        return $this->client()
            ->get("/movie/{$tmdbId}/external_ids", $this->defaultParams(includeLanguage: false))
            ->throw()
            ->json();
    }

    public function discoverTvShows(array $params = []): array
    {
        return $this->client()
            ->get('/discover/tv', $this->defaultParams($params))
            ->throw()
            ->json();
    }

    public function discoverMovies(array $params = []): array
    {
        return $this->client()
            ->get('/discover/movie', $this->defaultParams($params))
            ->throw()
            ->json();
    }

    public function getTrendingTvShows(string $timeWindow = 'week', array $params = []): array
    {
        $timeWindow = in_array($timeWindow, ['day', 'week'], true) ? $timeWindow : 'week';

        return $this->client()
            ->get("/trending/tv/{$timeWindow}", $this->defaultParams($params))
            ->throw()
            ->json();
    }

    public function getTrendingMovies(string $timeWindow = 'week', array $params = []): array
    {
        $timeWindow = in_array($timeWindow, ['day', 'week'], true) ? $timeWindow : 'week';

        return $this->client()
            ->get("/trending/movie/{$timeWindow}", $this->defaultParams($params))
            ->throw()
            ->json();
    }

    public function getTvGenres(array $params = []): array
    {
        return $this->client()
            ->get('/genre/tv/list', $this->defaultParams($params))
            ->throw()
            ->json();
    }

    public function getMovieGenres(array $params = []): array
    {
        return $this->client()
            ->get('/genre/movie/list', $this->defaultParams($params))
            ->throw()
            ->json();
    }
}


