<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\PlexServiceContract;
use App\Exceptions\Integration\InvalidResponseException;
use App\Models\Movie;
use App\Models\Show;
use App\Services\DTOs\Plex\LibraryItemDTO;
use Illuminate\Support\Collection;

class PlexService implements PlexServiceContract
{
    public function getLibraries(): Collection
    {
        $json = $this->requestJson('GET', '/library/sections');

        $directories = $json['MediaContainer']['Directory'] ?? [];
        if (!is_array($directories)) {
            $directories = [];
        }

        $items = [];
        foreach ($directories as $dir) {
            $items[] = new LibraryItemDTO(
                key: (string) ($dir['key'] ?? ''),
                title: (string) ($dir['title'] ?? ''),
                type: (string) ($dir['type'] ?? ''),
                ratingKey: (string) ($dir['ratingKey'] ?? ''),
                guid: (string) ($dir['guid'] ?? ''),
            );
        }

        return collect($items);
    }

    public function getLibraryItems(int $libraryId, array $query = []): Collection
    {
        // Add default query parameters for better metadata
        // Note: type is determined by library type, so we don't set a default here
        // Plex types: '1' = movie, '2' = show, '8' = artist
        $defaultQuery = [
            'includeMeta' => '1',
            'includeAdvanced' => '1',
            'includeCollections' => '1',
            'includeExternalMedia' => '1',
        ];
        $query = array_merge($defaultQuery, $query);

        $json = $this->requestJson('GET', "/library/sections/{$libraryId}/all", query: $query);

        $metadata = $json['MediaContainer']['Metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $items = [];
        foreach ($metadata as $item) {
            $items[] = new LibraryItemDTO(
                key: (string) ($item['key'] ?? ''),
                title: (string) ($item['title'] ?? ''),
                type: (string) ($item['type'] ?? ''),
                ratingKey: (string) ($item['ratingKey'] ?? ''),
                guid: (string) ($item['guid'] ?? ''),
                year: isset($item['year']) ? (int) $item['year'] : null,
                originalTitle: isset($item['originalTitle']) ? (string) $item['originalTitle'] : null,
                slug: isset($item['slug']) ? (string) $item['slug'] : null,
            );
        }

        return collect($items);
    }

    public function getCollections(int $libraryId): Collection
    {
        $json = $this->requestJson('GET', "/library/sections/{$libraryId}/collections");

        $directories = $json['MediaContainer']['Directory'] ?? [];
        if (!is_array($directories)) {
            $directories = [];
        }

        $items = [];
        foreach ($directories as $dir) {
            $items[] = new LibraryItemDTO(
                key: (string) ($dir['key'] ?? ''),
                title: (string) ($dir['title'] ?? ''),
                type: (string) ($dir['type'] ?? ''),
                ratingKey: (string) ($dir['ratingKey'] ?? ''),
                guid: (string) ($dir['guid'] ?? ''),
            );
        }

        return collect($items);
    }

    /**
     * Extract external IDs from Plex GUID.
     * Plex GUIDs typically look like:
     * - com.plexapp.agents.thetvdb://12345?lang=en
     * - com.plexapp.agents.themoviedb://12345?lang=en
     * - com.plexapp.agents.imdb://tt1234567
     *
     * @return array{imdb_id?: string, tvdb_id?: int, tmdb_id?: int}
     */
    public function extractExternalIdsFromGuid(?string $guid): array
    {
        if (empty($guid)) {
            return [];
        }

        $ids = [];

        // Extract IMDB ID
        if (preg_match('/com\.plexapp\.agents\.imdb:\/\/(tt\d+)/i', $guid, $matches)) {
            $ids['imdb_id'] = $matches[1];
        }

        // Extract TVDB ID
        if (preg_match('/com\.plexapp\.agents\.thetvdb:\/\/(\d+)/i', $guid, $matches)) {
            $ids['tvdb_id'] = (int) $matches[1];
        }

        // Extract TMDB ID
        if (preg_match('/com\.plexapp\.agents\.themoviedb:\/\/(\d+)/i', $guid, $matches)) {
            $ids['tmdb_id'] = (int) $matches[1];
        }

        return $ids;
    }

    /**
     * Match a Plex show to a local Show model using multiple strategies.
     */
    public function matchPlexShowToLocalShow(
        string $plexTitle,
        ?string $plexGuid = null,
        ?int $plexYear = null,
        ?string $plexOriginalTitle = null,
        ?string $plexSlug = null
    ): ?Show {
        $externalIds = $this->extractExternalIdsFromGuid($plexGuid);

        // Strategy 1: Match by external IDs (most reliable)
        if (!empty($externalIds)) {
            $query = Show::query();

            if (isset($externalIds['imdb_id'])) {
                $query->orWhere('imdb_id', $externalIds['imdb_id']);
            }
            if (isset($externalIds['tvdb_id'])) {
                $query->orWhere('tvdb_id', $externalIds['tvdb_id']);
            }
            if (isset($externalIds['tmdb_id'])) {
                $query->orWhere('tmdb_id', $externalIds['tmdb_id']);
            }

            $match = $query->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 2: Match by exact name and year
        if ($plexYear) {
            $match = Show::query()
                ->where('name', $plexTitle)
                ->where('release_year', $plexYear)
                ->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 3: Match by exact name
        $match = Show::query()
            ->where('name', $plexTitle)
            ->first();
        if ($match) {
            return $match;
        }

        // Strategy 4: Match by slug (use provided slug or generate from title)
        $slug = $plexSlug ?? \Illuminate\Support\Str::slug($plexTitle);
        $match = Show::query()
            ->where('slug', $slug)
            ->first();
        if ($match) {
            return $match;
        }

        // Strategy 5: Match by original title if provided
        if ($plexOriginalTitle) {
            $match = Show::query()
                ->where('name', $plexOriginalTitle)
                ->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 6: Match by aliases
        $match = Show::query()
            ->whereJsonContains('aliases', $plexTitle)
            ->first();
        if ($match) {
            return $match;
        }

        // Also try matching originalTitle in aliases if provided
        if ($plexOriginalTitle) {
            $match = Show::query()
                ->whereJsonContains('aliases', $plexOriginalTitle)
                ->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 7: Fuzzy name matching (case-insensitive, normalized)
        $normalizedTitle = $this->normalizeTitle($plexTitle);
        $match = Show::query()
            ->get()
            ->first(function (Show $show) use ($normalizedTitle) {
                $showTitle = $this->normalizeTitle($show->name);
                return $showTitle === $normalizedTitle;
            });

        return $match;
    }

    /**
     * Match a Plex movie to a local Movie model using multiple strategies.
     */
    public function matchPlexMovieToLocalMovie(
        string $plexTitle,
        ?string $plexGuid = null,
        ?int $plexYear = null,
        ?string $plexOriginalTitle = null,
        ?string $plexSlug = null
    ): ?Movie {
        $externalIds = $this->extractExternalIdsFromGuid($plexGuid);

        // Strategy 1: Match by external IDs (most reliable)
        if (!empty($externalIds)) {
            $query = Movie::query();

            if (isset($externalIds['imdb_id'])) {
                $query->orWhere('imdb_id', $externalIds['imdb_id']);
            }
            if (isset($externalIds['tmdb_id'])) {
                $query->orWhere('tmdb_id', $externalIds['tmdb_id']);
            }

            $match = $query->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 2: Match by exact name and year
        if ($plexYear) {
            $match = Movie::query()
                ->where('name', $plexTitle)
                ->where('release_year', $plexYear)
                ->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 3: Match by exact name
        $match = Movie::query()
            ->where('name', $plexTitle)
            ->first();
        if ($match) {
            return $match;
        }

        // Strategy 4: Match by slug (use provided slug or generate from title)
        $slug = $plexSlug ?? \Illuminate\Support\Str::slug($plexTitle);
        $match = Movie::query()
            ->where('slug', $slug)
            ->first();
        if ($match) {
            return $match;
        }

        // Strategy 5: Match by original title if provided
        if ($plexOriginalTitle) {
            $match = Movie::query()
                ->where('name', $plexOriginalTitle)
                ->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 6: Match by aliases
        $match = Movie::query()
            ->whereJsonContains('aliases', $plexTitle)
            ->first();
        if ($match) {
            return $match;
        }

        // Also try matching originalTitle in aliases if provided
        if ($plexOriginalTitle) {
            $match = Movie::query()
                ->whereJsonContains('aliases', $plexOriginalTitle)
                ->first();
            if ($match) {
                return $match;
            }
        }

        // Strategy 7: Fuzzy name matching (case-insensitive, normalized)
        $normalizedTitle = $this->normalizeTitle($plexTitle);
        $match = Movie::query()
            ->get()
            ->first(function (Movie $movie) use ($normalizedTitle) {
                $movieTitle = $this->normalizeTitle($movie->name);
                return $movieTitle === $normalizedTitle;
            });

        return $match;
    }

    /**
     * Normalize title for fuzzy matching (remove special chars, lowercase).
     */
    private function normalizeTitle(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $title));
    }

    /**
     * Get Plex server information for building web URLs.
     *
     * @return array{url: string, serverId: string}|null
     */
    public function getServerInfo(): ?array
    {
        try {
            // Try JSON first
            try {
                $json = $this->requestJson('GET', '/');
                $machineIdentifier = $json['MediaContainer']['machineIdentifier'] ?? null;
            } catch (\Exception $e) {
                // Fallback to XML if JSON fails
                $xml = $this->requestXml('GET', '/');
                $attrs = $xml->attributes();
                $machineIdentifier = $attrs ? (string) ($attrs['machineIdentifier'] ?? '') : null;
            }

            if (empty($machineIdentifier)) {
                return null;
            }

            $baseUrl = $this->getBaseUrl();
            if (empty($baseUrl)) {
                return null;
            }

            return [
                'url' => rtrim($baseUrl, '/'),
                'serverId' => (string) $machineIdentifier,
            ];
        } catch (\Exception $e) {
            // Silently fail if Plex is not configured
            return null;
        }
    }

    /**
     * Build Plex web URL for a show.
     */
    public function buildShowUrl(?string $plexId): ?string
    {
        if (empty($plexId)) {
            return null;
        }

        $serverInfo = $this->getServerInfo();
        if (!$serverInfo) {
            return null;
        }

        return sprintf(
            '%s/web/index.html#!/server/%s/details?key=%s',
            $serverInfo['url'],
            $serverInfo['serverId'],
            urlencode($plexId)
        );
    }

    public function buildMovieUrl(?string $plexId): ?string
    {
        // Movies use the same URL structure as shows
        return $this->buildShowUrl($plexId);
    }

    private function getBaseUrl(): string
    {
        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $url = $store->get('plex', 'url');

        return $url ?? (string) config('services.plex.url', '');
    }

    private function requestJson(string $method, string $path, array $query = []): array
    {
        $baseUrl = $this->getBaseUrl();
        if (empty($baseUrl)) {
            throw new InvalidResponseException(
                service: 'plex',
                method: $method,
                url: $path,
                status: null,
                responseBody: null,
                message: 'Missing Plex base URL configuration',
            );
        }

        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $token = $store->get('plex', 'token') ?? config('services.plex.token');

        $headers = [
            'Accept' => 'application/json',
            'X-Plex-Text-Format' => 'plain',
            'X-Plex-Product' => 'Plex Web',
            'X-Plex-Version' => '4.157.0',
            'X-Plex-Client-Identifier' => 'zapnwz0ublf9k9m7utu97u50',
            'X-Plex-Platform' => 'Firefox',
            'X-Plex-Platform-Version' => '146.0',
            'X-Plex-Features' => 'external-media,indirect-media,hub-style-list',
            'X-Plex-Model' => 'standalone',
            'X-Plex-Device' => 'Linux',
            'X-Plex-Device-Name' => 'Firefox',
        ];

        if ($token !== '') {
            $query['X-Plex-Token'] = $token;
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $http = \Illuminate\Support\Facades\Http::withHeaders($headers);
        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $query),
            'POST' => $http->post($url, $query),
            'PUT' => $http->put($url, $query),
            'DELETE' => $http->delete($url, $query),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            throw new InvalidResponseException(
                service: 'plex',
                method: $method,
                url: $url,
                status: $response->status(),
                responseBody: $response->body(),
                message: "HTTP {$response->status()} from Plex",
            );
        }

        $body = $response->body();
        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidResponseException(
                service: 'plex',
                method: $method,
                url: $url,
                status: $response->status(),
                responseBody: $body,
                message: 'Failed to parse Plex JSON response: ' . json_last_error_msg(),
            );
        }

        return $json;
    }

    private function requestXml(string $method, string $path, array $query = []): \SimpleXMLElement
    {
        $baseUrl = $this->getBaseUrl();
        if (empty($baseUrl)) {
            throw new InvalidResponseException(
                service: 'plex',
                method: $method,
                url: $path,
                status: null,
                responseBody: null,
                message: 'Missing Plex base URL configuration',
            );
        }

        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $token = $store->get('plex', 'token') ?? config('services.plex.token');

        if ($token !== '') {
            $query['X-Plex-Token'] = $token;
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $response = \Illuminate\Support\Facades\Http::get($url, $query);

        if (!$response->successful()) {
            throw new InvalidResponseException(
                service: 'plex',
                method: $method,
                url: $url,
                status: $response->status(),
                responseBody: $response->body(),
                message: "HTTP {$response->status()} from Plex",
            );
        }

        $body = $response->body();
        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            throw new InvalidResponseException(
                service: 'plex',
                method: $method,
                url: $url,
                status: $response->status(),
                responseBody: $body,
                message: 'Failed to parse Plex XML response',
            );
        }

        return $xml;
    }
}
