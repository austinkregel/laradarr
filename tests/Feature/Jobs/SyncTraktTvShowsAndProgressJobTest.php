<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncTraktTvShowsAndProgressJob;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Show;
use App\Models\User;
use App\Services\DTOs\Trakt\EpisodeDTO;
use App\Services\DTOs\Trakt\ShowDTO;
use App\Services\TraktTvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncTraktTvShowsAndProgressJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_watched_episodes_for_user(): void
    {
        $user = User::factory()->create();

        $show = Show::create([
            'name' => 'Example',
            'trakt_id' => 123,
            'slug' => 'example',
            'release_year' => 2024,
        ]);

        $season = $show->seasons()->create([
            'season' => 1,
            'name' => 'Season 1',
        ]);

        $episode = $season->episodes()->create([
            'name' => 'Pilot',
            'episode_number' => 1,
            'has_file' => false,
        ]);

        $dto = new ShowDTO(
            id: 123,
            ids: ['trakt' => 123, 'slug' => 'example'],
            name: 'Example',
            releaseYear: 2024,
            episodes: [new EpisodeDTO(key: 'S01E01', season: 1, number: 1, watchedAt: '2024-01-01T00:00:00Z')],
        );

        Http::fake([
            'https://api.trakt.tv/*' => Http::response([], 200),
        ]);

        Http::fake([
            'https://api.trakt.tv/*' => Http::response([], 200),
        ]);

        $this->mock(TraktTvService::class, function ($mock) use ($dto) {
            $mock->shouldReceive('findWatchedShows')->andReturn(collect([$dto]));
        });

        $job = new SyncTraktTvShowsAndProgressJob($user);
        $job->handle(app(TraktTvService::class));

        $this->assertTrue($user->watchedEpisodes()->where('episode_id', $episode->id)->exists());
    }
}




