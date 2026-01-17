<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TokenManagerContract;
use App\Contracts\TraktTvServiceContract;
use App\Exceptions\Integration\AuthenticationException;
use App\Services\DTOs\Trakt\ShowDTO;
use Illuminate\Support\Collection;

class TraktTvService extends BaseApiClient implements TraktTvServiceContract
{
    private ?int $userId = null;

    public function __construct(
        private readonly TokenManagerContract $tokenManager,
    ) {}

    /**
     * Create a user-scoped instance of the service.
     */
    public function forUser(int $userId): static
    {
        $instance = new static($this->tokenManager);
        $instance->userId = $userId;
        return $instance;
    }

    /**
     * Get the current user ID this service is scoped to (null = global).
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    protected function serviceKey(): string
    {
        return 'trakt';
    }

    protected function baseUrlOverride(): ?string
    {
        return (string) config('services.trakt.base_url', 'https://api.trakt.tv');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => (string) config('services.trakt.client_id'),
        ];
    }

    private function authHeaders(): array
    {
        $token = $this->tokenManager->getTraktAccessToken($this->userId);
        return $token ? ['Authorization' => 'Bearer ' . $token] : [];
    }

    /**
     * First create a device token. You'll use the returned device_code to exchange for an access token.
     * You'll need to click the provided link, and enter the generated code in the Trakt website.
     */
    public function createDeviceToken(): array
    {
        return $this->requestJson('POST', '/oauth/device/code', body: [
            'client_id' => (string) config('services.trakt.client_id'),
        ]);
    }

    /**
     * Once you've finished with the Trakt website, you can exchange the code for an access token.
     * This access token will be used to authenticate all future requests. Save the response somewhere.
     */
    public function exchangeForAccessToken(string $code): array
    {
        $json = $this->requestJson('POST', '/oauth/device/token', body: [
            'code' => $code,
            'client_id' => config('services.trakt.client_id'),
            'client_secret' => config('services.trakt.client_secret'),
        ]);

        // Persist in cache for runtime use (scoped to user if set).
        $this->tokenManager->storeTraktTokens($json, $this->userId);

        return $json;
    }

    public function refreshAccessToken(): array
    {
        return $this->tokenManager->refreshTraktTokens($this->userId);
    }

    /** @return Collection<int, ShowDTO> */
    public function findWatchedShows(): Collection
    {
        $json = $this->requestJsonAuthed('GET', '/sync/watched/shows');
        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['show']))
            ->map(fn (array $row) => ShowDTO::fromWatchedArray($row))
            ->values();
    }

    public function getTrendingShows(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return cache()->remember("trakt-shows-trending-$limit", now()->addMinutes(30), function () use ($limit) {
            return $this->requestJson('GET', '/shows/trending', query: [
                'limit' => $limit,
                'extended' => 'full',
            ]);
        });
    }

    public function getPopularShows(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return cache()->remember("trakt-shows-popular-$limit", now()->addMinutes(30), function () use ($limit) {
            return $this->requestJson('GET', '/shows/popular', query: [
                'limit' => $limit,
                'extended' => 'full',
            ]);
        });
    }

    public function getTrendingMovies(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return cache()->remember("trakt-movies-trending-$limit", now()->addMinutes(30), function () use ($limit) {
            return $this->requestJson('GET', '/movies/trending', query: [
                'limit' => $limit,
                'extended' => 'full',
            ]);
        });
    }

    public function getPopularMovies(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return cache()->remember("trakt-movies-popular-$limit", now()->addMinutes(30), function () use ($limit) {
            return $this->requestJson('GET', '/movies/popular', query: [
                'limit' => $limit,
                'extended' => 'full',
            ]);
        });
    }

    public function findShowsOnList(string $user, string $list): array
    {
        return cache()->remember("trakt-list-$user-$list", now()->addMinutes(30), function () use ($user, $list) {
            return $this->requestJsonAuthed('GET', "/users/{$user}/lists/{$list}/items/shows");
        });
    }

    public function fetchUserLists(string $user): array
    {
        $json = $this->requestJsonAuthed('GET', "/users/{$user}/lists");

        return array_map(function ($list) {
            return [
                'name' => $list['name'] ?? null,
                'description' => $list['description'] ?? null,
                'ids' => $list['ids'] ?? null,
            ];
        }, $json);
    }

    public function createList(string $name, array $shows): array
    {
        return $this->requestJsonAuthed('POST', '/users/me/lists', body: [
            'name' => $name,
            'description' => 'List created by the Laradarr app',
            'privacy' => 'private',
            'show_ids' => $shows,
        ]);
    }

    public function addShowsToList(string $user, string $list, array $showIds): array
    {
        return $this->requestJsonAuthed('POST', "/users/{$user}/lists/{$list}/items", body: [
            'shows' => array_map(function ($showId) {
                return [
                    'ids' => [
                        'trakt' => $showId,
                    ],
                ];
            }, $showIds),
        ]);
    }

    private function requestJsonAuthed(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
    ): array {
        try {
            return $this->requestJson($method, $path, query: $query, body: $body, headers: $this->authHeaders());
        } catch (AuthenticationException $e) {
            // Refresh once and retry.
            $this->tokenManager->refreshTraktTokens($this->userId);
            return $this->requestJson($method, $path, query: $query, body: $body, headers: $this->authHeaders());
        }
    }
}
