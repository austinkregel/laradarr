<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Services\DTOs\Radarr\MovieDTO;
use App\Services\DTOs\Radarr\MovieFileDTO;
use Illuminate\Support\Collection;

interface RadarrServiceContract
{
    /** @return Collection<int, MovieDTO> */
    public function getMovies(): Collection;

    public function getMovie(int $movieId): MovieDTO;

    public function getMovieFile(int $movieFileId): MovieFileDTO;

    public function pushRelease(
        string $title,
        ?string $magnetUrl = null,
        ?string $downloadUrl = null,
        ?string $publishDate = null,
        array $extra = [],
    ): array;

    public function searchMovie(array $movieIds): array;

    public function getCommandStatus(int $commandId): array;

    /** @return array<int, array<string, mixed>> */
    public function getQueue(): array;

    /** @return array<string, mixed> */
    public function getHistory(?int $movieId = null, int $page = 1, int $pageSize = 20): array;
}

