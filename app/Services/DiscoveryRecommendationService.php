<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DiscoveryRecommendationServiceContract;
use App\Models\DiscoverableMovie;
use App\Models\DiscoverableShow;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\WatchedEpisode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Overtrue\LaravelFavorite\Favorite;

class DiscoveryRecommendationService implements DiscoveryRecommendationServiceContract
{
    /** @return Collection<int, array{show:DiscoverableShow,score:float}> */
    public function rankDiscoverableShows(User $user, Collection $candidates, int $limit): Collection
    {
        $profile = $this->computeUserProfile($user);

        return $candidates
            ->filter(fn ($c) => $c instanceof DiscoverableShow)
            ->map(fn (DiscoverableShow $show) => [
                'show' => $show,
                'score' => $this->computeDiscoverableShowScore($show, $profile),
            ])
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    /** @return Collection<int, array{movie:DiscoverableMovie,score:float}> */
    public function rankDiscoverableMovies(User $user, Collection $candidates, int $limit): Collection
    {
        $profile = $this->computeMovieProfile($user);

        return $candidates
            ->filter(fn ($c) => $c instanceof DiscoverableMovie)
            ->map(fn (DiscoverableMovie $movie) => [
                'movie' => $movie,
                'score' => $this->computeDiscoverableMovieScore($movie, $profile),
            ])
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    protected function computeUserProfile(User $user): array
    {
        $profile = $this->initializeProfile();

        $completedShows = $user->completedShows()
            ->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
            ->get();
        $this->applyShowsToProfile($profile, $completedShows, 3);

        $favoriteShows = $this->getFavoritedShows($user);
        $this->applyShowsToProfile($profile, $favoriteShows, 2);

        $watchedShowIds = WatchedEpisode::query()
            ->where('user_id', $user->id)
            ->with('season')
            ->get()
            ->map(fn (WatchedEpisode $episode) => $episode->season?->show_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($watchedShowIds)) {
            $watchedShows = Show::with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
                ->whereIn('id', $watchedShowIds)
                ->get();
            $this->applyShowsToProfile($profile, $watchedShows, 1);
        }

        $profile['hasInteractions'] = $profile['hasInteractions'] ?? false;
        $profile['rating'] = $this->calculateAverage($profile['rating']);
        $profile['year'] = $this->calculateAverage($profile['year']);

        return $profile;
    }

    protected function computeMovieProfile(User $user): array
    {
        $profile = $this->initializeProfile();

        $completedMovies = $user->completedMovies()
            ->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
            ->get();
        $this->applyMoviesToProfile($profile, $completedMovies, 3);

        $favoriteMovies = $this->getFavoritedMovies($user);
        $this->applyMoviesToProfile($profile, $favoriteMovies, 2);

        $watchedMovieIds = $user->watchedMovies()
            ->pluck('movie_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($watchedMovieIds)) {
            $watchedMovies = Movie::with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
                ->whereIn('id', $watchedMovieIds)
                ->get();
            $this->applyMoviesToProfile($profile, $watchedMovies, 1);
        }

        $profile['hasInteractions'] = $profile['hasInteractions'] ?? false;
        $profile['rating'] = $this->calculateAverage($profile['rating']);
        $profile['year'] = $this->calculateAverage($profile['year']);

        return $profile;
    }

    protected function initializeProfile(): array
    {
        return [
            'genres' => [],
            'rating' => ['total' => 0.0, 'count' => 0, 'average' => null],
            'year' => ['total' => 0.0, 'count' => 0, 'average' => null],
            'hasInteractions' => false,
        ];
    }

    protected function applyShowsToProfile(array &$profile, Collection $shows, int $weight): void
    {
        foreach ($shows as $show) {
            if (!$show instanceof Show) {
                continue;
            }

            $profile['hasInteractions'] = true;

            foreach ($this->normalizeGenresArray($show->genres) as $genre) {
                $profile['genres'][$genre] = ($profile['genres'][$genre] ?? 0) + $weight;
            }

            $rating = $this->getNormalizedShowRating($show);
            if ($rating !== null) {
                $profile['rating']['total'] += $rating * $weight;
                $profile['rating']['count'] += $weight;
            }

            $year = $show->release_year;
            if ($year) {
                $profile['year']['total'] += (float) $year * $weight;
                $profile['year']['count'] += $weight;
            }
        }
    }

    protected function applyMoviesToProfile(array &$profile, Collection $movies, int $weight): void
    {
        foreach ($movies as $movie) {
            if (!$movie instanceof Movie) {
                continue;
            }

            $profile['hasInteractions'] = true;

            foreach ($this->normalizeGenresArray($movie->genres) as $genre) {
                $profile['genres'][$genre] = ($profile['genres'][$genre] ?? 0) + $weight;
            }

            $rating = $this->getNormalizedMovieRating($movie);
            if ($rating !== null) {
                $profile['rating']['total'] += $rating * $weight;
                $profile['rating']['count'] += $weight;
            }

            $year = $movie->release_year;
            if ($year) {
                $profile['year']['total'] += (float) $year * $weight;
                $profile['year']['count'] += $weight;
            }
        }
    }

    protected function getFavoritedShows(User $user): Collection
    {
        return Favorite::query()
            ->with(['favoriteable'])
            ->withType(Show::class)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Favorite $favorite) => $favorite->favoriteable)
            ->filter(fn ($favoriteable) => $favoriteable instanceof Show)
            ->unique('id')
            ->values();
    }

    protected function getFavoritedMovies(User $user): Collection
    {
        return Favorite::query()
            ->with(['favoriteable'])
            ->withType(Movie::class)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Favorite $favorite) => $favorite->favoriteable)
            ->filter(fn ($favoriteable) => $favoriteable instanceof Movie)
            ->unique('id')
            ->values();
    }

    protected function calculateAverage(array $entry): array
    {
        $count = $entry['count'] ?? 0;
        if ($count === 0) {
            return [
                'total' => $entry['total'] ?? 0.0,
                'count' => 0,
                'average' => null,
            ];
        }

        return [
            'total' => $entry['total'],
            'count' => $count,
            'average' => (float) $entry['total'] / $count,
        ];
    }

    protected function computeDiscoverableShowScore(DiscoverableShow $show, array $profile): float
    {
        if (!($profile['hasInteractions'] ?? false)) {
            // Fallback: popularity-weighted score when we don't know the user yet.
            return (float) ($show->popularity ?? 0.0);
        }

        $genreScore = $this->weightedOverlap($profile['genres'], $this->normalizeGenresArray($show->genres));
        $ratingScore = $this->normalizedRatingSimilarity($profile['rating'], $this->normalizeVoteAverage($show->vote_average));
        $yearScore = $this->yearProximity($profile['year'], $show->release_year);

        return ($genreScore * 0.65) + ($ratingScore * 0.25) + ($yearScore * 0.10);
    }

    protected function computeDiscoverableMovieScore(DiscoverableMovie $movie, array $profile): float
    {
        if (!($profile['hasInteractions'] ?? false)) {
            return (float) ($movie->popularity ?? 0.0);
        }

        $genreScore = $this->weightedOverlap($profile['genres'], $this->normalizeGenresArray($movie->genres));
        $ratingScore = $this->normalizedRatingSimilarity($profile['rating'], $this->normalizeVoteAverage($movie->vote_average));
        $yearScore = $this->yearProximity($profile['year'], $movie->release_year);

        return ($genreScore * 0.65) + ($ratingScore * 0.25) + ($yearScore * 0.10);
    }

    protected function normalizeGenresArray($genres): array
    {
        if (empty($genres)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($g) => $this->normalizeFeature($g),
            (array) $genres
        ))));
    }

    protected function normalizeFeature($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        return $normalized !== '' ? $normalized : null;
    }

    protected function weightedOverlap(array $profileScores, array $values): float
    {
        if (empty($profileScores) || empty($values)) {
            return 0.0;
        }

        $total = array_sum($profileScores);
        if ($total <= 0.0) {
            return 0.0;
        }

        $match = 0.0;
        foreach ($values as $value) {
            $match += $profileScores[$value] ?? 0.0;
        }

        return min(1.0, $match / $total);
    }

    protected function normalizedRatingSimilarity(array $ratingProfile, ?float $candidateNormalized): float
    {
        $profileScore = $ratingProfile['average'] ?? null;
        if ($profileScore === null || $candidateNormalized === null) {
            return 0.0;
        }

        return max(0.0, 1 - abs((float) $profileScore - $candidateNormalized));
    }

    protected function yearProximity(array $yearProfile, ?int $year): float
    {
        $average = $yearProfile['average'] ?? null;
        if ($average === null || $year === null) {
            return 0.0;
        }

        $gap = abs($average - $year);
        return max(0.0, 1 - ($gap / 30));
    }

    protected function normalizeVoteAverage($voteAverage): ?float
    {
        if ($voteAverage === null || !is_numeric($voteAverage)) {
            return null;
        }

        return max(0.0, min(1.0, ((float) $voteAverage) / 10));
    }

    protected function getNormalizedShowRating(Show $show): ?float
    {
        $candidate = $show->imdb_rating
            ?? $show->tmdb_rating
            ?? $show->trakt_rating
            ?? $show->community_rating;

        if ($candidate === null) {
            return null;
        }

        return max(0.0, min(1.0, (float) $candidate / 10));
    }

    protected function getNormalizedMovieRating(Movie $movie): ?float
    {
        $candidate = $movie->imdb_rating
            ?? $movie->tmdb_rating
            ?? $movie->trakt_rating
            ?? $movie->community_rating;

        if ($candidate === null) {
            return null;
        }

        return max(0.0, min(1.0, (float) $candidate / 10));
    }
}

