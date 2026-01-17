<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

interface TraktTvServiceContract
{
    /**
     * Create a user-scoped instance of the service.
     */
    public function forUser(int $userId): static;

    /**
     * Get the current user ID this service is scoped to (null = global).
     */
    public function getUserId(): ?int;

    public function createDeviceToken(): array;

    public function exchangeForAccessToken(string $code): array;

    public function refreshAccessToken(): array;

    /** @return Collection<int, \App\Services\DTOs\Trakt\ShowDTO> */
    public function findWatchedShows(): Collection;

    public function getTrendingShows(int $limit = 100): array;

    public function getPopularShows(int $limit = 100): array;

    public function getTrendingMovies(int $limit = 100): array;

    public function getPopularMovies(int $limit = 100): array;

    public function findShowsOnList(string $user, string $list): array;

    public function fetchUserLists(string $user): array;

    public function createList(string $name, array $shows): array;

    public function addShowsToList(string $user, string $list, array $showIds): array;
}





