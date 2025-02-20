<?php

use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sync:trakt')->everyThirtyMinutes();
Schedule::command('sync:sonarr')->everyThirtyMinutes();

//Artisan::command('fetch:all-anime', function () {
//    /** @var \App\Services\TraktTvService $service */
//    $service = app(\App\Services\TraktTvService::class);
//
//    $lists = $service->fetchUserLists('sp1ti');
//    $allLists = [];
//
//    foreach ($lists as $list) {
//        if (str_contains($list['name'], 'Anime') && (
//                str_contains($list['name'], 'Fall') ||
//                str_contains($list['name'], 'Winter') ||
//                str_contains($list['name'], 'Spring') ||
//                str_contains($list['name'], 'Summer') ||
//                str_contains($list['name'], 'fall') ||
//                str_contains($list['name'], 'winter') ||
//                str_contains($list['name'], 'spring') ||
//                str_contains($list['name'], 'summer')
//
//            )) {
//            $allLists[] = $list;
//        }
//    }
//
//    $anime = [];
//
//
//
//    foreach ($allLists as $list) {
//        $content = $service->findShowsOnList('sp1ti', $list['ids']['slug']);
//
//        foreach ($content as $show) {
//            $anime[$show['show']['ids']['trakt']] = [
//                'id' => $show['show']['ids']['trakt'],
//                'name' => $show['show']['title'],
//            ];
//        }
//    }
//
//    $service->createList('All Anime', []);
//
//    dd($service->addShowsToList('austinkregel', 'all-anime', array_keys($anime)));
//    dd($service->findShowsOnList('sp1ti', 'anime-winter-2020-2021'));
//});

Artisan::command('detect:full-series', function () {
    $shows = \App\Models\Show::query()
        ->where('size_on_disk', '>', 0)
        ->with('seasons.episodes.media')
        ->get();

    foreach ($shows as $show) {
        $seasons = $show->seasons;
        $hasCompleteSeason = [];
        $epsiodeCount = 0;
        foreach ($seasons as $season) {
            $episodes = $season->episodes;

            $epsiodeCount += $episodes->count();
            foreach ($episodes as $episode) {
                $episode->update([
                    'has_file' => $episode->media()->count() > 0,
                ]);
            }
        }
        if ($epsiodeCount === 0) {
            continue;
        }

        $show->update([
            'has_complete_series' => $epsiodeCount === $show->episodes()->where('has_file', true)->count(),
        ]);
    }
});

Artisan::command('sync:sonarr', function () {
    dispatch_sync(new \App\Jobs\SyncSonarrShowsJob());
});

Artisan::command('sync:trakt', function () {
    dispatch_sync(new \App\Jobs\SyncTraktTvShowsAndProgressJob(\App\Models\User::first()));
});
