<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('sync:sonarr', function () {
    dispatch_sync(new \App\Jobs\SyncSonarrShowsJob());
});

Artisan::command('sync:trakt', function () {
    dispatch_sync(new \App\Jobs\SyncTraktTvShowsAndProgressJob(\App\Models\User::first()));
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('report', function () {
    $query = \Spatie\MediaLibrary\MediaCollections\Models\Media::query();

    $query->whereJsonDoesntContain('custom_properties->languages', 'English');

    $showsWithoutEnglish = $query->get();
    $script = [
        '#!/bin/bash',
        'set -e',
    ];
    /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $show */
    foreach ($showsWithoutEnglish as $show) {
        if (!in_array('Japanese', $show->custom_properties['languages'])) {
            // We don't want to remove shows that the system thinks are in other languages... It's often wrong.
            continue;
        }


        $script[] = "rm -rfv ".escapeshellarg(str_replace('/data/', '/volume1/', $show->custom_properties['path']));
    }

    $script[] = "echo 'All done!'";

    $scriptPath = storage_path('app/report.sh');

    file_put_contents($scriptPath, implode("\n", $script));

});
