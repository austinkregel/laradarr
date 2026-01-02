<?php
declare(strict_types=1);

namespace App\Services\DTOs\Lidarr;

readonly class ArtistDTO
{
    public function __construct(
        public int $id,
        public string $artistName,
        public ?string $path,
        public array $genres,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            artistName: (string) ($data['artistName'] ?? $data['name'] ?? ''),
            path: isset($data['path']) ? (string) $data['path'] : null,
            genres: is_array($data['genres'] ?? null) ? $data['genres'] : [],
        );
    }
}






