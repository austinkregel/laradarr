<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\TmdbServiceContract;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Services\Auth\CredentialStore;

class TmdbService implements TmdbServiceContract
{
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
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('tmdb', 'api_key') ?? (string) config('services.tmdb.api_key');
        $bearer = $store->get('tmdb', 'bearer_token') ?? (string) config('services.tmdb.bearer_token', '');

        $params = ['language' => 'en-US'];
        if ($bearer === '') {
            $params['api_key'] = $apiKey;
        }

        return $this->client()
            ->get("/tv/{$tmdbId}", $params)
            ->throw()
            ->json();
    }

    public function getTvShowExternalIds(int $tmdbId): array
    {
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('tmdb', 'api_key') ?? (string) config('services.tmdb.api_key');
        $bearer = $store->get('tmdb', 'bearer_token') ?? (string) config('services.tmdb.bearer_token', '');

        $params = [];
        if ($bearer === '') {
            $params['api_key'] = $apiKey;
        }

        return $this->client()
            ->get("/tv/{$tmdbId}/external_ids", $params)
            ->throw()
            ->json();
    }

    public function getMovieDetails(int $tmdbId): array
    {
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('tmdb', 'api_key') ?? (string) config('services.tmdb.api_key');
        $bearer = $store->get('tmdb', 'bearer_token') ?? (string) config('services.tmdb.bearer_token', '');

        $params = ['language' => 'en-US'];
        if ($bearer === '') {
            $params['api_key'] = $apiKey;
        }

        return $this->client()
            ->get("/movie/{$tmdbId}", $params)
            ->throw()
            ->json();
    }

    public function getMovieExternalIds(int $tmdbId): array
    {
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('tmdb', 'api_key') ?? (string) config('services.tmdb.api_key');
        $bearer = $store->get('tmdb', 'bearer_token') ?? (string) config('services.tmdb.bearer_token', '');

        $params = [];
        if ($bearer === '') {
            $params['api_key'] = $apiKey;
        }

        return $this->client()
            ->get("/movie/{$tmdbId}/external_ids", $params)
            ->throw()
            ->json();
    }
}


