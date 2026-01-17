<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Services\DTOs\Sonarr\EpisodeDTO;
use App\Services\DTOs\Sonarr\EpisodeFileDTO;
use App\Services\DTOs\Sonarr\ShowDTO;
use Illuminate\Support\Collection;

interface SonarrServiceContract
{
    /** @return Collection<int, ShowDTO> */
    public function getShows(): Collection;

    public function getShow(int $showId): ShowDTO;

    /** @return Collection<int, EpisodeDTO> */
    public function getEpisodes(int $showId, bool $missingOnly = false): Collection;

    /** @return Collection<int, EpisodeDTO> */
    public function getMissingEpisodes(int $showId): Collection;

    public function getEpisodeFile(int $episodeFileId, int $showId): EpisodeFileDTO;

    public function pushRelease(
        string $title,
        ?string $magnetUrl = null,
        ?string $downloadUrl = null,
        ?string $publishDate = null,
        array $extra = [],
    ): array;

    public function searchEpisodes(array $episodeIds): array;

    public function getCommandStatus(int $commandId): array;

    /** @return array<int, array<string, mixed>> */
    public function getQueue(): array;

    /** @return array<string, mixed> */
    public function getHistory(?int $episodeId = null, int $page = 1, int $pageSize = 20): array;

    /** @return Collection<int, ShowDTO> */
    public function searchSeries(string $term): Collection;

    /** @return array<int, array<string, mixed>> */
    public function getManualImportCandidates(string $folder, bool $filterExistingFiles = true): array;

    /** @return array<string, mixed> */
    public function submitManualImport(array $importItems): array;
}





