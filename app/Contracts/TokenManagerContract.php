<?php

declare(strict_types=1);

namespace App\Contracts;

interface TokenManagerContract
{
    /**
     * Get Trakt access token. If userId is provided, gets that user's token.
     * If null, attempts to get global/legacy token.
     */
    public function getTraktAccessToken(?int $userId = null): ?string;

    public function getTraktRefreshToken(?int $userId = null): ?string;

    public function storeTraktTokens(array $tokenResponse, ?int $userId = null): void;

    /** @return array{access_token?:string,refresh_token?:string,expires_in?:int,created_at?:int} */
    public function refreshTraktTokens(?int $userId = null): array;

    /**
     * Delete all Trakt tokens for a user.
     */
    public function deleteTraktTokens(int $userId): void;
}





