<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentWarning;
use App\Models\Media;
use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MovieController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $movies = QueryBuilder::for(Movie::class)
            ->where(function ($query) {
                $q = request('q');
                if ($q) {
                    $query->where('name', 'like', '%' . $q . '%');
                }
            })
            ->withCount([
                'watchers' => fn ($query) => $query->where('user_id', $user->id),
                'completed' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->with([
                'categories:id,name,slug,color,icon',
                'contentWarnings:id,name,slug,icon',
                'watchers' => fn ($query) => $query->where('user_id', $user->id)->select('users.id')->withPivot('watched_at'),
            ])
            ->whereHas('media')
            ->whereNotNull('poster_image')
            ->where('is_available', true)
            ->when(!request()->has('filter[liked-only]'), function ($query) use ($user) {
                // Exclude liked content by default
                $query->whereNotExists(function ($subquery) use ($user) {
                    $subquery->select(\DB::raw(1))
                        ->from('favorites')
                        ->whereColumn('favorites.favoriteable_id', 'movies.id')
                        ->where('favorites.favoriteable_type', Movie::class)
                        ->where('favorites.user_id', $user->id);
                });
            })
            ->when(request()->has('filter[liked-only]'), function ($query) use ($user) {
                // Show only liked content when filter is active
                $query->whereExists(function ($subquery) use ($user) {
                    $subquery->select(\DB::raw(1))
                        ->from('favorites')
                        ->whereColumn('favorites.favoriteable_id', 'movies.id')
                        ->where('favorites.favoriteable_type', Movie::class)
                        ->where('favorites.user_id', $user->id);
                });
            })
            ->allowedFilters([
                AllowedFilter::scope('unwatched-only'),
                AllowedFilter::scope('with-watched-progress'),
                AllowedFilter::scope('completed-only'),
                AllowedFilter::callback('dub_language', function ($query, $value) {
                    if (!$value) {
                        return;
                    }
                    $query->whereHas('media', fn ($q) => $q->whereJsonContains('custom_properties->languages', $value));
                }),
                AllowedFilter::callback('category_ids', function ($query, $value) {
                    $ids = is_array($value) ? $value : [$value];
                    $ids = array_values(array_filter(array_map('intval', $ids)));
                    if (empty($ids)) {
                        return;
                    }
                    $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
                }),
                AllowedFilter::callback('content_warning_ids', function ($query, $value) {
                    $ids = is_array($value) ? $value : [$value];
                    $ids = array_values(array_filter(array_map('intval', $ids)));
                    if (empty($ids)) {
                        return;
                    }
                    $query->whereHas('contentWarnings', fn ($q) => $q->whereIn('content_warnings.id', $ids));
                }),
                AllowedFilter::callback('min_rating', function ($query, $value) {
                    $rating = is_numeric($value) ? (float) $value : null;
                    if ($rating === null) {
                        return;
                    }
                    $source = (string) request('rating_source', 'imdb');
                    $query->minRating($rating, $source);
                }),
            ])
            ->defaultSort('-created_at')
            ->paginate(request('limit', 30))
            ->appends(request()->query());

        $languages = Movie::query()
            ->whereNotNull('available_dub_languages')
            ->pluck('available_dub_languages')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Fallback if metadata hasn't been backfilled yet.
        if ($languages->isEmpty()) {
            $languages = Media::query()
                ->where('model_type', Movie::class)
                ->select(['custom_properties'])
                ->limit(2000)
                ->get()
                ->flatMap(fn ($m) => (array) data_get($m, 'custom_properties.languages', []))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        }

        return Inertia::render('Movies', [
            'movies' => $movies,
            'filters' => [
                'categories' => Category::query()
                    ->select(['id', 'name', 'slug', 'type', 'color', 'icon'])
                    ->orderBy('type')
                    ->orderBy('name')
                    ->get(),
                'content_warnings' => ContentWarning::query()
                    ->select(['id', 'name', 'slug', 'icon'])
                    ->orderBy('name')
                    ->get(),
                'dub_languages' => $languages,
                'rating_sources' => ['imdb', 'tmdb', 'trakt', 'community'],
            ],
        ]);
    }

    public function show(Movie $movie): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $movie->load([
            'watchers' => fn ($query) => $query->where('user_id', $user->id),
            'categories:id,name,slug,color,icon',
            'contentWarnings:id,name,slug,icon',
        ]);
        
        // Load media using Spatie's relationship
        $movie->load('media');

        return Inertia::render('Movie', [
            'movie' => $movie,
        ]);
    }
}

