<?php
declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

interface TraktTvServiceContract
{
    public function createDeviceToken(): array;

    public function exchangeForAccessToken(string $code): array;

    public function refreshAccessToken(): array;

    /** @return Collection<int, \App\Services\DTOs\Trakt\ShowDTO> */
    public function findWatchedShows(): Collection;

    public function findShowsOnList(string $user, string $list): array;

    public function fetchUserLists(string $user): array;

    public function createList(string $name, array $shows): array;

    public function addShowsToList(string $user, string $list, array $showIds): array;
}



