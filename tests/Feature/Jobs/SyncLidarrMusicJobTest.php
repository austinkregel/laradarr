<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncLidarrMusicJob;
use App\Services\DTOs\Lidarr\ArtistDTO;
use App\Services\LidarrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncLidarrMusicJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_writes_artists_json_to_storage(): void
    {
        Storage::fake('local');

        $artist = new ArtistDTO(id: 1, artistName: 'Artist', path: '/music/artist', genres: []);

        $this->mock(LidarrService::class, function ($mock) use ($artist) {
            $mock->shouldReceive('getArtists')->andReturn(collect([$artist]));
        });

        $job = new SyncLidarrMusicJob();
        $job->handle(app(LidarrService::class));

        Storage::disk('local')->assertExists('lidarr/artists.json');
    }
}








