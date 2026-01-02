<?php
declare(strict_types=1);

namespace App\Contracts;

interface TokenManagerContract
{
    public function getTraktAccessToken(): ?string;

    public function getTraktRefreshToken(): ?string;

    public function storeTraktTokens(array $tokenResponse): void;

    /** @return array{access_token?:string,refresh_token?:string,expires_in?:int,created_at?:int} */
    public function refreshTraktTokens(): array;
}



