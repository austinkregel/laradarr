<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\SonarrServiceContract;
use App\Services\DTOs\Sonarr\EpisodeDTO;
use App\Services\DTOs\Sonarr\EpisodeFileDTO;
use App\Services\DTOs\Sonarr\ShowDTO;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SonarrService extends BaseApiClient implements SonarrServiceContract
{
    protected function serviceKey(): string
    {
        return 'sonarr';
    }

    protected function defaultQuery(): array
    {
        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $apiKey = $store->get('sonarr', 'api_key') ?? config('services.sonarr.api_key');
        
        return [
            'apikey' => (string) $apiKey,
        ];
    }

    /** @return Collection<int, ShowDTO> */
    public function getShows(): Collection
    {
        $json = $this->requestJson('GET', '/api/v3/series');
        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['id'], $row['title'], $row['titleSlug']))
            ->map(fn (array $row) => ShowDTO::fromArray($row))
            ->values();
    }

    public function getShow(int $showId): ShowDTO
    {
        $json = cache()->remember(
            'sonarr-show-'.$showId,
            now()->addHour(),
            fn () => $this->requestJson('GET', "/api/v3/series/{$showId}")
        );

        $this->ensureKeys($json, ['id', 'title', 'titleSlug'], 'sonarr.getShow');
        return ShowDTO::fromArray($json);
    }

    private function ensureMagnetOrDownload(?string $magnetUrl, ?string $downloadUrl): void
    {
        if ($magnetUrl === null && $downloadUrl === null) {
            throw new InvalidArgumentException('Either magnetUrl or downloadUrl must be provided');
        }
    }

    /** @return Collection<int, EpisodeDTO> */
    public function getEpisodes(int $showId, bool $missingOnly = false): Collection
    {
        $json = $this->requestJson('GET', '/api/v3/episode', query: ['seriesId' => $showId]);

        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['id'], $row['seriesId']))
            ->map(fn (array $row) => EpisodeDTO::fromArray($row))
            ->values()
            ->when($missingOnly, fn ($collection) => $collection->filter(fn (EpisodeDTO $episode) => !$episode->hasFile)->values());
    }

    /** @return Collection<int, EpisodeDTO> */
    public function getMissingEpisodes(int $showId): Collection
    {
        return $this->getEpisodes($showId, missingOnly: true);
    }

    public function getEpisodeFile(int $episodeFileId, int $showId): EpisodeFileDTO
    {
        $json = $this->requestJson(
            'GET',
            "/api/v3/episodefile/{$episodeFileId}",
            query: ['seriesId' => $showId],
        );

        return EpisodeFileDTO::fromArray($json);
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

    public function searchEpisodes(array $episodeIds): array
    {
        $ids = array_values(array_filter($episodeIds, fn ($id) => $id !== null));
        if (empty($ids)) {
            throw new InvalidArgumentException('episodeIds cannot be empty');
        }

        $body = [
            'name' => 'EpisodeSearch',
            'episodeIds' => array_map('intval', $ids),
        ];

        return $this->requestJson('POST', '/api/v3/command', body: $body);
    }

    public function getCommandStatus(int $commandId): array
    {
        return $this->requestJson('GET', "/api/v3/command/{$commandId}");
    }

    /**
     * Get the download queue from Sonarr.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueue(): array
    {
        return $this->requestJson('GET', '/api/v3/queue');
    }

    /**
     * Get history from Sonarr, optionally filtered by episode ID.
     *
     * @param int|null $episodeId Filter by specific episode ID
     * @param int $page Page number (default 1)
     * @param int $pageSize Number of results per page (default 20)
     * @return array<string, mixed>
     */
    public function getHistory(?int $episodeId = null, int $page = 1, int $pageSize = 20): array
    {
        $query = [
            'page' => $page,
            'pageSize' => $pageSize,
        ];

        if ($episodeId !== null) {
            $query['episodeId'] = $episodeId;
        }

        return $this->requestJson('GET', '/api/v3/history', query: $query);
    }

    /** @return Collection<int, ShowDTO> */
    public function searchSeries(string $term): Collection
    {
        $json = $this->requestJson('GET', '/api/v3/series', query: ['term' => $term]);

        return collect($json)
            ->filter(fn ($row) => is_array($row) && isset($row['id'], $row['title'], $row['titleSlug']))
            ->map(fn (array $row) => ShowDTO::fromArray($row))
            ->values();
    }

    /**
     * Get manual import candidates by scanning a folder path.
     *
     * @param string $folder Path to scan for importable files
     * @param bool $filterExistingFiles Filter out files that already exist
     * @return array<int, array<string, mixed>>
     */
    public function getManualImportCandidates(string $folder, bool $filterExistingFiles = true): array
    {
        $query = [
            'folder' => $folder,
            'filterExistingFiles' => $filterExistingFiles,
        ];

        return $this->requestJson('GET', '/api/v3/manualimport', query: $query);
    }

    /**
     * Submit manual import to Sonarr.
     *
     * @param array<int, array<string, mixed>> $importItems Array of import items (from getManualImportCandidates)
     * @return array<string, mixed>
     */
    public function submitManualImport(array $importItems): array
    {
        if (empty($importItems)) {
            throw new InvalidArgumentException('importItems cannot be empty');
        }

        return $this->requestJson('POST', '/api/v3/manualimport', body: $importItems);
    }
}
