<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Movie;
use App\Models\Show;
use App\Services\DTOs\Plex\LibraryItemDTO;
use Illuminate\Support\Collection;

interface PlexServiceContract
{
    public function getLibraries(): Collection;

    public function getLibraryItems(int $libraryId, array $query = []): Collection;

    public function getCollections(int $libraryId): Collection;

    /** @return array{imdb_id?: string, tvdb_id?: int, tmdb_id?: int} */
    public function extractExternalIdsFromGuid(?string $guid): array;

    public function matchPlexShowToLocalShow(
        string $plexTitle,
        ?string $plexGuid = null,
        ?int $plexYear = null,
        ?string $plexOriginalTitle = null,
        ?string $plexSlug = null
    ): ?Show;

    public function matchPlexMovieToLocalMovie(
        string $plexTitle,
        ?string $plexGuid = null,
        ?int $plexYear = null,
        ?string $plexOriginalTitle = null,
        ?string $plexSlug = null
    ): ?Movie;

    /** @return array{url: string, serverId: string}|null */
    public function getServerInfo(): ?array;

    public function buildShowUrl(?string $plexId): ?string;

    public function buildMovieUrl(?string $plexId): ?string;
}

