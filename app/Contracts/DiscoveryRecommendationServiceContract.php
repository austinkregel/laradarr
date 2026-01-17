<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface DiscoveryRecommendationServiceContract
{
    /** @return Collection<int, array{show:\App\Models\DiscoverableShow,score:float}> */
    public function rankDiscoverableShows(User $user, Collection $candidates, int $limit): Collection;

    /** @return Collection<int, array{movie:\App\Models\DiscoverableMovie,score:float}> */
    public function rankDiscoverableMovies(User $user, Collection $candidates, int $limit): Collection;
}

