<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Movie;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAllMoviesMetadataJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('metadata');
    }

    public function tags(): array
    {
        return ['metadata', 'metadata:all_movies'];
    }

    public function displayName(): string
    {
        return self::class;
    }

    public function handle(): void
    {
        Movie::query()
            ->where(function ($q) {
                $q->whereNotNull('tmdb_id')
                    ->orWhereNotNull('trakt_id');
            })
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(250, function ($movies) {
                foreach ($movies as $movie) {
                    dispatch(new SyncMovieMetadataJob((int) $movie->id));
                }
            });
    }
}

