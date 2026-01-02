<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MetadataSyncServiceContract;
use App\Models\Movie;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SyncMovieMetadataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $movieId,
    ) {
        $this->onQueue('metadata');
    }

    public function tags(): array
    {
        $movie = Movie::query()->select(['id', 'name', 'slug', 'trakt_id', 'tmdb_id'])->find($this->movieId);

        return array_values(array_filter([
            'metadata',
            'metadata:movie',
            $movie?->id ? 'movie_id:'.$movie->id : null,
            $movie?->tmdb_id ? 'tmdb_id:'.$movie->tmdb_id : null,
            $movie?->trakt_id ? 'trakt_id:'.$movie->trakt_id : null,
            $movie?->slug ? 'movie_slug:'.$movie->slug : null,
            $movie?->name ? 'movie:'.Str::limit($movie->name, 50) : null,
        ]));
    }

    public function displayName(): string
    {
        return self::class.' (#'.$this->movieId.')';
    }

    public function handle(MetadataSyncServiceContract $metadataSyncService): void
    {
        $movie = Movie::query()->find($this->movieId);
        if (!$movie) {
            return;
        }

        $metadataSyncService->syncMovieMetadata($movie);
    }
}

