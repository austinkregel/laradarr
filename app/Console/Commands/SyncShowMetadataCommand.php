<?php

namespace App\Console\Commands;

use App\Jobs\SyncAllShowsMetadataJob;
use App\Jobs\SyncShowMetadataJob;
use Illuminate\Console\Command;

class SyncShowMetadataCommand extends Command
{
    protected $signature = 'sync:metadata {--show_id=} {--sync}';

    protected $description = 'Sync show metadata (ratings, genres, external ids, dub languages) from external APIs';

    public function handle(): int
    {
        $showId = $this->option('show_id');
        $sync = (bool) $this->option('sync');

        if ($showId !== null) {
            $job = new SyncShowMetadataJob((int) $showId);
            $sync ? dispatch_sync($job) : dispatch($job);
            $this->info('Queued metadata sync for show_id='.(int) $showId);
            return self::SUCCESS;
        }

        $job = new SyncAllShowsMetadataJob();
        $sync ? dispatch_sync($job) : dispatch($job);
        $this->info('Queued metadata sync for all shows');

        return self::SUCCESS;
    }
}





