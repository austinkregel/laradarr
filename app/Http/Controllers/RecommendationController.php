<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RecommendationServiceContract;
use App\Models\Movie;
use App\Models\Show;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public const SHOW_LIMIT = 20;
    public const MOVIE_LIMIT = 20;

    public function __construct(private RecommendationServiceContract $service)
    {
    }

    public function index(Request $request): Response
    {
        // Redirect to shows recommendations by default
        return redirect()->route('recommendations.shows');
    }

    public function shows(Request $request): Response
    {
        $user = $request->user();

        $showRecommendations = $this->service->getShowRecommendations($user, self::SHOW_LIMIT);

        return Inertia::render('ShowRecommendations', [
            'showRecommendations' => $this->serializeShowRecommendations($showRecommendations),
        ]);
    }

    public function movies(Request $request): Response
    {
        $user = $request->user();

        $movieRecommendations = $this->service->getMovieRecommendations($user, self::MOVIE_LIMIT);

        return Inertia::render('MovieRecommendations', [
            'movieRecommendations' => $this->serializeMovieRecommendations($movieRecommendations),
        ]);
    }

    public function showsApi(Request $request): JsonResponse
    {
        $recommendations = $this->service->getShowRecommendations($request->user(), self::SHOW_LIMIT);

        return response()->json($this->serializeShowRecommendations($recommendations));
    }

    public function moviesApi(Request $request): JsonResponse
    {
        $recommendations = $this->service->getMovieRecommendations($request->user(), self::MOVIE_LIMIT);

        return response()->json($this->serializeMovieRecommendations($recommendations));
    }

    public function similar(Show $show): JsonResponse
    {
        $recommendations = $this->service->getSimilarShows($show, self::SHOW_LIMIT);

        return response()->json($this->serializeShowRecommendations($recommendations));
    }

    public function similarMovies(Movie $movie): JsonResponse
    {
        $recommendations = $this->service->getSimilarMovies($movie, self::MOVIE_LIMIT);

        return response()->json($this->serializeMovieRecommendations($recommendations));
    }

    private function serializeShowRecommendations(Collection $recommendations): array
    {
        return $recommendations
            ->map(fn ($entry) => [
                'show' => optional($entry['show'])->only([
                    'id',
                    'name',
                    'poster_image',
                    'genres',
                    'type',
                    'release_year',
                ]),
                'score' => (float) ($entry['score'] ?? 0.0),
            ])
            ->all();
    }

    private function serializeMovieRecommendations(Collection $recommendations): array
    {
        return $recommendations
            ->map(fn ($entry) => [
                'movie' => optional($entry['movie'])->only([
                    'id',
                    'name',
                    'poster_image',
                    'genres',
                    'release_year',
                    'runtime',
                ]),
                'score' => (float) ($entry['score'] ?? 0.0),
            ])
            ->all();
    }
}

