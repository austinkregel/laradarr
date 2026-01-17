<?php
declare(strict_types=1);

namespace App\Services\DTOs\Sonarr;

use Carbon\CarbonImmutable;

readonly class ShowDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $titleSlug,
        public int $year,
        public ?string $overview,
        public string $seriesType,
        public string $path,
        public array $alternateTitles,
        public array $images,
        public array $seasons,
        public ?string $imdbId,
        public ?int $tvdbId,
        public ?int $tmdbId,
        public ?CarbonImmutable $firstAired,
        public ?array $statistics,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            title: (string) $data['title'],
            titleSlug: (string) $data['titleSlug'],
            year: (int) ($data['year'] ?? 0),
            overview: isset($data['overview']) ? (string) $data['overview'] : null,
            seriesType: (string) ($data['seriesType'] ?? 'standard'),
            path: (string) ($data['path'] ?? ''),
            alternateTitles: is_array($data['alternateTitles'] ?? null) ? $data['alternateTitles'] : [],
            images: is_array($data['images'] ?? null) ? $data['images'] : [],
            seasons: is_array($data['seasons'] ?? null) ? $data['seasons'] : [],
            imdbId: isset($data['imdbId']) ? (string) $data['imdbId'] : null,
            tvdbId: isset($data['tvdbId']) ? (int) $data['tvdbId'] : null,
            tmdbId: isset($data['tmdbId']) ? (int) $data['tmdbId'] : null,
            firstAired: isset($data['firstAired']) ? CarbonImmutable::parse((string) $data['firstAired']) : null,
            statistics: is_array($data['statistics'] ?? null) ? $data['statistics'] : null,
        );
    }
}








