<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Credential;
use Illuminate\Support\Carbon;

interface CredentialStoreContract
{
    /**
     * Get a credential value. If userId is null, looks for global credentials.
     * If userId is provided, looks for that user's credential.
     */
    public function get(string $service, string $key, ?int $userId = null): ?string;

    /**
     * Set a credential value. If userId is null, stores as global credential.
     * If userId is provided, stores for that specific user.
     */
    public function set(
        string $service,
        string $key,
        ?string $value,
        bool $enabled = true,
        ?Carbon $expiresAt = null,
        ?int $userId = null
    ): Credential;

    public function enable(string $service, string $key, ?int $userId = null): void;

    public function disable(string $service, string $key, ?int $userId = null): void;

    /**
     * Delete credentials for a specific service/key/user combination.
     */
    public function delete(string $service, string $key, ?int $userId = null): void;
}





