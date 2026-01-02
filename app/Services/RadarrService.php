<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\RadarrServiceContract;
use App\Services\DTOs\Radarr\MovieDTO;
use App\Services\DTOs\Radarr\MovieFileDTO;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RadarrService extends BaseApiClient implements RadarrServiceContract
{
    protected function serviceKey(): string
    {
        return 'radarr';
    }

    protected function defaultQuery(): array
    {
        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('radarr', 'api_key') ?? config('services.radarr.api_key');
        
        return [
            'apikey' => (string) $apiKey,
        ];
    }

    /** @return Collection<int, MovieDTO> */
    public function getMovies(): Collection
    {
        $json = $this->requestJson('GET', '/api/v3/movie');

        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['id'], $row['title']))
            ->map(fn (array $row) => MovieDTO::fromArray($row))
            ->values();
    }

    public function getMovie(int $movieId): MovieDTO
    {
        $json = $this->requestJson('GET', "/api/v3/movie/{$movieId}");
        $this->ensureKeys($json, ['id', 'title'], 'radarr.getMovie');
        return MovieDTO::fromArray($json);
    }

    public function getMovieFile(int $movieFileId): MovieFileDTO
    {
        $json = $this->requestJson('GET', "/api/v3/moviefile/{$movieFileId}");
        return MovieFileDTO::fromArray($json);
    }

    private function ensureMagnetOrDownload(?string $magnetUrl, ?string $downloadUrl): void
    {
        if ($magnetUrl === null && $downloadUrl === null) {
            throw new InvalidArgumentException('Either magnetUrl or downloadUrl must be provided');
        }
    }

    public function pushRelease(
        string $title,
        ?string $magnetUrl = null,
        ?string $downloadUrl = null,
        ?string $publishDate = null,
        array $extra = [],
    ): array {
        $this->ensureMagnetOrDownload($magnetUrl, $downloadUrl);

        $body = [
            'title' => $title,
            'protocol' => 'torrent',
        ];

        if ($magnetUrl !== null) {
            $body['magnetUrl'] = $magnetUrl;
        }

        if ($downloadUrl !== null) {
            $body['downloadUrl'] = $downloadUrl;
        }

        if ($publishDate !== null) {
            $body['publishDate'] = $publishDate;
        }

        $body = array_merge($body, $extra);

        return $this->requestJson('POST', '/api/v3/release/push', body: $body);
    }

    public function searchMovie(array $movieIds): array
    {
        $ids = array_values(array_filter($movieIds, fn ($id) => $id !== null));
        if (empty($ids)) {
            throw new InvalidArgumentException('movieIds cannot be empty');
        }

        $body = [
            'name' => 'MoviesSearch',
            'movieIds' => array_map('intval', $ids),
        ];

        return $this->requestJson('POST', '/api/v3/command', body: $body);
    }

    public function getCommandStatus(int $commandId): array
    {
        return $this->requestJson('GET', "/api/v3/command/{$commandId}");
    }

    /**
     * Get the download queue from Radarr.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueue(): array
    {
        return $this->requestJson('GET', '/api/v3/queue');
    }

    /**
     * Get history from Radarr, optionally filtered by movie ID.
     *
     * @param int|null $movieId Filter by specific movie ID
     * @param int $page Page number (default 1)
     * @param int $pageSize Number of results per page (default 20)
     * @return array<string, mixed>
     */
    public function getHistory(?int $movieId = null, int $page = 1, int $pageSize = 20): array
    {
        $query = [
            'page' => $page,
            'pageSize' => $pageSize,
        ];

        if ($movieId !== null) {
            $query['movieId'] = $movieId;
        }

        return $this->requestJson('GET', '/api/v3/history', query: $query);
    }
}




