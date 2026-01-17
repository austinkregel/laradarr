<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\CredentialStoreContract;
use App\Contracts\TokenManagerContract;
use App\Exceptions\Integration\AuthenticationException;
use App\Exceptions\Integration\InvalidResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TokenManager implements TokenManagerContract
{
    private const TRAKT_CACHE_KEY_PREFIX = 'integration:trakt:tokens';
    private const TRAKT_REFRESH_LOCK_PREFIX = 'integration:trakt:refresh_lock';

    /**
     * Returns the current access token, refreshing if it's expired and we have a refresh token.
     */
    public function getTraktAccessToken(?int $userId = null): ?string
    {
        $tokens = $this->getTraktTokens($userId);
        $store = app(CredentialStoreContract::class);

        // Try cache first, then credential store, then config (for global/legacy)
        $access = $tokens['access_token']
            ?? $store->get('trakt', 'access_token', $userId)
            ?? ($userId === null ? config('services.trakt.access_token') : null);

        if (!is_string($access) || $access === '') {
            return null;
        }

        $expiresAt = $tokens['expires_at'] ?? null;
        if ($expiresAt !== null && is_int($expiresAt) && $expiresAt <= now()->addSeconds(30)->timestamp) {
            // Token is expired/near-expiry; try refresh once.
            $refreshed = $this->refreshTraktTokens($userId);
            return $refreshed['access_token'] ?? $access;
        }

        return $access;
    }

    public function getTraktRefreshToken(?int $userId = null): ?string
    {
        $tokens = $this->getTraktTokens($userId);
        $store = app(CredentialStoreContract::class);

        $refresh = $tokens['refresh_token']
            ?? $store->get('trakt', 'refresh_token', $userId)
            ?? ($userId === null ? config('services.trakt.refresh_token') : null);

        return is_string($refresh) && $refresh !== '' ? $refresh : null;
    }

    /**
     * Store tokens returned from Trakt OAuth endpoints.
     */
    public function storeTraktTokens(array $tokenResponse, ?int $userId = null): void
    {
        $accessToken = $tokenResponse['access_token'] ?? null;
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $expiresIn = $tokenResponse['expires_in'] ?? null;
        $createdAt = $tokenResponse['created_at'] ?? time();

        if (!is_string($accessToken) || $accessToken === '') {
            throw new InvalidResponseException(
                service: 'trakt',
                method: 'STORE_TOKENS',
                url: 'cache',
                status: null,
                responseBody: json_encode($tokenResponse),
                message: 'Missing access_token from token response',
            );
        }

        $payload = [
            'access_token' => $accessToken,
            'refresh_token' => is_string($refreshToken) ? $refreshToken : null,
            'created_at' => is_int($createdAt) ? $createdAt : time(),
            'expires_in' => is_int($expiresIn) ? $expiresIn : null,
        ];

        if (is_int($expiresIn) && $expiresIn > 0) {
            // Refresh 60s early to avoid edge failures.
            $payload['expires_at'] = ($payload['created_at'] + $expiresIn) - 60;
        }

        $store = app(CredentialStoreContract::class);
        $store->set(
            'trakt',
            'access_token',
            $payload['access_token'],
            enabled: true,
            expiresAt: isset($payload['expires_at']) ? now()->setTimestamp((int) $payload['expires_at']) : null,
            userId: $userId
        );

        if (is_string($payload['refresh_token'] ?? null) && $payload['refresh_token'] !== '') {
            $store->set(
                'trakt',
                'refresh_token',
                $payload['refresh_token'],
                enabled: true,
                expiresAt: null,
                userId: $userId
            );
        }

        Cache::forever($this->getCacheKey($userId), encrypt(json_encode($payload)));
    }

    /**
     * Refresh Trakt tokens using the stored refresh token.
     *
     * @return array{access_token?:string,refresh_token?:string,expires_in?:int,created_at?:int}
     */
    public function refreshTraktTokens(?int $userId = null): array
    {
        $lock = Cache::lock($this->getLockKey($userId), 30);

        return $lock->block(10, function () use ($userId) {
            $refreshToken = $this->getTraktRefreshToken($userId);
            if ($refreshToken === null) {
                throw new AuthenticationException(
                    service: 'trakt',
                    method: 'POST',
                    url: '/oauth/token',
                    status: 401,
                    responseBody: null,
                    message: 'Missing Trakt refresh token',
                );
            }

            $baseUrl = (string) config('services.trakt.base_url', 'https://api.trakt.tv');
            $clientId = (string) config('services.trakt.client_id');
            $clientSecret = (string) config('services.trakt.client_secret');

            try {
                $response = Http::baseUrl(rtrim($baseUrl, '/'))
                    ->timeout((int) config('services.trakt.timeout', 15))
                    ->acceptJson()
                    ->asJson()
                    ->post('/oauth/token', [
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'refresh_token' => $refreshToken,
                        'grant_type' => 'refresh_token',
                    ]);

            } catch (Throwable $e) {
                throw new AuthenticationException(
                    service: 'trakt',
                    method: 'POST',
                    url: $baseUrl . '/oauth/token',
                    status: null,
                    responseBody: null,
                    message: $e->getMessage(),
                    previous: $e,
                );
            }

            if ($response->status() >= 400) {
                throw new AuthenticationException(
                    service: 'trakt',
                    method: 'POST',
                    url: (string) $response->effectiveUri(),
                    status: $response->status(),
                    responseBody: $response->body(),
                    message: 'Failed to refresh Trakt access token: HTTP '.$response->status().' '.$response->body(),
                );
            }

            $json = $response->json();
            if (!is_array($json)) {
                throw new InvalidResponseException(
                    service: 'trakt',
                    method: 'POST',
                    url: (string) $response->effectiveUri(),
                    status: $response->status(),
                    responseBody: $response->body(),
                    message: 'Invalid refresh token response',
                );
            }

            $this->storeTraktTokens($json, $userId);

            return $json;
        });
    }

    /**
     * Delete all Trakt tokens for a user.
     */
    public function deleteTraktTokens(int $userId): void
    {
        $store = app(CredentialStoreContract::class);
        $store->delete('trakt', 'access_token', $userId);
        $store->delete('trakt', 'refresh_token', $userId);

        Cache::forget($this->getCacheKey($userId));
    }

    /**
     * @return array{access_token?:string,refresh_token?:string,expires_at?:int,created_at?:int,expires_in?:int}
     */
    private function getTraktTokens(?int $userId): array
    {
        $encrypted = Cache::get($this->getCacheKey($userId));
        if (!is_string($encrypted) || $encrypted === '') {
            return [];
        }

        try {
            $decoded = decrypt($encrypted);
            $arr = json_decode($decoded, true);
            return is_array($arr) ? $arr : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function getCacheKey(?int $userId): string
    {
        return $userId !== null
            ? self::TRAKT_CACHE_KEY_PREFIX . ':user:' . $userId
            : self::TRAKT_CACHE_KEY_PREFIX . ':global';
    }

    private function getLockKey(?int $userId): string
    {
        return $userId !== null
            ? self::TRAKT_REFRESH_LOCK_PREFIX . ':user:' . $userId
            : self::TRAKT_REFRESH_LOCK_PREFIX . ':global';
    }
}
