<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\RecommendationServiceContract;
use App\Models\Movie;
use App\Models\Show;
use App\Models\User;
use App\Models\WatchedEpisode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Overtrue\LaravelFavorite\Favorite;

class RecommendationService implements RecommendationServiceContract
{
    public function getShowRecommendations(User $user, int $limit = 20): Collection
    {
        $profile = $this->computeUserProfile($user);

        if (!$profile['hasInteractions']) {
            return $this->getFallbackShows($limit);
        }

        $results = $this->scoreShows($profile, $limit);
        if ($results->isEmpty()) {
            return $this->getFallbackShows($limit);
        }

        return $results;
    }

    public function getMovieRecommendations(User $user, int $limit = 20): Collection
    {
        $profile = $this->computeMovieProfile($user);

        if (!$profile['hasInteractions']) {
            return $this->getFallbackMovies($limit);
        }

        $results = $this->scoreMovies($profile, $limit);
        if ($results->isEmpty()) {
            return $this->getFallbackMovies($limit);
        }

        return $results;
    }

    public function getSimilarMovies(Movie $movie, int $limit = 10): Collection
    {
        $profile = $this->buildProfileFromMovie($movie);

        return $this->scoreMovies($profile, $limit, [$movie->id]);
    }

    public function getSimilarShows(Show $show, int $limit = 10): Collection
    {
        $profile = $this->buildProfileFromShow($show);

        return $this->scoreShows($profile, $limit, [$show->id]);
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

        $profile['seen_show_ids'] = array_values(array_unique($profile['seen_show_ids']));
        $profile['rating'] = $this->calculateAverage($profile['rating']);
        $profile['year'] = $this->calculateAverage($profile['year']);

        return $profile;
    }

    protected function buildProfileFromShow(Show $show): array
    {
        $profile = $this->initializeProfile();
        $show->loadMissing(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug']);
        $this->applyShowsToProfile($profile, new EloquentCollection([$show]), 3);
        $profile['seen_show_ids'] = [$show->id];
        $profile['rating'] = $this->calculateAverage($profile['rating']);
        $profile['year'] = $this->calculateAverage($profile['year']);

        return $profile;
    }

    protected function initializeProfile(): array
    {
        return [
            'genres' => [],
            'categories' => [],
            'tags' => [],
            'contentWarnings' => [],
            'rating' => ['total' => 0.0, 'count' => 0, 'average' => null],
            'year' => ['total' => 0.0, 'count' => 0, 'average' => null],
            'seen_show_ids' => [],
            'seen_movie_ids' => [],
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
            $profile['seen_show_ids'][] = $show->id;

            foreach ($this->normalizeGenres($show) as $genre) {
                $profile['genres'][$genre] = ($profile['genres'][$genre] ?? 0) + $weight;
            }

            foreach ($this->normalizeCategories($show) as $category) {
                $profile['categories'][$category] = ($profile['categories'][$category] ?? 0) + $weight;
            }

            foreach ($this->normalizeTags($show) as $tag) {
                $profile['tags'][$tag] = ($profile['tags'][$tag] ?? 0) + $weight;
            }

            foreach ($this->normalizeWarnings($show) as $warning) {
                $profile['contentWarnings'][$warning] = ($profile['contentWarnings'][$warning] ?? 0) + $weight;
            }

            $rating = $this->getNormalizedRating($show);
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

    protected function getFavoritedShows(User $user): Collection
    {
        return Favorite::query()
            ->with(['favoriteable' => fn ($query) => $query->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])])
            ->withType(Show::class)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Favorite $favorite) => $favorite->favoriteable)
            ->filter(fn ($favoriteable) => $favoriteable instanceof Show)
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

    protected function scoreShows(array $profile, int $limit, array $excludeIds = []): Collection
    {
        $excludeIds = array_filter(array_unique(array_merge($profile['seen_show_ids'] ?? [], $excludeIds)));
        $candidateQuery = $this->buildCandidateQuery($excludeIds);

        return $candidateQuery
            ->get()
            ->map(fn (Show $show) => [
                'show' => $show,
                'score' => $this->computeSimilarity($show, $profile),
            ])
            ->filter(fn (array $entry) => $entry['score'] > 0)
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    protected function buildCandidateQuery(array $excludeIds)
    {
        return Show::query()
            ->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
            ->whereNotNull('poster_image')
            ->hasDubLanguageFiles('English')
            ->when(!empty($excludeIds), fn ($query) => $query->whereNotIn('id', $excludeIds));
    }

    protected function computeSimilarity(Show $show, array $profile): float
    {
        $genres = $this->normalizeGenres($show);
        $categories = $this->normalizeCategories($show);
        $tags = $this->normalizeTags($show);
        $warnings = $this->normalizeWarnings($show);

        $genreScore = $this->weightedOverlap($profile['genres'], $genres);
        $categoryScore = $this->weightedOverlap($profile['categories'], $categories);
        $tagScore = $this->weightedOverlap($profile['tags'], $tags);
        $warningScore = $this->weightedOverlap($profile['contentWarnings'], $warnings);
        $ratingScore = $this->ratingSimilarity($profile['rating'], $show);
        $yearScore = $this->yearProximity($profile['year'], $show->release_year);

        return ($genreScore * 0.4)
            + ($categoryScore * 0.25)
            + ($tagScore * 0.15)
            + ($warningScore * 0.05)
            + ($ratingScore * 0.1)
            + ($yearScore * 0.05);
    }

    protected function weightedOverlap(array $profileScores, array $values): float
    {
        if (empty($profileScores) || empty($values)) {
            return 0.0;
        }

        $total = array_sum($profileScores);
        if ($total === 0.0) {
            return 0.0;
        }

        $match = 0.0;
        foreach ($values as $value) {
            $match += $profileScores[$value] ?? 0.0;
        }

        return min(1.0, $match / $total);
    }

    protected function ratingSimilarity(array $ratingProfile, Show $show): float
    {
        $profileScore = $ratingProfile['average'] ?? null;
        if ($profileScore === null) {
            return 0.0;
        }

        $showScore = $this->getNormalizedRating($show);
        if ($showScore === null) {
            return 0.0;
        }

        return max(0.0, 1 - abs($profileScore - $showScore));
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

    protected function getFallbackShows(int $limit): Collection
    {
        $shows = Show::query()
            ->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
            ->whereNotNull('poster_image')
            ->hasDubLanguageFiles('English')
            ->orderByDesc('trakt_rating')
            ->limit($limit)
            ->get();

        return $shows->map(fn (Show $show) => [
            'show' => $show,
            'score' => $this->getNormalizedRating($show) ?? 0.1,
        ]);
    }

    protected function normalizeGenres(Show $show): array
    {
        if (empty($show->genres)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($genre) => $this->normalizeFeature($genre), (array) $show->genres))));
    }

    protected function normalizeCategories(Show $show): array
    {
        if (!$show->relationLoaded('categories')) {
            return [];
        }

        return $show->categories
            ->map(fn ($category) => $this->normalizeFeature($category->slug ?? $category->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeTags(Show $show): array
    {
        if (!$show->relationLoaded('tags')) {
            return [];
        }

        return $show->tags
            ->map(fn ($tag) => $this->normalizeFeature($tag->slug ?? $tag->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeWarnings(Show $show): array
    {
        if (!$show->relationLoaded('contentWarnings')) {
            return [];
        }

        return $show->contentWarnings
            ->map(fn ($warning) => $this->normalizeFeature($warning->slug ?? $warning->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeFeature($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower(trim((string) $value));
    }

    protected function getNormalizedRating(Show $show): ?float
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

        $profile['seen_movie_ids'] = array_values(array_unique($profile['seen_movie_ids'] ?? []));
        $profile['rating'] = $this->calculateAverage($profile['rating']);
        $profile['year'] = $this->calculateAverage($profile['year']);

        return $profile;
    }

    protected function buildProfileFromMovie(Movie $movie): array
    {
        $profile = $this->initializeProfile();
        $movie->loadMissing(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug']);
        $this->applyMoviesToProfile($profile, new EloquentCollection([$movie]), 3);
        $profile['seen_movie_ids'] = [$movie->id];
        $profile['rating'] = $this->calculateAverage($profile['rating']);
        $profile['year'] = $this->calculateAverage($profile['year']);

        return $profile;
    }

    protected function applyMoviesToProfile(array &$profile, Collection $movies, int $weight): void
    {
        foreach ($movies as $movie) {
            if (!$movie instanceof Movie) {
                continue;
            }

            $profile['hasInteractions'] = true;
            $profile['seen_movie_ids'][] = $movie->id;

            foreach ($this->normalizeMovieGenres($movie) as $genre) {
                $profile['genres'][$genre] = ($profile['genres'][$genre] ?? 0) + $weight;
            }

            foreach ($this->normalizeMovieCategories($movie) as $category) {
                $profile['categories'][$category] = ($profile['categories'][$category] ?? 0) + $weight;
            }

            foreach ($this->normalizeMovieTags($movie) as $tag) {
                $profile['tags'][$tag] = ($profile['tags'][$tag] ?? 0) + $weight;
            }

            foreach ($this->normalizeMovieWarnings($movie) as $warning) {
                $profile['contentWarnings'][$warning] = ($profile['contentWarnings'][$warning] ?? 0) + $weight;
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

    protected function getFavoritedMovies(User $user): Collection
    {
        return Favorite::query()
            ->with(['favoriteable' => fn ($query) => $query->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])])
            ->withType(Movie::class)
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Favorite $favorite) => $favorite->favoriteable)
            ->filter(fn ($favoriteable) => $favoriteable instanceof Movie)
            ->unique('id')
            ->values();
    }

    protected function scoreMovies(array $profile, int $limit, array $excludeIds = []): Collection
    {
        $excludeIds = array_filter(array_unique(array_merge($profile['seen_movie_ids'] ?? [], $excludeIds)));
        $candidateQuery = $this->buildMovieCandidateQuery($excludeIds);

        return $candidateQuery
            ->get()
            ->map(fn (Movie $movie) => [
                'movie' => $movie,
                'score' => $this->computeMovieSimilarity($movie, $profile),
            ])
            ->filter(fn (array $entry) => $entry['score'] > 0)
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    protected function buildMovieCandidateQuery(array $excludeIds)
    {
        return Movie::query()
            ->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
            ->whereNotNull('poster_image')
            ->where('is_available', true)
            ->when(!empty($excludeIds), fn ($query) => $query->whereNotIn('id', $excludeIds));
    }

    protected function computeMovieSimilarity(Movie $movie, array $profile): float
    {
        $genres = $this->normalizeMovieGenres($movie);
        $categories = $this->normalizeMovieCategories($movie);
        $tags = $this->normalizeMovieTags($movie);
        $warnings = $this->normalizeMovieWarnings($movie);

        $genreScore = $this->weightedOverlap($profile['genres'], $genres);
        $categoryScore = $this->weightedOverlap($profile['categories'], $categories);
        $tagScore = $this->weightedOverlap($profile['tags'], $tags);
        $warningScore = $this->weightedOverlap($profile['contentWarnings'], $warnings);
        $ratingScore = $this->movieRatingSimilarity($profile['rating'], $movie);
        $yearScore = $this->yearProximity($profile['year'], $movie->release_year);

        return ($genreScore * 0.4)
            + ($categoryScore * 0.25)
            + ($tagScore * 0.15)
            + ($warningScore * 0.05)
            + ($ratingScore * 0.1)
            + ($yearScore * 0.05);
    }

    protected function movieRatingSimilarity(array $ratingProfile, Movie $movie): float
    {
        $profileScore = $ratingProfile['average'] ?? null;
        if ($profileScore === null) {
            return 0.0;
        }

        $movieScore = $this->getNormalizedMovieRating($movie);
        if ($movieScore === null) {
            return 0.0;
        }

        return max(0.0, 1 - abs($profileScore - $movieScore));
    }

    protected function getFallbackMovies(int $limit): Collection
    {
        $movies = Movie::query()
            ->with(['categories:id,slug,name', 'tags', 'contentWarnings:id,slug'])
            ->whereNotNull('poster_image')
            ->where('is_available', true)
            ->orderByDesc('trakt_rating')
            ->limit($limit)
            ->get();

        return $movies->map(fn (Movie $movie) => [
            'movie' => $movie,
            'score' => $this->getNormalizedMovieRating($movie) ?? 0.1,
        ]);
    }

    protected function normalizeMovieGenres(Movie $movie): array
    {
        if (empty($movie->genres)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($genre) => $this->normalizeFeature($genre), (array) $movie->genres))));
    }

    protected function normalizeMovieCategories(Movie $movie): array
    {
        if (!$movie->relationLoaded('categories')) {
            return [];
        }

        return $movie->categories
            ->map(fn ($category) => $this->normalizeFeature($category->slug ?? $category->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeMovieTags(Movie $movie): array
    {
        if (!$movie->relationLoaded('tags')) {
            return [];
        }

        return $movie->tags
            ->map(fn ($tag) => $this->normalizeFeature($tag->slug ?? $tag->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeMovieWarnings(Movie $movie): array
    {
        if (!$movie->relationLoaded('contentWarnings')) {
            return [];
        }

        return $movie->contentWarnings
            ->map(fn ($warning) => $this->normalizeFeature($warning->slug ?? $warning->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
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

