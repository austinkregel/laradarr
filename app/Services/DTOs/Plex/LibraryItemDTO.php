<?php
declare(strict_types=1);

namespace App\Services\DTOs\Plex;

readonly class LibraryItemDTO
{
    public function __construct(
        public string $key,
        public string $title,
        public ?string $type = null,
        public ?string $ratingKey = null,
        public ?string $guid = null,
        public ?int $year = null,
        public ?string $originalTitle = null,
        public ?string $slug = null,
    ) {}
}




