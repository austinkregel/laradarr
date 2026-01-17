<?php

namespace App\Jobs;

use App\Models\Show;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAllShowsMetadataJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('metadata');
    }

    public function tags(): array
    {
        return ['metadata', 'metadata:all_shows'];
    }

    public function displayName(): string
    {
        return self::class;
    }

    public function handle(): void
    {
        Show::query()
            ->where(function ($q) {
                $q->whereNotNull('tmdb_id')
                    ->orWhereNotNull('trakt_id');
            })
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(250, function ($shows) {
                foreach ($shows as $show) {
                    dispatch(new SyncShowMetadataJob((int) $show->id));
                }
            });
    }
}







