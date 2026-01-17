<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\DiscoveryRecommendationServiceContract;
use App\Models\DiscoverableMovie;
use App\Models\DiscoverableShow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DiscoveryController extends Controller
{
    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        // Default to shows discovery.
        return redirect()->route('discover.shows', $request->query());
    }

    public function shows(Request $request, DiscoveryRecommendationServiceContract $recs): InertiaResponse
    {
        $user = $request->user();
        $minYear = now()->subYears(30)->year;

        $q = trim((string) $request->get('q', ''));
        $sort = (string) $request->get('sort', 'recommendation');
        $genre = $request->filled('genre') ? (string) $request->get('genre') : null; // stored as slug
        $format = $request->filled('format') ? (string) $request->get('format') : null; // animated | live_action
        $perPage = max(10, min(60, (int) $request->get('per_page', 30)));

        $filterBaseQuery = DiscoverableShow::query()
            ->notInLibrary()
            ->where(function ($query) use ($minYear) {
                $query->whereNull('release_year')->orWhere('release_year', '>=', $minYear);
            });

        $genreOptions = $filterBaseQuery
            ->orderByDesc('popularity')
            ->limit(2000)
            ->get(['genres'])
            ->flatMap(fn (DiscoverableShow $s) => (array) ($s->genres ?? []))
            ->filter(fn ($g) => is_string($g) && $g !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $baseQuery = (clone $filterBaseQuery)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($genre !== null, fn ($query) => $query->whereJsonContains('genres', $genre))
            ->when($format === 'animated', fn ($query) => $query->where('is_animated', true))
            ->when($format === 'live_action', fn ($query) => $query->where('is_animated', false));

        if ($sort === 'year') {
            $shows = $baseQuery
                ->orderByDesc('release_year')
                ->orderByDesc('first_air_date')
                ->orderByDesc('popularity')
                ->paginate($perPage)
                ->appends($request->query());

            return Inertia::render('Discover', [
                'activeTab' => 'shows',
                'shows' => $shows,
                'movies' => null,
                'sort' => $sort,
                'q' => $q,
                'genre' => $genre,
                'format' => $format,
                'filters' => [
                    'genres' => $genreOptions,
                    'formats' => [
                        ['value' => null, 'label' => 'All'],
                        ['value' => 'live_action', 'label' => 'Live action'],
                        ['value' => 'animated', 'label' => 'Animated'],
                    ],
                ],
            ]);
        }

        if ($sort === 'rating') {
            $shows = $baseQuery
                ->orderByDesc('vote_average')
                ->orderByDesc('vote_count')
                ->orderByDesc('popularity')
                ->paginate($perPage)
                ->appends($request->query());

            return Inertia::render('Discover', [
                'activeTab' => 'shows',
                'shows' => $shows,
                'movies' => null,
                'sort' => $sort,
                'q' => $q,
                'genre' => $genre,
                'format' => $format,
                'filters' => [
                    'genres' => $genreOptions,
                    'formats' => [
                        ['value' => null, 'label' => 'All'],
                        ['value' => 'live_action', 'label' => 'Live action'],
                        ['value' => 'animated', 'label' => 'Animated'],
                    ],
                ],
            ]);
        }

        // Default: recommendation sorting (computed in memory over a popularity-sorted candidate set).
        $candidateLimit = 1000;
        $candidates = $baseQuery
            ->orderByDesc('popularity')
            ->limit($candidateLimit)
            ->get();

        $ranked = $recs->rankDiscoverableShows($user, $candidates, $candidateLimit)->values();

        $page = max(1, (int) $request->get('page', 1));
        $slice = $ranked->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $ranked->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Discover', [
            'activeTab' => 'shows',
            'shows' => $paginator,
            'movies' => null,
            'sort' => 'recommendation',
            'q' => $q,
            'genre' => $genre,
            'format' => $format,
            'filters' => [
                'genres' => $genreOptions,
                'formats' => [
                    ['value' => null, 'label' => 'All'],
                    ['value' => 'live_action', 'label' => 'Live action'],
                    ['value' => 'animated', 'label' => 'Animated'],
                ],
            ],
        ]);
    }

    public function movies(Request $request, DiscoveryRecommendationServiceContract $recs): InertiaResponse
    {
        $user = $request->user();
        $minYear = now()->subYears(30)->year;

        $q = trim((string) $request->get('q', ''));
        $sort = (string) $request->get('sort', 'recommendation');
        $genre = $request->filled('genre') ? (string) $request->get('genre') : null; // stored as slug
        $format = $request->filled('format') ? (string) $request->get('format') : null; // animated | live_action
        $perPage = max(10, min(60, (int) $request->get('per_page', 30)));

        $filterBaseQuery = DiscoverableMovie::query()
            ->notInLibrary()
            ->where(function ($query) use ($minYear) {
                $query->whereNull('release_year')->orWhere('release_year', '>=', $minYear);
            });

        $genreOptions = $filterBaseQuery
            ->orderByDesc('popularity')
            ->limit(2000)
            ->get(['genres'])
            ->flatMap(fn (DiscoverableMovie $m) => (array) ($m->genres ?? []))
            ->filter(fn ($g) => is_string($g) && $g !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $baseQuery = (clone $filterBaseQuery)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($genre !== null, fn ($query) => $query->whereJsonContains('genres', $genre))
            ->when($format === 'animated', fn ($query) => $query->where('is_animated', true))
            ->when($format === 'live_action', fn ($query) => $query->where('is_animated', false));

        if ($sort === 'year') {
            $movies = $baseQuery
                ->orderByDesc('release_year')
                ->orderByDesc('release_date')
                ->orderByDesc('popularity')
                ->paginate($perPage)
                ->appends($request->query());

            return Inertia::render('Discover', [
                'activeTab' => 'movies',
                'shows' => null,
                'movies' => $movies,
                'sort' => $sort,
                'q' => $q,
                'genre' => $genre,
                'format' => $format,
                'filters' => [
                    'genres' => $genreOptions,
                    'formats' => [
                        ['value' => null, 'label' => 'All'],
                        ['value' => 'live_action', 'label' => 'Live action'],
                        ['value' => 'animated', 'label' => 'Animated'],
                    ],
                ],
            ]);
        }

        if ($sort === 'rating') {
            $movies = $baseQuery
                ->orderByDesc('vote_average')
                ->orderByDesc('vote_count')
                ->orderByDesc('popularity')
                ->paginate($perPage)
                ->appends($request->query());

            return Inertia::render('Discover', [
                'activeTab' => 'movies',
                'shows' => null,
                'movies' => $movies,
                'sort' => $sort,
                'q' => $q,
                'genre' => $genre,
                'format' => $format,
                'filters' => [
                    'genres' => $genreOptions,
                    'formats' => [
                        ['value' => null, 'label' => 'All'],
                        ['value' => 'live_action', 'label' => 'Live action'],
                        ['value' => 'animated', 'label' => 'Animated'],
                    ],
                ],
            ]);
        }

        $candidateLimit = 1000;
        $candidates = $baseQuery
            ->orderByDesc('popularity')
            ->limit($candidateLimit)
            ->get();

        $ranked = $recs->rankDiscoverableMovies($user, $candidates, $candidateLimit)->values();

        $page = max(1, (int) $request->get('page', 1));
        $slice = $ranked->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $ranked->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Discover', [
            'activeTab' => 'movies',
            'shows' => null,
            'movies' => $paginator,
            'sort' => 'recommendation',
            'q' => $q,
            'genre' => $genre,
            'format' => $format,
            'filters' => [
                'genres' => $genreOptions,
                'formats' => [
                    ['value' => null, 'label' => 'All'],
                    ['value' => 'live_action', 'label' => 'Live action'],
                    ['value' => 'animated', 'label' => 'Animated'],
                ],
            ],
        ]);
    }
}

