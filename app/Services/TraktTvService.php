<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TraktTvService
{
    /**
     * First create a device token. You'll use the returned device_code to exchange for an access token.
     * You'll need to click the provided link, and enter the generated code in the Trakt website.
     */
    public function createDeviceToken(): array
    {
        return Http::post('https://api.trakt.tv/oauth/device/code', [
            'client_id' => config('services.trakt.client_id'),
        ])->json();
    }

    /**
     * Once you've finished with the Trakt website, you can exchange the code for an access token.
     * This access token will be used to authenticate all future requests. Save the response somewhere.
     */
    public function exchangeForAccessToken(string $code): array
    {
        return Http::post('https://api.trakt.tv/oauth/device/token', [
            'code' => $code,
            'client_id' => config('services.trakt.client_id'),
            'client_secret' => config('services.trakt.client_secret'),
        ])->json();
    }


    public function findWatchedShows(): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.env('TRAKT_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id')
        ])
            ->get('https://api.trakt.tv/sync/watched/shows');

        return array_map(function($showFromTrakt) {
            return [
                'id' => $showFromTrakt['show']['ids']['trakt'],
                'ids' => $showFromTrakt['show']['ids'],
                'name' => $showFromTrakt['show']['title'],
                'release_year' => $showFromTrakt['show']['year'],
                'episodes' => array_reduce($showFromTrakt['seasons'], function ($carry, $season) {
                    return array_reduce($season['episodes'], function ($seasonCarry, $episode) use ($season) {
                        $key = 'S'.
                            str_pad($season['number'], 2, '0', STR_PAD_LEFT).
                            'E'.
                            str_pad($episode['number'], 2, '0', STR_PAD_LEFT);


                        $seasonCarry[] = [
                            'key' => $key,
                            'season' => $season['number'],
                            'number' => $episode['number'],
                            'watched_at' => $episode['last_watched_at'],
                        ];
                        return $seasonCarry;
                    }, $carry);
                }, []),
            ];
        }, $response->json());
    }

    public function findShowsOnList(string $user, string $list): array
    {
        return cache()->remember("trakt-list-$user-$list", now()->addMinutes(30), function () use ($user, $list) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('TRAKT_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
                'trakt-api-version' => '2',
                'trakt-api-key' => config('services.trakt.client_id')
            ])
                ->get("https://api.trakt.tv/users/$user/lists/$list/items/shows");

            return array_map(function ($showFromTrakt) {
                return $showFromTrakt;
            }, $response->json());
        });
    }
    public function fetchUserLists(string $user): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TRAKT_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id')
        ])
            ->get("https://api.trakt.tv/users/$user/lists");

        return array_map(function($list) {
            return [
                'name' => $list['name'],
                'description' => $list['description'],
                'ids' => $list['ids'],
            ];
        }, $response->json());
    }

    public function createList(string $name, array $shows): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TRAKT_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id')
        ])
            ->post("https://api.trakt.tv/users/me/lists", [
                'name' => $name,
                'description' => 'List created by the Laradarr app',
                'privacy' => 'private',
                'show_ids' => $shows,
            ]);

        return $response->json();
    }

    public function addShowsToList(string $user, string $list, array $showIds): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TRAKT_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id')
        ])
            ->post("https://api.trakt.tv/users/$user/lists/$list/items", [
                'shows' => array_map(function ($showId) {
                    return [
                        'ids' => [
                            'trakt' => $showId,
                        ],
                    ];
                }, $showIds),
            ]);

        return $response->json();
    }
}
