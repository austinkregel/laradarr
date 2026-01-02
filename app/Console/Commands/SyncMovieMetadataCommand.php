<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncAllMoviesMetadataJob;
use App\Jobs\SyncMovieMetadataJob;
use Illuminate\Console\Command;

class SyncMovieMetadataCommand extends Command
{
    protected $signature = 'sync:movies-metadata {--movie_id=} {--sync}';

    protected $description = 'Sync movie metadata (ratings, genres, external ids, dub languages) from external APIs';

    public function handle(): int
    {
        $movieId = $this->option('movie_id');
        $sync = (bool) $this->option('sync');

        if ($movieId !== null) {
            $job = new SyncMovieMetadataJob((int) $movieId);
            $sync ? dispatch_sync($job) : dispatch($job);
            $this->info('Queued metadata sync for movie_id='.(int) $movieId);
            return self::SUCCESS;
        }

        $job = new SyncAllMoviesMetadataJob();
        $sync ? dispatch_sync($job) : dispatch($job);
        $this->info('Queued metadata sync for all movies');

        return self::SUCCESS;
    }
}



