<?php

use App\Services\MetadataSyncService;
use App\Services\SonarrService;
use App\Services\TmdbService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Sync Trakt watch history for all users with Trakt connected
Schedule::call(function () {
    $users = \App\Models\User::whereHas('credentials', function ($q) {
        $q->where('service', 'trakt')
          ->where('key', 'access_token')
          ->where('is_enabled', true);
    })->get();

    foreach ($users as $user) {
        dispatch(new \App\Jobs\SyncTraktTvShowsAndProgressJob($user));
    }
})->everyThirtyMinutes()->name('sync:trakt:all-users');
Schedule::command('sync:sonarr')->everyThirtyMinutes()->withoutOverlapping();

// Horizon housekeeping (required for trimming completed/pending job history + metrics).
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('horizon:purge')->hourly();

// Prune old episode search requests (keep completed for 30 days, pending/active for 7 days)
Schedule::command('episode-search-requests:prune')->daily();

// Monitor qBittorrent downloads and nudge stalled ones
Schedule::job(new \App\Jobs\MonitorQBittorrentDownloadsJob)->everyFiveMinutes();

// Discovery cache (external shows/movies not in library)
Schedule::job(new \App\Jobs\SyncDiscoverableShowsJob)->daily()->withoutOverlapping();
Schedule::job(new \App\Jobs\SyncDiscoverableMoviesJob)->daily()->withoutOverlapping();

Artisan::command('monitor:qbittorrent', function () {
    dispatch(new \App\Jobs\MonitorQBittorrentDownloadsJob());
});
Artisan::command('recommendations:generate', function () {
    dispatch(new \App\Jobs\ComputeRecommendationsJob());
});

Artisan::command('scan:for-files', function () {
    $file = \App\Models\File::first();
    dispatch_sync(new \App\Jobs\DetectFileMetadataJob(
        $file->path,
        $file->disk,
    ));

    dd('done');
    dispatch(new \App\Jobs\ScanDirectoryJob(
        'Shows',
        'library',
    ));
});

Artisan::command('trakt:refresh_token', function () {
    $service = app(\App\Services\TraktTvService::class);

    $device = $service->refreshAccessToken();

    dd($device);
});

Artisan::command('trakt:device', function () {
    /** @var \App\Services\TraktTvService $service */
    $service = app(\App\Services\TraktTvService::class);

    $device = $service->createDeviceToken();

    $deviceCode = $device['device_code'] ?? null;
    $userCode = $device['user_code'] ?? null;
    $verificationUrl = $device['verification_url'] ?? null;
    $expiresIn = (int) ($device['expires_in'] ?? 600);
    $interval = (int) ($device['interval'] ?? 5);

    if (!$deviceCode || !$userCode || !$verificationUrl) {
        $this->error('Unexpected device response: '.json_encode($device));
        return;
    }

    cache()->put('integration:trakt:device_code', $deviceCode, now()->addSeconds($expiresIn));
    cache()->put('integration:trakt:device_interval', $interval, now()->addSeconds($expiresIn));

    $this->info('Trakt device authorization started.');
    $this->line('1) Visit: '.$verificationUrl);
    $this->line('2) Enter code: '.$userCode);
    $this->line('3) Then run: sail artisan trakt:authorize');
    $this->line('Device code (cached): '.$deviceCode);
    $this->line('Expires in: '.$expiresIn.'s (poll interval '.$interval.'s)');
});

Artisan::command('trakt:authorize {--device_code=} {--no-poll}', function () {
    $baseUrl = rtrim((string) config('services.trakt.base_url', 'https://api.trakt.tv'), '/');
    $clientId = (string) config('services.trakt.client_id');
    $clientSecret = (string) config('services.trakt.client_secret');

    $deviceCode = (string) ($this->option('device_code') ?: cache()->get('integration:trakt:device_code', ''));
    $interval = (int) (cache()->get('integration:trakt:device_interval', 5));

    if ($deviceCode === '') {
        $this->error('Missing device_code. Run `sail artisan trakt:device` first, or pass --device_code=');
        return;
    }

    $poll = !$this->option('no-poll');
    $this->info('Exchanging Trakt device_code for tokens'.($poll ? ' (polling)…' : '…'));

    $attempt = 0;
    while (true) {
        $attempt++;

        $response = \Illuminate\Support\Facades\Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->post('/oauth/device/token', [
                'code' => $deviceCode,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if ($response->successful()) {
            $json = $response->json();
            if (!is_array($json)) {
                $this->error('Unexpected token response: '.$response->body());
                return;
            }

            /** @var \App\Services\Auth\TokenManager $tm */
            $tm = app(\App\Services\Auth\TokenManager::class);
            $tm->storeTraktTokens($json); // persists into DB credentials + cache

            $this->info('Trakt tokens stored into database credentials (trakt.access_token / trakt.refresh_token).');
            return;
        }

        $body = $response->json();
        $error = is_array($body) ? ($body['error'] ?? null) : null;

        if (!$poll) {
            $this->error('Authorization not complete. Response: '.$response->status().' '.$response->body());
            return;
        }

        if ($error === 'authorization_pending') {
            $this->line("[$attempt] Pending… waiting {$interval}s");
            sleep(max(1, $interval));
            continue;
        }

        if ($error === 'slow_down') {
            $interval += 2;
            $this->warn("[$attempt] Told to slow down; new interval {$interval}s");
            sleep(max(1, $interval));
            continue;
        }

        $this->error('Failed to authorize: '.$response->status().' '.$response->body());
        return;
    }
});

Artisan::command('fetch:all-anime', function () {

    $security = env('PLEX_API_TOKEN');

    $sdk = \LukeHagar\Plex_API\PlexAPI::builder()
        ->setClientID(env('PLEX_CLIENT_ID'))
        ->setClientName('Plex API Client')
        ->setClientVersion('1.0.0')
        ->setPlatform('Laravel')
        ->setDeviceNickname('Api')
        ->setSecurity($security)
        ->setIp(env('PLEX_HOST_IP'))
        ->setPort(32400)
        ->setProtocol(\LukeHagar\Plex_API\ServerProtocol::Http)
        ->build();

    $createRequest = new \LukeHagar\Plex_API\Models\Operations\CreatePlaylistRequest(
        'Anime Collection',
        \LukeHagar\Plex_API\Models\Operations\CreatePlaylistQueryParamType::Video,
        \LukeHagar\Plex_API\Models\Operations\Smart::Zero,
        'anime',
    );

    $response = $sdk->playlists->createPlaylist($createRequest);

    dd($response);

});

Artisan::command('detect:full-series', function () {
    $shows = \App\Models\Show::query()
        ->where('size_on_disk', '>', 0)
        ->with('seasons.episodes.media')
        ->get();

    foreach ($shows as $show) {
        // `Show` also has a `seasons` attribute; use the relationship explicitly.
        $seasons = $show->seasons()->get();
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

Artisan::command('sync:sonarr {--sync}', function () {
    if ($this->option('sync')) {
        dispatch_sync(new \App\Jobs\SyncSonarrShowsJob());
        return;
    }

    dispatch(new \App\Jobs\SyncSonarrShowsJob());
});

Artisan::command('sync:trakt {--user_id=} {--all} {--sync}', function () {
    $userId = $this->option('user_id');
    $syncAll = (bool) $this->option('all');
    $runSync = (bool) $this->option('sync');

    if ($syncAll) {
        // Sync all users with Trakt connected
        $users = \App\Models\User::whereHas('credentials', function ($q) {
            $q->where('service', 'trakt')
              ->where('key', 'access_token')
              ->where('is_enabled', true);
        })->get();

        if ($users->isEmpty()) {
            $this->warn('No users have Trakt connected.');
            return;
        }

        $this->info("Syncing Trakt for {$users->count()} user(s)...");

        foreach ($users as $user) {
            $this->line("  - {$user->name} (ID: {$user->id})");
            $job = new \App\Jobs\SyncTraktTvShowsAndProgressJob($user);
            $runSync ? dispatch_sync($job) : dispatch($job);
        }

        return;
    }

    // Sync specific user or first user with Trakt connected
    if ($userId) {
        $user = \App\Models\User::find((int) $userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }
        if (!$user->hasTraktConnected()) {
            $this->error("User {$user->name} does not have Trakt connected.");
            return;
        }
    } else {
        // Find first user with Trakt connected
        $user = \App\Models\User::whereHas('credentials', function ($q) {
            $q->where('service', 'trakt')
              ->where('key', 'access_token')
              ->where('is_enabled', true);
        })->first();

        if (!$user) {
            $this->error('No users have Trakt connected. Connect Trakt in your profile settings.');
            return;
        }
    }

    $this->info("Syncing Trakt for {$user->name} (ID: {$user->id})...");
    $job = new \App\Jobs\SyncTraktTvShowsAndProgressJob($user);
    $runSync ? dispatch_sync($job) : dispatch($job);
})->purpose('Sync Trakt watch history for users');

Artisan::command('sync:radarr {--sync}', function () {
    if ($this->option('sync')) {
        dispatch_sync(new \App\Jobs\SyncRadarrMoviesJob());
        return;
    }

    dispatch(new \App\Jobs\SyncRadarrMoviesJob());
});

Artisan::command('sync:discover {--sync} {--count=250} {--shows} {--movies}', function () {
    $sync = (bool) $this->option('sync');
    $count = (int) $this->option('count');
    $count = max(1, min(2000, $count));

    $onlyShows = (bool) $this->option('shows');
    $onlyMovies = (bool) $this->option('movies');

    if (!$onlyShows && !$onlyMovies) {
        $onlyShows = true;
        $onlyMovies = true;
    }

    if ($onlyShows) {
        $job = new \App\Jobs\SyncDiscoverableShowsJob($count);
        if ($sync) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }
    }

    if ($onlyMovies) {
        $job = new \App\Jobs\SyncDiscoverableMoviesJob($count);
        if ($sync) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }
    }
})->purpose('Sync discoverable shows/movies on demand');

Artisan::command('discover:purge {--shows} {--movies}', function () {
    $onlyShows = (bool) $this->option('shows');
    $onlyMovies = (bool) $this->option('movies');

    if (!$onlyShows && !$onlyMovies) {
        $onlyShows = true;
        $onlyMovies = true;
    }

    if ($onlyShows) {
        \Illuminate\Support\Facades\DB::table('discoverable_shows')->truncate();
        $this->info('Purged discoverable_shows.');
    }

    if ($onlyMovies) {
        \Illuminate\Support\Facades\DB::table('discoverable_movies')->truncate();
        $this->info('Purged discoverable_movies.');
    }
})->purpose('Purge discoverable cache tables (not library)');

Artisan::command('sync:lidarr {--sync}', function () {
    if ($this->option('sync')) {
        dispatch_sync(new \App\Jobs\SyncLidarrMusicJob());
        return;
    }

    dispatch(new \App\Jobs\SyncLidarrMusicJob());
});

Artisan::command('episode-search-requests:prune {--days-completed=30} {--days-pending=7} {--dry-run}', function () {
    $daysCompleted = (int) $this->option('days-completed');
    $daysPending = (int) $this->option('days-pending');
    $dryRun = (bool) $this->option('dry-run');

    $cutoffCompleted = now()->subDays($daysCompleted);
    $cutoffPending = now()->subDays($daysPending);

    // Delete completed search requests older than the cutoff
    $completedQuery = \App\Models\EpisodeSearchRequest::query()
        ->whereNotNull('completed_at')
        ->where('completed_at', '<', $cutoffCompleted);

    $completedCount = $completedQuery->count();

    // Delete old pending/active requests that are likely stuck
    $pendingQuery = \App\Models\EpisodeSearchRequest::query()
        ->whereNull('completed_at')
        ->whereIn('status', ['pending', 'queued', 'started'])
        ->where('created_at', '<', $cutoffPending);

    $pendingCount = $pendingQuery->count();

    if ($dryRun) {
        $this->info("DRY RUN - Would delete:");
        $this->line("  - {$completedCount} completed search requests older than {$daysCompleted} days");
        $this->line("  - {$pendingCount} pending/active search requests older than {$daysPending} days");
        $this->line("  Total: " . ($completedCount + $pendingCount) . " records");
        return;
    }

    $deletedCompleted = $completedQuery->delete();
    $deletedPending = $pendingQuery->delete();

    $this->info("Pruned episode search requests:");
    $this->line("  - Deleted {$deletedCompleted} completed requests older than {$daysCompleted} days");
    $this->line("  - Deleted {$deletedPending} pending/active requests older than {$daysPending} days");
    $this->line("  Total deleted: " . ($deletedCompleted + $deletedPending) . " records");
});

Artisan::command('sync:classify {--show_id=}', function () {
    $showId = $this->option('show_id');
    if ($showId !== null) {
        $this->call(\App\Console\Commands\SyncShowClassificationCommand::class, [
            '--show_id' => (int) $showId,
        ]);
        return;
    }

    $this->call(\App\Console\Commands\SyncShowClassificationCommand::class);
});

Artisan::command('sync:movies-metadata {--movie_id=} {--sync}', function () {
    $movieId = $this->option('movie_id');
    $sync = (bool) $this->option('sync');

    if ($movieId !== null) {
        $this->call(\App\Console\Commands\SyncMovieMetadataCommand::class, [
            '--movie_id' => (int) $movieId,
            '--sync' => $sync,
        ]);
        return;
    }

    $this->call(\App\Console\Commands\SyncMovieMetadataCommand::class, [
        '--sync' => $sync,
    ]);
});

Artisan::command('movies:classify {--movie_id=}', function () {
    $movieId = $this->option('movie_id');
    if ($movieId !== null) {
        $this->call(\App\Console\Commands\SyncMovieClassificationCommand::class, [
            '--movie_id' => (int) $movieId,
        ]);
        return;
    }

    $this->call(\App\Console\Commands\SyncMovieClassificationCommand::class);
});

Artisan::command('credentials:set {service} {key} {value?} {--disabled}', function () {
    $service = (string) $this->argument('service');
    $key = (string) $this->argument('key');
    $value = $this->argument('value');
    $disabled = (bool) $this->option('disabled');

    /** @var \App\Services\Auth\CredentialStore $store */
    $store = app(\App\Services\Auth\CredentialStore::class);
    $store->set($service, $key, is_string($value) ? $value : null, enabled: !$disabled);

    $this->info("Saved credential {$service}.{$key} (enabled=" . (!$disabled ? 'true' : 'false') . ")");
});

Artisan::command('credentials:toggle {service} {key} {--enable} {--disable}', function () {
    $service = (string) $this->argument('service');
    $key = (string) $this->argument('key');

    /** @var \App\Services\Auth\CredentialStore $store */
    $store = app(\App\Services\Auth\CredentialStore::class);

    if ($this->option('enable')) {
        $store->enable($service, $key);
        $this->info("Enabled {$service}.{$key}");
        return;
    }

    if ($this->option('disable')) {
        $store->disable($service, $key);
        $this->info("Disabled {$service}.{$key}");
        return;
    }

    $this->error('Pass --enable or --disable');
});

Artisan::command('credentials:import-env', function () {
    /** @var \App\Services\Auth\CredentialStore $store */
    $store = app(\App\Services\Auth\CredentialStore::class);

    if (is_string(env('TRAKT_ACCESS_TOKEN'))) { 
        // Trakt
        $store->set('trakt', 'access_token', env('TRAKT_ACCESS_TOKEN'));
        $store->set('trakt', 'refresh_token', env('TRAKT_REFRESH_TOKEN'));
    }

    if (is_string(env('TMDB_API_KEY'))) {
        // TMDB
        $store->set('tmdb', 'api_key', env('TMDB_API_KEY'));
        $store->set('tmdb', 'bearer_token', env('TMDB_BEARER_TOKEN'));
    }

    if (is_string(env('SONARR_URL'))) {
        // Sonarr
        $store->set('sonarr', 'url', env('SONARR_URL') ?: null);
        $store->set('sonarr', 'api_key', env('SONARR_API_KEY') ?: null);
    }

    if (is_string(env('RADARR_URL'))) {
        // Radarr
        $store->set('radarr', 'url', env('RADARR_URL') ?: null);
        $store->set('radarr', 'api_key', env('RADARR_API_KEY') ?: null);
    }

    if (is_string(env('LIDARR_URL'))) {
        // Lidarr
        $store->set('lidarr', 'url', env('LIDARR_URL') ?: null);
        $store->set('lidarr', 'api_key', env('LIDARR_API_KEY') ?: null);
    }

    if (is_string(env('PLEX_TOKEN'))) {
        // Plex
        $store->set('plex', 'token', env('PLEX_TOKEN') ?: env('PLEX_API_TOKEN') ?: null);
    }

    $this->info('Imported known credentials from .env into database.');
});

Artisan::command('sync:plex {libraryId?} {--show-id=} {--movie-id=} {--type=}', function ($libraryId = null) {
    $plexService = app(\App\Services\PlexService::class);
    $showId = $this->option('show-id');
    $movieId = $this->option('movie-id');

    // Determine which types to process
    $allowedTypes = ['show', 'movie'];

    $libraries = $plexService->getLibraries();
    
    // Determine which libraries to process
    $librariesToProcess = [];
    
    if ($libraryId) {
        // Verify the library exists and get its type
        $selectedLibrary = $libraries->firstWhere('key', (string) $libraryId);
        if (!$selectedLibrary) {
            $this->error("Library with ID {$libraryId} not found.");
            return 1;
        }
        
        // Warn if library type is not supported
        if ($selectedLibrary->type && !in_array($selectedLibrary->type, $allowedTypes, true)) {
            $this->warn("Library type '{$selectedLibrary->type}' is not yet supported. Supported types: " . implode(', ', $allowedTypes));
            $this->warn("Skipping library as it is not a supported type.");
            return 0;
        }
        
        $librariesToProcess = [$selectedLibrary];
    } else {
        // If type is specified, find all libraries of that type; otherwise process all supported types
        $requestedType = $this->option('type');
        
        if ($requestedType) {
            if (!in_array($requestedType, $allowedTypes, true)) {
                $this->error("Type '{$requestedType}' is not supported. Supported types: " . implode(', ', $allowedTypes));
                return 1;
            }
            $targetLibraries = $libraries->where('type', $requestedType);
        } else {
            // Process all libraries of supported types
            $targetLibraries = $libraries->filter(function ($lib) use ($allowedTypes) {
                return in_array($lib->type, $allowedTypes, true);
            });
        }
        
        if ($targetLibraries->isEmpty()) {
            $this->error("No matching library found. Please specify library ID with: sync:plex {libraryId}");
            $this->line('Available libraries:');
            foreach ($libraries as $lib) {
                $this->line("  - {$lib->title} (ID: {$lib->key}, Type: {$lib->type})");
            }
            return 1;
        }
        
        $librariesToProcess = $targetLibraries;
        $this->info("Found {$librariesToProcess->count()} " . ($librariesToProcess->count() === 1 ? 'library' : 'libraries') . " to process");
    }

    // Track totals across all libraries
    $totalMatched = 0;
    $totalSkipped = 0;
    $totalUpdated = 0;

    // Process each library
    foreach ($librariesToProcess as $libraryIndex => $library) {
        $libraryId = (int) $library->key;
        $this->newLine();
        $this->info("Processing library: {$library->title} (ID: {$libraryId}, Type: {$library->type})");
        
        // Get all items from Plex library (JSON API with full metadata)
        // Note: This may include collections and playlists due to includeCollections=1
        // Map library type to Plex API type: 'show' = '2', 'movie' = '1'
        $plexTypeMap = [
            'show' => '2',
            'movie' => '1',
        ];
        $plexType = $plexTypeMap[$library->type] ?? null;
        $queryParams = $plexType ? ['type' => $plexType] : [];
        $plexItems = $plexService->getLibraryItems($libraryId, $queryParams);

        $totalCount = $plexItems->count();
        $this->info("Found {$totalCount} items in library (may include collections/playlists)");

        $matched = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($plexItems as $index => $plexItem) {
        $index++;
        $plexTitle = $plexItem->title;
        $plexKey = str_replace('/children', '', $plexItem->key);

        // Skip collections and playlists
        if ($plexItem->type === 'collection') {
            $this->line("[{$index}/{$totalCount}] Skipping: {$plexTitle} - collection");
            $skipped++;
            continue;
        }

        if ($plexItem->type === 'playlist') {
            $this->line("[{$index}/{$totalCount}] Skipping: {$plexTitle} - playlist");
            $skipped++;
            continue;
        }

        // Skip unsupported types
        if (!in_array($plexItem->type, $allowedTypes, true)) {
            $this->line("[{$index}/{$totalCount}] Skipping: {$plexTitle} - unsupported type ({$plexItem->type})");
            $skipped++;
            continue;
        }

        // Process based on item type
        if ($plexItem->type === 'show') {
            // If --show-id is specified, only process that show
            if ($showId) {
                $localShow = \App\Models\Show::find($showId);
                if (!$localShow) {
                    $this->error("Show with ID {$showId} not found");
                    return 1;
                }
                // Only process if this Plex show matches
                $matchedShow = $plexService->matchPlexShowToLocalShow(
                    $plexTitle,
                    $plexItem->guid,
                    $plexItem->year,
                    $plexItem->originalTitle,
                    $plexItem->slug
                );
                if (!$matchedShow || $matchedShow->id !== (int) $showId) {
                    continue;
                }
            }

            // Use improved matching logic with all available fields
            $localShow = $plexService->matchPlexShowToLocalShow(
                $plexTitle,
                $plexItem->guid,
                $plexItem->year,
                $plexItem->originalTitle,
                $plexItem->slug
            );

            if (!$localShow) {
                $this->warn("[{$index}/{$totalCount}] Skipping: {$plexTitle} - No local show found");
                $skipped++;
                continue;
            }

            // Check if already synced
            if ($localShow->plex_id === $plexKey) {
                $this->line("[{$index}/{$totalCount}] Already synced: {$plexTitle} → {$localShow->name}");
                $matched++;
                continue;
            }

            // Update Plex ID
            $localShow->update(['plex_id' => $plexKey]);
            $this->info("[{$index}/{$totalCount}] Synced: {$plexTitle} → {$localShow->name}");
            $updated++;
            $matched++;
        } elseif ($plexItem->type === 'movie') {
            // If --movie-id is specified, only process that movie
            if ($movieId) {
                $localMovie = \App\Models\Movie::find($movieId);
                if (!$localMovie) {
                    $this->error("Movie with ID {$movieId} not found");
                    return 1;
                }
                // Only process if this Plex movie matches
                $matchedMovie = $plexService->matchPlexMovieToLocalMovie(
                    $plexTitle,
                    $plexItem->guid,
                    $plexItem->year,
                    $plexItem->originalTitle,
                    $plexItem->slug
                );
                if (!$matchedMovie || $matchedMovie->id !== (int) $movieId) {
                    continue;
                }
            }

            // Use improved matching logic with all available fields
            $localMovie = $plexService->matchPlexMovieToLocalMovie(
                $plexTitle,
                $plexItem->guid,
                $plexItem->year,
                $plexItem->originalTitle,
                $plexItem->slug
            );

            if (!$localMovie) {
                $this->warn("[{$index}/{$totalCount}] Skipping: {$plexTitle} - No local movie found");
                $skipped++;
                continue;
            }

            // Check if already synced
            if ($localMovie->plex_id === $plexKey) {
                $this->line("[{$index}/{$totalCount}] Already synced: {$plexTitle} → {$localMovie->name}");
                $matched++;
                continue;
            }

            // Update Plex ID
            $localMovie->update(['plex_id' => $plexKey]);
            $this->info("[{$index}/{$totalCount}] Synced: {$plexTitle} → {$localMovie->name}");
            $updated++;
            $matched++;
        }
        }

        // Add this library's totals to overall totals
        $totalMatched += $matched;
        $totalSkipped += $skipped;
        $totalUpdated += $updated;

        $this->info("Library summary: Matched: {$matched}, Updated: {$updated}, Skipped: {$skipped}");
    }

    $this->newLine();
    $this->info("Overall Summary:");
    $this->info("  Matched: {$totalMatched}");
    $this->info("  Updated: {$totalUpdated}");
    $this->info("  Skipped: {$totalSkipped}");

    return 0;
});
