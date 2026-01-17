<?php

use App\Models\Show;
use App\Models\Movie;
use App\Models\Category;
use App\Models\ContentWarning;
use App\Models\Media;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\ManualImportController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\RadarrController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SonarrController;
use App\Http\Controllers\TraktAuthController;
use App\Services\RecommendationService;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/credentials', [CredentialController::class, 'index'])->name('credentials.index');
    Route::post('/credentials', [CredentialController::class, 'store'])->name('credentials.store');
    Route::put('/credentials/{credential}', [CredentialController::class, 'update'])->name('credentials.update');
    Route::delete('/credentials/{credential}', [CredentialController::class, 'destroy'])->name('credentials.destroy');

    Route::get('/manual-imports', [ManualImportController::class, 'index'])->name('manual-imports.index');
    Route::post('/manual-imports/{flag}/resolve', [ManualImportController::class, 'markResolved'])->name('manual-imports.resolve');
    Route::post('/manual-imports/{flag}/trigger-sonarr-import', [ManualImportController::class, 'triggerSonarrImport'])->name('manual-imports.trigger-sonarr-import');

    // Trakt OAuth authentication routes
    Route::get('/trakt/status', [TraktAuthController::class, 'status'])->name('trakt.status');
    Route::post('/trakt/device', [TraktAuthController::class, 'startDevice'])->name('trakt.device');
    Route::post('/trakt/poll', [TraktAuthController::class, 'poll'])->name('trakt.poll');
    Route::delete('/trakt/disconnect', [TraktAuthController::class, 'disconnect'])->name('trakt.disconnect');

    Route::get('/dashboard', function () {
        // Redirect to shows dashboard by default
        return redirect()->route('dashboard.shows');
    })->name('dashboard');

    Route::get('/dashboard/shows', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $shows = QueryBuilder::for(Show::class)
            ->where('name', 'like', '%' . request('q') . '%')
            ->withCount([
                'seasons',
                'episodes',
                'episodes as episodes_with_media_count' => fn ($query) => $query->whereHas('media'),
                'watchers' => fn ($query) => $query->where('user_id', $user->id),
                'completed' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->with([
                'categories:id,name,slug,color,icon',
                'contentWarnings:id,name,slug,icon',
            ])
            ->whereHas('episodes.media')
            ->whereNotNull('poster_image')
            ->allowedFilters([
                AllowedFilter::scope('complete-collection'),
                AllowedFilter::scope('all-episodes-with-media'),
                AllowedFilter::scope('incomplete-with-some-media'),
                AllowedFilter::scope('english-only'),
                AllowedFilter::scope('unwatched-only'),
                AllowedFilter::scope('with-watched-progress'),
                AllowedFilter::scope('completed-only'),

                // New filters
                AllowedFilter::exact('type'),
                AllowedFilter::scope('has-multiple-seasons'),
                AllowedFilter::callback('dub_language', function ($query, $value) {
                    if (!$value) {
                        return;
                    }
                    // If the user is also filtering for "complete episodes (media)", require that
                    // *every* episode has a media file that includes this language.
                    if (request()->has('filter.all-episodes-with-media')) {
                        $query
                            ->whereHas('episodes')
                            ->whereDoesntHave('episodes', fn ($e) => $e->whereDoesntHave(
                                'media',
                                fn ($m) => $m->whereJsonContains('custom_properties->languages', $value)
                            ));
                        return;
                    }

                    // Default behavior: at least one episode has a media file with this language.
                    $query->whereHas('episodes.media', fn ($q) => $q->whereJsonContains('custom_properties->languages', $value));
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
            ->orderByDesc('last_watched_at')
            ->paginate(request('limit', 15))
        ->appends(request()->query())
        ;

        $types = Show::query()
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->sort()
            ->values();

        $languages = Show::query()
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
                ->select(['custom_properties'])
                ->limit(2000)
                ->get()
                ->flatMap(fn ($m) => (array) data_get($m, 'custom_properties.languages', []))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        }

        $showRecommendations = app(RecommendationService::class)
            ->getShowRecommendations($user, 6)
            ->map(fn ($entry) => [
                'show' => optional($entry['show'])->only([
                    'id',
                    'name',
                    'poster_image',
                    'genres',
                    'type',
                    'release_year',
                ]),
                'score' => (float) ($entry['score'] ?? 0),
            ])
            ->values();

        return Inertia::render('ShowsDashboard', [
            'shows' => $shows,
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
                'types' => $types,
                'rating_sources' => ['imdb', 'tmdb', 'trakt', 'community'],
            ],
            'recently_watched' => $user->watchedEpisodes()
                ->with(['watchers', 'show'])
                ->orderByDesc('watched_at')
                ->limit(10)
                ->get(),
            'show_recommendations' => $showRecommendations,
        ]);
    })->name('dashboard.shows');

    Route::get('/dashboard/movies', function () {
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
            ->paginate(request('limit', 15))
            ->appends(request()->query());

        $movieLanguages = Movie::query()
            ->whereNotNull('available_dub_languages')
            ->pluck('available_dub_languages')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($movieLanguages->isEmpty()) {
            $movieLanguages = Media::query()
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

        $movieRecommendations = app(RecommendationService::class)
            ->getMovieRecommendations($user, 6)
            ->map(fn ($entry) => [
                'movie' => optional($entry['movie'])->only([
                    'id',
                    'name',
                    'poster_image',
                    'genres',
                    'release_year',
                ]),
                'score' => (float) ($entry['score'] ?? 0),
            ])
            ->values();

        return Inertia::render('MoviesDashboard', [
            'movies' => $movies,
            'movie_filters' => [
                'categories' => Category::query()
                    ->select(['id', 'name', 'slug', 'type', 'color', 'icon'])
                    ->orderBy('type')
                    ->orderBy('name')
                    ->get(),
                'content_warnings' => ContentWarning::query()
                    ->select(['id', 'name', 'slug', 'icon'])
                    ->orderBy('name')
                    ->get(),
                'dub_languages' => $movieLanguages,
                'rating_sources' => ['imdb', 'tmdb', 'trakt', 'community'],
            ],
            'recently_watched_movies' => $user->watchedMovies()
                ->with(['movie'])
                ->orderByDesc('watched_at')
                ->limit(10)
                ->get(),
            'movie_recommendations' => $movieRecommendations,
        ]);
    })->name('dashboard.movies');

    Route::get('/recommendations', [RecommendationController::class, 'index'])
        ->name('recommendations.index');
    Route::get('/recommendations/shows', [RecommendationController::class, 'shows'])
        ->name('recommendations.shows');
    Route::get('/recommendations/movies', [RecommendationController::class, 'movies'])
        ->name('recommendations.movies');

    Route::get('/discover', [DiscoveryController::class, 'index'])->name('discover');
    Route::get('/discover/shows', [DiscoveryController::class, 'shows'])->name('discover.shows');
    Route::get('/discover/movies', [DiscoveryController::class, 'movies'])->name('discover.movies');

    Route::get('/browse/shows', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $shows = QueryBuilder::for(Show::class)
            ->where('name', 'like', '%' . request('q') . '%')
            ->withCount([
                'episodes',
                'episodes as episodes_with_media_count' => fn ($query) => $query->whereHas('media'),
                'watchers' => fn ($query) => $query->where('user_id', $user->id),
                'completed' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->with([
                'categories:id,name,slug,color,icon',
                'contentWarnings:id,name,slug,icon',
            ])
            ->whereHas('episodes.media')
            ->whereNotNull('poster_image')
            ->when(!request()->has('filter[liked-only]'), function ($query) use ($user) {
                // Exclude liked content by default
                $query->whereNotExists(function ($subquery) use ($user) {
                    $subquery->select(\DB::raw(1))
                        ->from('favorites')
                        ->whereColumn('favorites.favoriteable_id', 'shows.id')
                        ->where('favorites.favoriteable_type', Show::class)
                        ->where('favorites.user_id', $user->id);
                });
            })
            ->when(request()->has('filter[liked-only]'), function ($query) use ($user) {
                // Show only liked content when filter is active
                $query->whereExists(function ($subquery) use ($user) {
                    $subquery->select(\DB::raw(1))
                        ->from('favorites')
                        ->whereColumn('favorites.favoriteable_id', 'shows.id')
                        ->where('favorites.favoriteable_type', Show::class)
                        ->where('favorites.user_id', $user->id);
                });
            })
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::scope('complete-collection'),
                AllowedFilter::scope('all-episodes-with-media'),
                AllowedFilter::scope('incomplete-with-some-media'),
                AllowedFilter::scope('english-only'),
                AllowedFilter::scope('unwatched-only'),
                AllowedFilter::scope('with-watched-progress'),
                AllowedFilter::scope('completed-only'),
                AllowedFilter::scope('has-multiple-seasons'),
                AllowedFilter::callback('dub_language', function ($query, $value) {
                    if (!$value) {
                        return;
                    }
                    // If the user is also filtering for "complete episodes (media)", require that
                    // *every* episode has a media file that includes this language.
                    if (request()->has('filter.all-episodes-with-media')) {
                        $query
                            ->whereHas('episodes')
                            ->whereDoesntHave('episodes', fn ($e) => $e->whereDoesntHave(
                                'media',
                                fn ($m) => $m->whereJsonContains('custom_properties->languages', $value)
                            ));
                        return;
                    }

                    // Default behavior: at least one episode has a media file with this language.
                    $query->whereHas('episodes.media', fn ($q) => $q->whereJsonContains('custom_properties->languages', $value));
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

        $types = Show::query()
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->sort()
            ->values();

        $languages = Show::query()
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
                ->select(['custom_properties'])
                ->limit(2000)
                ->get()
                ->flatMap(fn ($m) => (array) data_get($m, 'custom_properties.languages', []))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        }

        return Inertia::render('BrowseShows', [
            'shows' => $shows,
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
                'types' => $types,
                'rating_sources' => ['imdb', 'tmdb', 'trakt', 'community'],
            ],
        ]);
    })->name('browse.shows');

    Route::get('/watched-shows', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Inertia::render('WatchHistory', [
            'shows' => $user->watchedEpisodes()
                ->with([
                    'season.show',
                    'show',
                    'watchers',
                ])
                ->orderByDesc('watched_at')
                ->paginate(30, ['*'], 'page', request('page', 1)),
        ]);
    })->name('watched-shows.index');

    Route::get('/watched-movies', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Inertia::render('WatchHistoryMovies', [
            'movies' => $user->watchedMovies()
                ->with(['movie', 'watchers'])
                ->orderByDesc('watched_at')
                ->paginate(30, ['*'], 'page', request('page', 1)),
        ]);
    })->name('watched-movies.index');

    Route::post('/favorite', function (Request $request) {
        $type = $request->get('likeable_type');
        $id = $request->get('likeable_id');
        $redirect = $request->get('redirect', '/');
        
        if (!$type || !$id) {
            return redirect($redirect)->with('error', 'Missing likeable_type or likeable_id');
        }
        
        // Normalize the class name (handle escaped backslashes)
        $type = str_replace('\\\\', '\\', $type);
        
        // Validate class exists using Composer's autoloader
        if (!class_exists($type)) {
            return redirect($redirect)->with('error', "Class {$type} not found");
        }
        
        // Ensure it's a valid Eloquent model
        if (!is_subclass_of($type, \Illuminate\Database\Eloquent\Model::class)) {
            return redirect($redirect)->with('error', "Class {$type} is not a valid Eloquent model");
        }
        
        $like = $type::find($id);
        
        if (!$like) {
            return redirect($redirect)->with('error', 'Resource not found');
        }
        
        if ($request->user()->hasFavorited($like)) {
            $request->user()->unfavorite($like);
            return redirect($redirect)->with('success', 'Unliked successfully');
        }

        
        $request->user()->favorite($like);

        return redirect($redirect)->with('success', 'Liked successfully');
    });

    Route::post('/sonarr/release/push', [SonarrController::class, 'pushRelease'])
        ->name('sonarr.release.push');
    Route::post('/sonarr/episodes/search', [SonarrController::class, 'searchEpisodes'])
        ->name('sonarr.episodes.search');
    Route::post('/sonarr/seasons/{season}/search', [SonarrController::class, 'searchSeason'])
        ->name('sonarr.seasons.search');
    Route::post('/sonarr/seasons/{season}/release/push', [SonarrController::class, 'pushSeasonRelease'])
        ->name('sonarr.seasons.release.push');
    Route::get('/sonarr/commands/{commandId}', [SonarrController::class, 'getCommandStatus'])
        ->name('sonarr.commands.status');
    Route::get('/sonarr/episodes/{episodeId}/search-status', [SonarrController::class, 'getEpisodeSearchStatus'])
        ->name('sonarr.episodes.search-status');

    Route::post('/radarr/release/push', [RadarrController::class, 'pushRelease'])
        ->name('radarr.release.push');
    Route::post('/radarr/movies/{movie}/search', [RadarrController::class, 'searchMovie'])
        ->name('radarr.movies.search');
    Route::get('/radarr/commands/{commandId}', [RadarrController::class, 'getCommandStatus'])
        ->name('radarr.commands.status');

    Route::get('/browse/movies', [MovieController::class, 'index'])->name('browse.movies');
    Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movie');

    Route::get('/shows/{show}', function (\App\Models\Show $show) {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Inertia::render('Show', [
            'show' => $show->load([
                'seasons.show:id,sonarr_id',
                'seasons.episodes.media' => fn ($query) => $query->with('tags'),
                'seasons.episodes' => function ($query) {
                },
                'seasons.episodes.watchers' => fn ($query) => $query->where('user_id', $user->id),
                'categories:id,name,slug,color,icon',
                'contentWarnings:id,name,slug,icon',
            ]),
        ]);
    })->name('show');
});
