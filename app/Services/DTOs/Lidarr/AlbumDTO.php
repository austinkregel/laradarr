<?php
declare(strict_types=1);

namespace App\Services\DTOs\Lidarr;

readonly class AlbumDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public int $artistId,
        public ?string $releaseDate,
        public ?string $path,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            title: (string) ($data['title'] ?? ''),
            artistId: (int) ($data['artistId'] ?? 0),
            releaseDate: isset($data['releaseDate']) ? (string) $data['releaseDate'] : null,
            path: isset($data['path']) ? (string) $data['path'] : null,
        );
    }
}








