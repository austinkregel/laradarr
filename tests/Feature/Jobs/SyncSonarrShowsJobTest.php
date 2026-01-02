<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncSonarrShowsJob;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Show;
use App\Services\DTOs\Sonarr\EpisodeDTO;
use App\Services\DTOs\Sonarr\ShowDTO;
use App\Services\SonarrService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSonarrShowsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_show_season_and_episode(): void
    {
        $showDto = new ShowDTO(
            id: 1,
            title: 'Example Show',
            titleSlug: 'example-show',
            year: 2020,
            overview: 'Desc',
            seriesType: 'standard',
            path: '/shows/example',
            alternateTitles: [],
            images: [],
            seasons: [],
            imdbId: null,
            tvdbId: null,
            tmdbId: null,
            firstAired: CarbonImmutable::parse('2020-01-01T00:00:00Z'),
            statistics: ['sizeOnDisk' => 0],
        );

        $episodeDto = new EpisodeDTO(
            id: 100,
            seriesId: 1,
            seasonNumber: 1,
            episodeNumber: 1,
            title: 'Pilot',
            overview: 'Pilot desc',
            hasFile: false,
            runtime: 42,
            tvdbId: null,
            episodeFileId: null,
            airDateUtc: '2020-01-01T00:00:00Z',
            airDate: null,
        );

        $this->mock(SonarrService::class, function ($mock) use ($showDto, $episodeDto) {
            $mock->shouldReceive('getShows')->andReturn(collect([$showDto]));
            $mock->shouldReceive('getEpisodes')->andReturn(collect([$episodeDto]));
        });

        $job = new SyncSonarrShowsJob();
        $job->handle(app(SonarrService::class));

        $this->assertSame(1, Show::query()->count());
        $this->assertSame(1, Season::query()->count());
        $this->assertSame(1, Episode::query()->count());
        $this->assertSame('Pilot', Episode::first()->name);
    }
}






