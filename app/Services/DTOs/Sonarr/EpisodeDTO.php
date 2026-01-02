<?php
declare(strict_types=1);

namespace App\Services\DTOs\Sonarr;

readonly class EpisodeDTO
{
    public function __construct(
        public int $id,
        public int $seriesId,
        public int $seasonNumber,
        public int $episodeNumber,
        public string $title,
        public ?string $overview,
        public bool $hasFile,
        public int $runtime,
        public ?int $tvdbId,
        public ?int $episodeFileId,
        public ?string $airDateUtc,
        public ?string $airDate,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            seriesId: (int) $data['seriesId'],
            seasonNumber: (int) ($data['seasonNumber'] ?? 1),
            episodeNumber: (int) ($data['episodeNumber'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            overview: isset($data['overview']) ? (string) $data['overview'] : null,
            hasFile: (bool) ($data['hasFile'] ?? false),
            runtime: (int) ($data['runtime'] ?? 0),
            tvdbId: isset($data['tvdbId']) ? (int) $data['tvdbId'] : null,
            episodeFileId: isset($data['episodeFileId']) ? (int) $data['episodeFileId'] : null,
            airDateUtc: isset($data['airDateUtc']) ? (string) $data['airDateUtc'] : null,
            airDate: isset($data['airDate']) ? (string) $data['airDate'] : null,
        );
    }
}






