<?php

namespace App\Jobs;

use App\Contracts\MediaTaggingServiceContract;
use App\Contracts\SonarrServiceContract;
use App\Models\Episode;
use App\Models\Show;
use App\Jobs\SyncShowMetadataJob;
use App\Services\DTOs\Sonarr\EpisodeDTO;
use App\Services\DTOs\Sonarr\ShowDTO;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncSonarrShowJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param array $showData Sonarr show payload compatible with ShowDTO::fromArray()
     */
    public function __construct(
        public array $showData,
    ) {
        //
    }

    /**
     * Horizon job tags (helps group + count jobs by show).
     */
    public function tags(): array
    {
        $id = $this->showData['id'] ?? null;
        $title = $this->showData['title'] ?? null;
        $slug = $this->showData['titleSlug'] ?? (is_string($title) ? Str::slug($title) : null);

        return array_values(array_filter([
            'sonarr',
            'sonarr:show',
            is_numeric($id) ? 'sonarr_id:'.(int) $id : null,
            is_string($title) && $title !== '' ? 'show:'.$title : null,
            is_string($slug) && $slug !== '' ? 'show_slug:'.$slug : null,
        ]));
    }

    /**
     * More readable name in Horizon / job payloads.
     */
    public function displayName(): string
    {
        $title = $this->showData['title'] ?? null;
        return is_string($title) && $title !== '' ? self::class.' ('.$title.')' : self::class;
    }

    /**
     * Execute the job.
     */
    public function handle(SonarrServiceContract $sonarrService, MediaTaggingServiceContract $mediaTaggingService): void
    {
        $show = ShowDTO::fromArray($this->showData);

        $attributes = $this->convertShow($show);

        $localShow = $this->findShow($show);

        if (empty($localShow)) {
            $localShow = Show::create($attributes);
        } else {
            foreach ($attributes as $attribute => $value) {
                if ($localShow->{$attribute} === $value) {
                    continue;
                }
                $localShow->{$attribute} = $value;
            }
            if ($localShow->isDirty()) {
                $localShow->save();
            }
        }

        if (!empty($localShow->tmdb_id) || !empty($localShow->trakt_id)) {
            dispatch(new SyncShowMetadataJob((int) $localShow->id));
        }

        // Now we want to sync the episodes
        $episodes = $sonarrService->getEpisodes($show->id);

        /** @var EpisodeDTO $episode */
        foreach ($episodes as $episode) {
            if ($episode->title === 'TBA') {
                // Don't sync episodes that are TBA
                continue;
            }

            if (($episode->overview ?? '') === 'TBA') {
                // Don't sync episodes that are TBA
                continue;
            }

            if (($episode->seasonNumber ?? 1) === 0) {
                // Don't sync episodes that are season 0
                continue;
            }

            $seasonNumber = $episode->seasonNumber ?: 1;

            $localSeason = $localShow->seasons()->firstOrCreate(['season' => $seasonNumber], [
                'name' => 'Season '.$seasonNumber,
            ]);

            /** @var Episode $localEpisode */
            $localEpisode = $localShow->episodes()->firstWhere('sonarr_episode_id', $episode->id);

            $attributes = [
                'sonarr_episode_id' => $episode->id,
                'name' => $episode->title,
                'description' => $episode->overview,
                'episode_number' => $episode->episodeNumber,
                'has_file' => $episode->hasFile,
                'runtime' => $episode->runtime,
                'tvdb_id' => $episode->tvdbId,
                'sonarr_episode_file_id' => $episode->episodeFileId,
                'sonarr_series_id' => $episode->seriesId,
                'aired_at' => $episode->airDateUtc ?? $episode->airDate,
            ];

            if (empty($localEpisode)) {
                $localEpisode = $localSeason->episodes()->create($attributes);
            } else {
                foreach ($attributes as $attribute => $value) {
                    if ($localEpisode->{$attribute} === $value) {
                        continue;
                    }
                    $localEpisode->{$attribute} = $value;
                }
            if ($localEpisode->isDirty()) {
                $localEpisode->save();
            }
        }

        // Handle media deletion: if episode no longer has a file, delete all media
        if (!$episode->hasFile && $localEpisode->hasMedia()) {
            Log::info('sonarr.episode_file_deleted', [
                'show_id' => $show->id,
                'episode_id' => $episode->id,
                'local_episode_id' => $localEpisode->id,
                'previous_file_id' => $localEpisode->sonarr_episode_file_id,
            ]);
            $localEpisode->clearMediaCollection('shows');
            continue;
        }

        // Handle file replacement: if episode file ID changed, delete old media
        if ($episode->hasFile 
            && $episode->episodeFileId !== null 
            && $localEpisode->sonarr_episode_file_id !== null 
            && $localEpisode->sonarr_episode_file_id !== $episode->episodeFileId
            && $localEpisode->hasMedia()
        ) {
            Log::info('sonarr.episode_file_replaced', [
                'show_id' => $show->id,
                'episode_id' => $episode->id,
                'local_episode_id' => $localEpisode->id,
                'old_file_id' => $localEpisode->sonarr_episode_file_id,
                'new_file_id' => $episode->episodeFileId,
            ]);
            $localEpisode->clearMediaCollection('shows');
        }

        if ($localEpisode->hasMedia()) {
            // At the moment we don't want to add more than 1 media
            continue;
        }

        if (!$episode->hasFile) {
            // Can't sync episodes media files that don't exist
            continue;
        }

            if ($episode->episodeFileId === null) {
                continue;
            }

            // Now we want to sync the episodes
            $mediaFile = $sonarrService->getEpisodeFile($episode->episodeFileId, $show->id);
            $path = $mediaFile->path;

            if ($path === null || $path === '') {
                Log::warning('sonarr.episode_file_missing_path', [
                    'show_id' => $show->id,
                    'episode_id' => $episode->id,
                    'episode_file_id' => $episode->episodeFileId,
                ]);
                continue;
            }

            if ($localEpisode->media()->where('name', basename($path))->exists()) {
                // We already have this media
                continue;
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION);

            $media = $localEpisode->media()->create([
                    'uuid' => Str::uuid(),
                    'collection_name' => 'shows',
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
                    'size' => $mediaFile->size,
                    'manipulations' => [],
                    'custom_properties' => [
                        'path' => $path,
                        'languages' => array_values(array_filter(array_map(
                            fn ($i) => is_array($i) ? ($i['name'] ?? null) : null,
                            $mediaFile->languages
                        ))),
                    ],
                    'generated_conversions' => [],
                    'responsive_images' => [],
                ]);

            $mediaTaggingService->tagMediaFile($media, $localShow);
        }
    }

    protected function findShow(ShowDTO $show): Show
    {
        $showIds = array_filter([
            'sonarr_id' => $show->id,
            'imdb_id' => $show->imdbId,
            'tvdb_id' => $show->tvdbId,
            'tmdb_id' => $show->tmdbId,
            'slug' => $show->titleSlug,
        ]);

        foreach ($showIds as $columnName => $id) {
            $localShow = Show::query()->with('seasons')->firstWhere($columnName, $id);

            if (isset($localShow)) {
                return $localShow;
            }
        }

        return Show::create($this->convertShow($show));
    }

    protected function convertShow(ShowDTO $show): array
    {
        $poster = array_values(array_filter($show->images, function ($image) {
            return is_array($image) && ($image['coverType'] ?? null) === 'poster';
        }))[0] ?? ['url' => null];

        $banner = array_values(array_filter($show->images, function ($image) {
            return is_array($image) && ($image['coverType'] ?? null) === 'banner';
        }))[0] ?? ['url' => null];

        $logo = array_values(array_filter($show->images, function ($image) {
            return is_array($image) && ($image['coverType'] ?? null) === 'clearlogo';
        }))[0] ?? ['url' => null];

        return [
            'sonarr_id' => $show->id,
            'name' => $show->title,
            'aliases' => array_values(array_filter(array_map(
                fn ($i) => is_array($i) ? ($i['title'] ?? null) : null,
                $show->alternateTitles
            ))),
            'slug' => $show->titleSlug,
            'description' => $show->overview,
            'release_year' => $show->year,
            'type' => $show->seriesType,
            'path' => $show->path,
            'poster_image' => ($poster['url'] ?? null) ? config('services.sonarr.url').$poster['url'] : null,
            'banner_image' => ($banner['url'] ?? null) ? config('services.sonarr.url').$banner['url'] : null,
            'logo_image' => ($logo['url'] ?? null) ? config('services.sonarr.url').$logo['url'] : null,
            'size_on_disk' => isset($show->statistics['sizeOnDisk']) ? (int) $show->statistics['sizeOnDisk'] : 0,
            'imdb_id' => $show->imdbId,
            'tvdb_id' => $show->tvdbId,
            'tmdb_id' => $show->tmdbId,
            'released_at' => $show->firstAired ? Carbon::parse($show->firstAired->toIso8601String()) : null,
            'season_count' => count(array_filter($show->seasons, fn ($i) => is_array($i) && (($i['statistics']['episodeCount'] ?? 0) > 0))),
            'episode_count' => array_sum(array_map(fn ($i) => is_array($i) ? (int) ($i['statistics']['episodeCount'] ?? 0) : 0, $show->seasons ?? [])),
        ];
    }
}



