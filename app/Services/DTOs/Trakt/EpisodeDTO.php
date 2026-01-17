<?php
declare(strict_types=1);

namespace App\Services\DTOs\Trakt;

readonly class EpisodeDTO
{
    public function __construct(
        public string $key,
        public int $season,
        public int $number,
        public string $watchedAt,
    ) {}

    public static function fromWatchedArray(int $seasonNumber, array $episode): self
    {
        $episodeNumber = (int) ($episode['number'] ?? 0);
        $key = 'S' . str_pad((string) $seasonNumber, 2, '0', STR_PAD_LEFT)
            . 'E' . str_pad((string) $episodeNumber, 2, '0', STR_PAD_LEFT);

        return new self(
            key: $key,
            season: $seasonNumber,
            number: $episodeNumber,
            watchedAt: (string) ($episode['last_watched_at'] ?? $episode['watched_at'] ?? ''),
        );
    }
}








