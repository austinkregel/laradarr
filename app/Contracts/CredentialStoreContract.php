<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Credential;
use Illuminate\Support\Carbon;

interface CredentialStoreContract
{
    public function get(string $service, string $key): ?string;

    public function set(
        string $service,
        string $key,
        ?string $value,
        bool $enabled = true,
        ?Carbon $expiresAt = null
    ): Credential;

    public function enable(string $service, string $key): void;

    public function disable(string $service, string $key): void;
}



