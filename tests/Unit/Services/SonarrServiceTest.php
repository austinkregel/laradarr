<?php

namespace Tests\Unit\Services;

use App\Services\Auth\CredentialStore;
use App\Services\SonarrService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class SonarrServiceTest extends TestCase
{
    private function configureService(): void
    {
        config()->set('services.sonarr.url', 'http://sonarr.test');
        config()->set('services.sonarr.api_key', 'abc');

        // Mock CredentialStore to return null so tests use config values
        $this->mock(CredentialStore::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn(null);
        });
    }

    public function test_get_shows_maps_to_dtos(): void
    {
        $this->configureService();

        Http::fake([
            'http://sonarr.test/api/v3/series*' => Http::response([
                [
                    'id' => 1,
                    'title' => 'Example Show',
                    'titleSlug' => 'example-show',
                ],
            ], 200),
        ]);

        $service = app(SonarrService::class);
        $shows = $service->getShows();

        $this->assertCount(1, $shows);
        $this->assertSame(1, $shows->first()->id);
        $this->assertSame('Example Show', $shows->first()->title);
    }

    public function test_push_release_with_magnet(): void
    {
        $this->configureService();

        Http::fake([
            'http://sonarr.test/api/v3/release/push*' => Http::response(['id' => 10], 201),
        ]);

        $service = app(SonarrService::class);
        $response = $service->pushRelease(
            'Example Episode',
            'magnet:?xt=urn:btih:123',
            null,
            '2025-12-25T00:00:00Z'
        );

        $this->assertSame(['id' => 10], $response);
    }

    public function test_push_release_with_download_url(): void
    {
        $this->configureService();

        Http::fake([
            'http://sonarr.test/api/v3/release/push*' => Http::response(['id' => 11], 201),
        ]);

        $service = app(SonarrService::class);
        $response = $service->pushRelease(
            'Example Episode',
            null,
            'https://example.com/episode.torrent'
        );

        $this->assertSame(['id' => 11], $response);
    }

    public function test_push_release_requires_a_source(): void
    {
        $this->configureService();

        $this->expectException(InvalidArgumentException::class);

        app(SonarrService::class)->pushRelease('No Source', null, null);
    }

    public function test_search_episodes_triggers_command(): void
    {
        $this->configureService();

        Http::fake([
            'http://sonarr.test/api/v3/command*' => Http::response(['id' => 42], 201),
        ]);

        $response = app(SonarrService::class)->searchEpisodes([123, 456]);

        $this->assertSame(['id' => 42], $response);
    }

    public function test_search_episodes_requires_ids(): void
    {
        $this->configureService();

        $this->expectException(InvalidArgumentException::class);

        app(SonarrService::class)->searchEpisodes([]);
    }

    public function test_get_command_status_returns_data(): void
    {
        $this->configureService();

        Http::fake([
            'http://sonarr.test/api/v3/command/99*' => Http::response(['id' => 99, 'status' => 'completed'], 200),
        ]);

        $response = app(SonarrService::class)->getCommandStatus(99);

        $this->assertSame(['id' => 99, 'status' => 'completed'], $response);
    }

    public function test_search_series_maps_to_dtos(): void
    {
        $this->configureService();

        Http::fake([
            'http://sonarr.test/api/v3/series*' => Http::response([
                [
                    'id' => 5,
                    'title' => 'Search Show',
                    'titleSlug' => 'search-show',
                ],
            ], 200),
        ]);

        $shows = app(SonarrService::class)->searchSeries('Search');

        $this->assertCount(1, $shows);
        $this->assertSame('Search Show', $shows->first()->title);
    }
}




