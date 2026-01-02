<?php
declare(strict_types=1);

namespace App\Services\DTOs\Radarr;

use Carbon\CarbonImmutable;

readonly class MovieDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $titleSlug,
        public int $year,
        public ?string $overview,
        public int $runtime,
        public ?int $movieFileId,
        public bool $hasFile,
        public array $alternateTitles,
        public array $images,
        public ?CarbonImmutable $inCinemas,
        public ?CarbonImmutable $physicalRelease,
        public ?string $imdbId,
        public ?int $tmdbId,
        public ?CarbonImmutable $added,
        public ?string $path,
        public ?array $statistics,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            title: (string) ($data['title'] ?? ''),
            titleSlug: (string) ($data['titleSlug'] ?? ''),
            year: (int) ($data['year'] ?? 0),
            overview: isset($data['overview']) ? (string) $data['overview'] : null,
            runtime: (int) ($data['runtime'] ?? 0),
            movieFileId: isset($data['movieFileId']) ? (int) $data['movieFileId'] : null,
            hasFile: (bool) ($data['hasFile'] ?? ($data['downloaded'] ?? false)),
            alternateTitles: is_array($data['alternateTitles'] ?? null) ? $data['alternateTitles'] : [],
            images: is_array($data['images'] ?? null) ? $data['images'] : [],
            inCinemas: isset($data['inCinemas']) ? CarbonImmutable::parse((string) $data['inCinemas']) : null,
            physicalRelease: isset($data['physicalRelease']) ? CarbonImmutable::parse((string) $data['physicalRelease']) : null,
            imdbId: isset($data['imdbId']) ? (string) $data['imdbId'] : null,
            tmdbId: isset($data['tmdbId']) ? (int) $data['tmdbId'] : null,
            added: isset($data['added']) ? CarbonImmutable::parse((string) $data['added']) : null,
            path: isset($data['path']) ? (string) $data['path'] : null,
            statistics: is_array($data['statistics'] ?? null) ? $data['statistics'] : null,
        );
    }
}




