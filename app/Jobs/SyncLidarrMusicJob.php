<?php

namespace App\Jobs;

use App\Contracts\LidarrServiceContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLidarrMusicJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(LidarrServiceContract $lidarrService): void
    {
        $artists = $lidarrService->getArtists()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->artistName,
                'path' => $a->path,
                'genres' => $a->genres,
            ])
            ->values()
            ->all();

        Storage::disk('local')->put(
            'lidarr/artists.json',
            json_encode(['synced_at' => now()->toIso8601String(), 'artists' => $artists], JSON_PRETTY_PRINT)
        );
    }
}
