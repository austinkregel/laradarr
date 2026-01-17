<?php
declare(strict_types=1);

namespace App\Services\DTOs\Radarr;

readonly class MovieFileDTO
{
    public function __construct(
        public int $id,
        public ?string $path,
        public int $size,
        public string $relativePath,
        public ?string $quality,
        /** @var array<int, array{name?:string}> */
        public array $languages,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            path: isset($data['path']) ? (string) $data['path'] : null,
            size: (int) ($data['size'] ?? 0),
            relativePath: (string) ($data['relativePath'] ?? ''),
            quality: isset($data['quality']['quality']['name']) ? (string) $data['quality']['quality']['name'] : null,
            languages: is_array($data['languages'] ?? null) ? $data['languages'] : [],
        );
    }
}








