<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Collection;

interface RecommendationServiceContract
{
    public function getShowRecommendations(User $user, int $limit = 20): Collection;

    public function getMovieRecommendations(User $user, int $limit = 20): Collection;

    public function getSimilarShows(Show $show, int $limit = 10): Collection;

    public function getSimilarMovies(Movie $movie, int $limit = 10): Collection;
}

