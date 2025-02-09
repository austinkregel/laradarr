<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncSonarrShowsJob implements ShouldQueue
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
    public function handle(): void
    {
        $series = Http::withHeaders([])
            ->get(config('services.sonarr.url').'/api/v3/series?apikey='.config('services.sonarr.api_key'))
            ->json();

        foreach ($series as $show) {
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

            // Now we want to sync the episodes
            $episodes = Http::withHeaders([])
                ->get(config('services.sonarr.url').'/api/v3/episode?seriesId='.$show['id'].'&apikey='.config('services.sonarr.api_key'))
                ->json();


            foreach ($episodes as $episode) {
                if ($episode['title'] === 'TBA') {
                    // Don't sync episodes that are TBA
                    continue;
                }

                if (($episode['overview'] ?? '') === 'TBA') {
                    // Don't sync episodes that are TBA
                    continue;
                }

                if (($episode['seasonNumber'] ?? 1) === 0) {
                    // Don't sync episodes that are season 0
                    continue;
                }

                if (empty($episode['seasonNumber'])) {
                    $episode['seasonNumber'] = 1;
                }

                $localSeason = $localShow->seasons()->firstOrCreate(['season' => $episode['seasonNumber']], [
                    'name' => 'Season '.$episode['seasonNumber'],
                ]);

                /** @var Episode $localEpisode */
                $localEpisode = $localShow->episodes()->firstWhere('sonarr_episode_id', $episode['id']);

                $attributes = [
                    'sonarr_episode_id' => $episode['id'],
                    'name' => $episode['title'],
                    'description' => $episode['overview'] ?? null,
                    'episode_number' => $episode['episodeNumber'],
                    'has_file' => $episode['hasFile'],
                    'runtime' => $episode['runtime'],
                    'tvdb_id' => $episode['tvdbId'],
                    'sonarr_episode_file_id' => $episode['episodeFileId'],
                    'sonarr_series_id' => $episode['seriesId'],
                    'aired_at' => $episode['airDateUtc'] ?? $episode['airDate'] ?? null,
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

                if ($localEpisode->hasMedia()) {
                    // At the moment we don't want to add more than 1 media
                    continue;
                }

                if (!$episode['hasFile']) {
                    // Don't sync episodes that don't have a file
                    continue;
                }

                // Now we want to sync the episodes
                $mediaFile = Http::withHeaders([])
                    ->get(config('services.sonarr.url').'/api/v3/episodefile/'.$episode['episodeFileId'].'?seriesId='.$show['id'].'&apikey='.config('services.sonarr.api_key'))
                    ->json();

                if (!isset($mediaFile['path'])) {
                    dd($mediaFile, $episode);
                }

                if ($localEpisode->media()->where('name' , basename($mediaFile['path']))->exists()) {
                    // We already have this media
                    continue;
                }

                $extension = pathinfo($mediaFile['path'], PATHINFO_EXTENSION);

                $localEpisode->media()
                    ->create([
                        "uuid" => Str::uuid(),
                        "collection_name" => "shows",
                        "name" => basename($mediaFile['path']),
                        "file_name" => Str::slug(basename($mediaFile['path'])),
                        "mime_type" => match ($extension) {
                            'mkv' => "video/x-matroska",
                            'mp4' => "video/mp4",
                            'avi' => "video/x-msvideo",
                            'mov' => "video/quicktime",
                            'wmv' => "video/x-ms-wmv",
                            'flv' => "video/x-flv",
                            'webm' => "video/webm",
                            'm4v' => "video/x-m4v",
                            'mpg' => "video/mpeg",
                            'mpeg' => "video/mpeg",
                            'ts' => "video/mpeg2",
                            '3gp' => "video/3gpp",
                            '3g2' => "video/3gpp2",
                            'iso' => "application/x-iso9660-image",
                            default => null,
                        },
                        "disk" => "local",
                        "conversions_disk" => "local",
                        "size" => $mediaFile['size'],
                        "manipulations" => [],
                        "custom_properties" => [
                            'path' => $mediaFile['path'],
                            'languages' => array_map(fn ($i) => $i['name'], $mediaFile['languages']),
                        ],
                        "generated_conversions" => [],
                        "responsive_images" => [],
                    ]);

            }
        }
    }

    protected function findShow($show)
    {
        $showIds = array_filter([
            'sonarr_id' => $show['id'],
            'imdb_id' => $show['imdbId'] ?? null,
            'tvdb_id' => $show['tvdbId'] ?? null,
            'tmdb_id' => $show['tmdbId'] ?? null,
            'slug' => $show['titleSlug'],
        ]);

        foreach ($showIds as $columnName => $id) {
            $localShow = Show::query()->with('seasons')->firstWhere($columnName, $id);

            if (isset($localShow)) {
                return $localShow;
            }
        }

        return Show::create($this->convertShow($show));
    }

    protected function convertShow(mixed $show)
    {
        $poster = array_values(array_filter($show['images'], function ($image) {
            return $image['coverType'] === 'poster';
        }))[0] ?? ['url' => null];

        $banner = array_values(array_filter($show['images'], function ($image) {
            return $image['coverType'] === 'banner';
        }))[0] ?? ['url' => null];

        $logo = array_values(array_filter($show['images'], function ($image) {
            return $image['coverType'] === 'clearlogo';
        }))[0] ?? ['url' => null];

        return [
            'sonarr_id' => $show['id'],
            'name' => $show['title'],
            'aliases' => array_map(fn ($i) => $i['title'], $show['alternateTitles']),
            'slug' => $show['titleSlug'],
            'description' => $show['overview']??null,
            'release_year' => $show['year'],
            'type' => $show['seriesType'],
            'path' => $show['path'],
            'poster_image' => $poster['url'] ? config('services.sonarr.url').$poster['url'] : null,
            'banner_image' => $banner['url'] ? config('services.sonarr.url').$banner['url'] : null,
            'logo_image' => $logo['url'] ? config('services.sonarr.url').$logo['url'] : null,
            'size_on_disk' => isset($show['statistics']) ? $show['statistics']['sizeOnDisk'] : 0,
            'imdb_id' => $show['imdbId'] ?? null,
            'tvdb_id' => $show['tvdbId'] ?? null,
            'tmdb_id' => $show['tmdbId'] ?? null,
            'released_at' => isset($show['firstAired']) ? Carbon::parse($show['firstAired']): null,
            'season_count' => count(array_filter($show['seasons'], fn ($i) => ($i['statistics']['episodeCount'] ?? 0) > 0)),
            'episode_count' => array_sum(array_map(fn ($i) => $i['statistics']['episodeCount'] ?? 0, $show['seasons'] ?? [])),
        ];
    }
}
