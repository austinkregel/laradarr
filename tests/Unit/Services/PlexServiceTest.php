<?php

namespace Tests\Unit\Services;

use App\Contracts\CredentialStoreContract;
use App\Contracts\PlexServiceContract;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlexServiceTest extends TestCase
{
    public function test_get_libraries_parses_xml(): void
    {
        config()->set('services.plex.url', 'http://plex.test');
        config()->set('services.plex.token', 'token');

        // Mock CredentialStoreContract to return null so tests use config values
        $this->mock(CredentialStoreContract::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn(null);
        });

        // Plex returns JSON when Accept: application/json header is sent
        $json = [
            'MediaContainer' => [
                'Directory' => [
                    [
                        'key' => '1',
                        'title' => 'Movies',
                        'type' => 'movie',
                        'ratingKey' => '1',
                        'guid' => 'plex://movie/1',
                    ],
                ],
            ],
        ];

        Http::fake([
            'http://plex.test/library/sections*' => Http::response(json_encode($json), 200, ['Content-Type' => 'application/json']),
        ]);

        $service = app(PlexServiceContract::class);
        $libs = $service->getLibraries();

        $this->assertCount(1, $libs);
        $this->assertSame('Movies', $libs->first()->title);
    }
}




