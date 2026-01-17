<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\CredentialStoreContract;
use App\Models\Credential;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CredentialStore implements CredentialStoreContract
{
    public function get(string $service, string $key, ?int $userId = null): ?string
    {
        /** @var Credential|null $cred */
        $cred = $this->buildQuery($service, $key, $userId)
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
        ?Carbon $expiresAt = null,
        ?int $userId = null
    ): Credential {
        return Credential::query()->updateOrCreate(
            [
                'service' => $service,
                'key' => $key,
                'user_id' => $userId,
            ],
            [
                'value' => $value,
                'is_enabled' => $enabled,
                'expires_at' => $expiresAt,
            ]
        );
    }

    public function enable(string $service, string $key, ?int $userId = null): void
    {
        $this->buildQuery($service, $key, $userId)->update(['is_enabled' => true]);
    }

    public function disable(string $service, string $key, ?int $userId = null): void
    {
        $this->buildQuery($service, $key, $userId)->update(['is_enabled' => false]);
    }

    public function delete(string $service, string $key, ?int $userId = null): void
    {
        $this->buildQuery($service, $key, $userId)->delete();
    }

    /**
     * Build a query filtered by service, key, and user_id.
     *
     * @return Builder<Credential>
     */
    private function buildQuery(string $service, string $key, ?int $userId): Builder
    {
        $query = Credential::query()
            ->where('service', $service)
            ->where('key', $key);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        return $query;
    }
}



