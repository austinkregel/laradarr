<?php
declare(strict_types=1);

namespace App\Contracts;

interface TmdbServiceContract
{
    public function getTvShowDetails(int $tmdbId): array;

    public function getTvShowExternalIds(int $tmdbId): array;

    public function getMovieDetails(int $tmdbId): array;

    public function getMovieExternalIds(int $tmdbId): array;
}

