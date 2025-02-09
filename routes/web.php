<?php

use App\Models\Show;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    Route::get('/dashboard', function () {
        $shows = \Spatie\QueryBuilder\QueryBuilder::for(Show::class)
            ->where('name', 'like', '%' . request('q') . '%')
            ->withCount([
                'seasons',
                'episodes',
                'watchers' => fn ($query) => $query->where('user_id', auth()->id()),
            ])
            ->whereNotNull('poster_image')
            ->allowedFilters([
                \Spatie\QueryBuilder\AllowedFilter::scope('complete'),
                \Spatie\QueryBuilder\AllowedFilter::scope('english-only'),
                \Spatie\QueryBuilder\AllowedFilter::scope('unwatched-only'),
                \Spatie\QueryBuilder\AllowedFilter::scope('with-watched-progress'),
            ])
            ->orderByDesc('last_watched_at')
            ->paginate(request('limit', 5000));

        return Inertia::render('Dashboard', [
            'shows' => $shows,
            'recently_watched' => auth()->user()->watchedEpisodes()
                ->with(['watchers', 'show'])
                ->orderByDesc('watched_at')
                ->limit(10)
                ->get(),
        ]);
    })->name('dashboard');

    Route::post('/favorite', function (Request $request) {
        $type = $request->get('likeable_type');
        $like = $type::find($request->get('likeable_id'));
        if ($request->user()->hasFavorited($like)) {
            $request->user()->unfavorite($like);
            return Inertia::location(request()->get('redirect'));
        }

        $like = $request->user()->favorite($like);

        return Inertia::location(request()->get('redirect'));
    });

    Route::get('/shows/{show}', function (\App\Models\Show $show) {
        return Inertia::render('Show', [
            'show' => $show->load([
                'seasons.episodes.media' => function ($query) {

                },
                'seasons.episodes' => function ($query) {
                },
                'seasons.episodes.watchers' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ]),
        ]);
    })->name('show');
});
