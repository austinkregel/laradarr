<?php

namespace Tests;

use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use App\Models\Show;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper method to add an episode with English dub media to a show.
     */
    protected function addEpisodeWithEnglishDub(Show $show, int $seasonNumber = 1, int $episodeNumber = 1): Episode
    {
        $season = Season::firstOrCreate(
            [
                'show_id' => $show->id,
                'season' => $seasonNumber,
            ],
            [
                'show_id' => $show->id,
                'name' => "Season {$seasonNumber}",
            ]
        );

        $episode = Episode::create([
            'season_id' => $season->id,
            'episode_number' => $episodeNumber,
            'name' => "Episode {$episodeNumber}",
            'has_file' => true,
        ]);

        $media = $episode->media()->create([
            'uuid' => Str::uuid(),
            'collection_name' => 'shows',
            'name' => "{$show->name} - S{$seasonNumber}E{$episodeNumber}.mkv",
            'file_name' => Str::slug("{$show->name} - S{$seasonNumber}E{$episodeNumber}"),
            'mime_type' => 'video/x-matroska',
            'disk' => 'local',
            'conversions_disk' => 'local',
            'size' => 1000000,
            'manipulations' => [],
            'custom_properties' => [
                'languages' => ['English', 'Japanese'],
            ],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        // Add English dub tags
        $media->syncTags(['dub', 'dub-english']);

        return $episode;
    }
}
