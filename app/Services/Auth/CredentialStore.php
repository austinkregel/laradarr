<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\CredentialStoreContract;
use App\Models\Credential;
use Illuminate\Support\Carbon;

class CredentialStore implements CredentialStoreContract
{
    public function get(string $service, string $key): ?string
    {
        /** @var Credential|null $cred */
        $cred = Credential::query()
            ->where('service', $service)
            ->where('key', $key)
            ->where('is_enabled', true)
            ->first();

        if (!$cred) {
            return null;
        }

        // If expired, treat as missing.
        if ($cred->expires_at instanceof Carbon && $cred->expires_at->isPast()) {
            return null;
        }

        $cred->forceFill(['last_used_at' => now()])->save();

        return is_string($cred->value) && $cred->value !== '' ? $cred->value : null;
    }

    public function set(
        string $service,
        string $key,
        ?string $value,
        bool $enabled = true,
        ?Carbon $expiresAt = null
    ): Credential {
        return Credential::query()->updateOrCreate(
            ['service' => $service, 'key' => $key],
            [
                'value' => $value,
                'is_enabled' => $enabled,
                'expires_at' => $expiresAt,
            ]
        );
    }

    public function enable(string $service, string $key): void
    {
        Credential::query()
            ->where('service', $service)
            ->where('key', $key)
            ->update(['is_enabled' => true]);
    }

    public function disable(string $service, string $key): void
    {
        Credential::query()
            ->where('service', $service)
            ->where('key', $key)
            ->update(['is_enabled' => false]);
    }
}



