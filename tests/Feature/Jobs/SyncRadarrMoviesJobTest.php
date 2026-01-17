<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncRadarrMoviesJob;
use App\Models\Movie;
use App\Services\DTOs\Radarr\MovieDTO;
use App\Services\RadarrService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncRadarrMoviesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_or_updates_movies(): void
    {
        $movieDto = new MovieDTO(
            id: 10,
            title: 'Example Movie',
            runtime: 120,
            movieFileId: 99,
            hasFile: true,
            alternateTitles: [['title' => 'Alt Title']],
            inCinemas: CarbonImmutable::parse('2024-01-01T00:00:00Z'),
            imdbId: 'tt1234567',
            added: CarbonImmutable::parse('2024-01-02T00:00:00Z'),
            path: '/movies/example',
        );

        $this->mock(RadarrService::class, function ($mock) use ($movieDto) {
            $mock->shouldReceive('getMovies')->andReturn(collect([$movieDto]));
        });

        $job = new SyncRadarrMoviesJob();
        $job->handle(app(RadarrService::class));

        $this->assertSame(1, Movie::query()->count());
        $this->assertSame('Example Movie', Movie::first()->name);
        $this->assertTrue(Movie::first()->is_available);
    }
}








