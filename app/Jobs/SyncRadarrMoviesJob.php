<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MediaTaggingServiceContract;
use App\Contracts\RadarrServiceContract;
use App\Models\Media;
use App\Models\Movie;
use App\Jobs\SyncMovieMetadataJob;
use App\Services\DTOs\Radarr\MovieDTO;
use App\Services\DTOs\Radarr\MovieFileDTO;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncRadarrMoviesJob implements ShouldQueue
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
     * Horizon job tags (helps group + count jobs by movie).
     */
    public function tags(): array
    {
        return [
            'radarr',
            'radarr:movie',
        ];
    }

    /**
     * More readable name in Horizon / job payloads.
     */
    public function displayName(): string
    {
        return self::class;
    }

    /**
     * Execute the job.
     */
    public function handle(RadarrServiceContract $radarrService, MediaTaggingServiceContract $mediaTaggingService): void
    {
        $movies = $radarrService->getMovies();

        /** @var MovieDTO $movie */
        foreach ($movies as $movie) {
            $attributes = $this->convertMovie($movie);

            $localMovie = $this->findMovie($movie);

            if (empty($localMovie)) {
                $localMovie = Movie::create($attributes);
            } else {
                foreach ($attributes as $attribute => $value) {
                    if ($localMovie->{$attribute} === $value) {
                        continue;
                    }
                    $localMovie->{$attribute} = $value;
                }
                if ($localMovie->isDirty()) {
                    $localMovie->save();
                }
            }

            if (!empty($localMovie->tmdb_id)) {
                dispatch(new SyncMovieMetadataJob((int) $localMovie->id));
            }

            // Handle media file syncing
            if ($movie->hasFile && $movie->movieFileId !== null) {
                $this->syncMovieFile($radarrService, $mediaTaggingService, $movie, $localMovie);
            } elseif (!$movie->hasFile && $localMovie->media()->exists()) {
                // Handle file deletion: if movie no longer has a file, delete all media
                Log::info('radarr.movie_file_deleted', [
                    'movie_id' => $movie->id,
                    'local_movie_id' => $localMovie->id,
                    'previous_file_id' => $localMovie->movie_file_id,
                ]);
                $localMovie->clearMediaCollection('movies');
            }
        }
    }

    protected function syncMovieFile(
        RadarrServiceContract $radarrService,
        MediaTaggingServiceContract $mediaTaggingService,
        MovieDTO $movie,
        Movie $localMovie
    ): void {
        if ($movie->movieFileId === null) {
            return;
        }

        $movieFile = $radarrService->getMovieFile($movie->movieFileId);
        $path = $movieFile->path;

        if ($path === null || $path === '') {
            Log::warning('radarr.movie_file_missing_path', [
                'movie_id' => $movie->id,
                'movie_file_id' => $movie->movieFileId,
            ]);
            return;
        }

        // Handle file replacement: if movie file ID changed, delete old media
        if ($localMovie->movie_file_id !== null
            && $localMovie->movie_file_id !== $movie->movieFileId
            && $localMovie->media()->exists()
        ) {
            Log::info('radarr.movie_file_replaced', [
                'movie_id' => $movie->id,
                'local_movie_id' => $localMovie->id,
                'old_file_id' => $localMovie->movie_file_id,
                'new_file_id' => $movie->movieFileId,
            ]);
            $localMovie->clearMediaCollection('movies');
        }

        if ($localMovie->media()->where('name', basename($path))->exists()) {
            // We already have this media
            return;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $media = $localMovie->media()->create([
            'uuid' => Str::uuid(),
            'collection_name' => 'movies',
            'name' => basename($path),
            'file_name' => Str::slug(basename($path)),
            'mime_type' => match ($extension) {
                'mkv' => 'video/x-matroska',
                'mp4' => 'video/mp4',
                'avi' => 'video/x-msvideo',
                'mov' => 'video/quicktime',
                'wmv' => 'video/x-ms-wmv',
                'flv' => 'video/x-flv',
                'webm' => 'video/webm',
                'm4v' => 'video/x-m4v',
                'mpg' => 'video/mpeg',
                'mpeg' => 'video/mpeg',
                'ts' => 'video/mpeg2',
                '3gp' => 'video/3gpp',
                '3g2' => 'video/3gpp2',
                'iso' => 'application/x-iso9660-image',
                default => null,
            },
            'disk' => 'local',
            'conversions_disk' => 'local',
            'size' => $movieFile->size,
            'manipulations' => [],
            'custom_properties' => [
                'path' => $path,
                'languages' => array_values(array_filter(array_map(
                    fn ($i) => is_array($i) ? ($i['name'] ?? null) : null,
                    $movieFile->languages
                ))),
            ],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $mediaTaggingService->tagMediaFile($media, $localMovie);
    }

    protected function findMovie(MovieDTO $movie): ?Movie
    {
        $movieIds = array_filter([
            'radarr_id' => $movie->id,
            'imdb_id' => $movie->imdbId,
            'tmdb_id' => $movie->tmdbId,
            'slug' => $movie->titleSlug,
        ]);

        foreach ($movieIds as $columnName => $id) {
            $localMovie = Movie::query()->firstWhere($columnName, $id);

            if (isset($localMovie)) {
                return $localMovie;
            }
        }

        return null;
    }

    protected function convertMovie(MovieDTO $movie): array
    {
        $poster = array_values(array_filter($movie->images, function ($image) {
            return is_array($image) && ($image['coverType'] ?? null) === 'poster';
        }))[0] ?? ['url' => null];

        $banner = array_values(array_filter($movie->images, function ($image) {
            return is_array($image) && ($image['coverType'] ?? null) === 'banner';
        }))[0] ?? ['url' => null];

        $logo = array_values(array_filter($movie->images, function ($image) {
            return is_array($image) && ($image['coverType'] ?? null) === 'clearlogo';
        }))[0] ?? ['url' => null];

        return [
            'radarr_id' => $movie->id,
            'name' => $movie->title,
            'aliases' => array_values(array_filter(array_map(
                fn ($i) => is_array($i) ? ($i['title'] ?? null) : null,
                $movie->alternateTitles
            ))),
            'slug' => $movie->titleSlug,
            'description' => $movie->overview,
            'release_year' => $movie->year,
            'released_at' => $movie->physicalRelease ? Carbon::parse($movie->physicalRelease->toIso8601String()) : null,
            'runtime' => $movie->runtime,
            'movie_file_id' => $movie->movieFileId ?? 0,
            'is_available' => $movie->hasFile,
            'in_cinemas' => $movie->inCinemas ? Carbon::parse($movie->inCinemas->toIso8601String()) : null,
            'imdb_id' => $movie->imdbId,
            'tmdb_id' => $movie->tmdbId,
            'added_at' => $movie->added ? Carbon::parse($movie->added->toIso8601String()) : null,
            'path' => $movie->path,
            'poster_image' => ($poster['url'] ?? null) ? config('services.radarr.url').$poster['url'] : null,
            'banner_image' => ($banner['url'] ?? null) ? config('services.radarr.url').$banner['url'] : null,
            'logo_image' => ($logo['url'] ?? null) ? config('services.radarr.url').$logo['url'] : null,
            'size_on_disk' => isset($movie->statistics['sizeOnDisk']) ? (int) $movie->statistics['sizeOnDisk'] : 0,
        ];
    }
}
