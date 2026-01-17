<?php
declare(strict_types=1);

namespace App\Contracts;

interface TmdbServiceContract
{
    public function getTvShowDetails(int $tmdbId): array;

    public function getTvShowExternalIds(int $tmdbId): array;

    public function getMovieDetails(int $tmdbId): array;

    public function getMovieExternalIds(int $tmdbId): array;

    public function discoverTvShows(array $params = []): array;

    public function discoverMovies(array $params = []): array;

    public function getTrendingTvShows(string $timeWindow = 'week', array $params = []): array;

    public function getTrendingMovies(string $timeWindow = 'week', array $params = []): array;

    public function getTvGenres(array $params = []): array;

    public function getMovieGenres(array $params = []): array;
}

