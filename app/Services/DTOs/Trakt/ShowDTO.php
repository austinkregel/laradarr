<?php
declare(strict_types=1);

namespace App\Services\DTOs\Trakt;

readonly class ShowDTO
{
    /**
     * @param array{id?:int,slug?:string,imdb?:string,tvdb?:int,tmdb?:int} $ids
     * @param array<int, EpisodeDTO> $episodes
     */
    public function __construct(
        public int $id,
        public array $ids,
        public string $name,
        public int $releaseYear,
        public array $episodes,
    ) {}

    public static function fromWatchedArray(array $showFromTrakt): self
    {
        $show = $showFromTrakt['show'] ?? [];
        $ids = is_array($show['ids'] ?? null) ? $show['ids'] : [];

        $episodes = [];
        foreach (($showFromTrakt['seasons'] ?? []) as $season) {
            if (!is_array($season)) {
                continue;
            }
            $seasonNumber = (int) ($season['number'] ?? 0);
            foreach (($season['episodes'] ?? []) as $ep) {
                if (!is_array($ep)) {
                    continue;
                }
                $episodes[] = EpisodeDTO::fromWatchedArray($seasonNumber, $ep);
            }
        }

        return new self(
            id: (int) ($ids['trakt'] ?? 0),
            ids: $ids,
            name: (string) ($show['title'] ?? ''),
            releaseYear: (int) ($show['year'] ?? 0),
            episodes: $episodes,
        );
    }
}








