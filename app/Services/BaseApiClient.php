<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Integration\ApiException;
use App\Exceptions\Integration\AuthenticationException;
use App\Exceptions\Integration\InvalidResponseException;
use App\Exceptions\Integration\RateLimitException;
use App\Exceptions\Integration\ServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

abstract class BaseApiClient
{
    /**
     * config/services.php key, e.g. "sonarr", "trakt".
     */
    abstract protected function serviceKey(): string;

    protected function baseUrl(): string
    {
        /** @var \App\Contracts\CredentialStoreContract $store */
        $store = app(\App\Contracts\CredentialStoreContract::class);
        $url = $store->get($this->serviceKey(), 'url');
        
        return $url ?? (string) config("services.{$this->serviceKey()}.url", '');
    }

    /**
     * Extra base URL for services that don't use `url` (e.g. Trakt).
     */
    protected function baseUrlOverride(): ?string
    {
        return null;
    }

    protected function defaultHeaders(): array
    {
        return [];
    }

    /**
     * Override for APIs that do not speak JSON (e.g. Plex XML).
     */
    protected function expectsJson(): bool
    {
        return true;
    }

    protected function acceptHeader(): string
    {
        return $this->expectsJson() ? 'application/json' : 'application/xml';
    }

    protected function defaultQuery(): array
    {
        return [];
    }

    protected function timeoutSeconds(): int
    {
        return (int) config("services.{$this->serviceKey()}.timeout", 15);
    }

    protected function retryTimes(): int
    {
        return (int) config("services.{$this->serviceKey()}.retry.times", 3);
    }

    protected function retrySleepMs(): int
    {
        return (int) config("services.{$this->serviceKey()}.retry.sleep_ms", 250);
    }

    protected function rateLimitPerMinute(): ?int
    {
        $val = config("services.{$this->serviceKey()}.rate_limit.per_minute");
        return $val === null ? null : (int) $val;
    }

    protected function rateLimitKey(): string
    {
        return "integration:{$this->serviceKey()}";
    }

    protected function rateLimitDecaySeconds(): int
    {
        return 60;
    }

    /**
     * Perform a JSON request and return decoded JSON.
     *
     * @throws ApiException
     */
    protected function requestJson(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
        ?callable $validate = null,
    ): array {
        $response = $this->request($method, $path, $query, $body, $headers);
        $json = $response->json();
        if (!is_array($json)) {
            throw new InvalidResponseException(
                service: $this->serviceKey(),
                method: $method,
                url: (string) $response->effectiveUri(),
                status: $response->status(),
                responseBody: $response->body(),
                message: 'Expected JSON array response',
            );
        }

        if ($validate !== null) {
            try {
                $validate($json);
            } catch (Throwable $e) {
                throw new InvalidResponseException(
                    service: $this->serviceKey(),
                    method: $method,
                    url: (string) $response->effectiveUri(),
                    status: $response->status(),
                    responseBody: $response->body(),
                    message: $e->getMessage(),
                    previous: $e,
                );
            }
        }

        return $json;
    }

    /**
     * Perform a request and return the response.
     *
     * @throws ApiException
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
    ): Response {
        $this->enforceRateLimit();

        $baseUrl = $this->baseUrlOverride() ?? $this->baseUrl();
        if ($baseUrl === '') {
            throw new ApiException(
                service: $this->serviceKey(),
                method: $method,
                url: $path,
                status: null,
                responseBody: null,
                message: 'Missing base URL configuration',
            );
        }

        $start = hrtime(true);
        $attempt = 0;


        try {
            $pending = Http::baseUrl(rtrim($baseUrl, '/'))
                ->timeout($this->timeoutSeconds())
                ->accept($this->acceptHeader())
                ->withHeaders(array_merge($this->defaultHeaders(), $headers))
                ->withQueryParameters(array_merge($this->defaultQuery(), $query))
                ->retry(
                    $this->retryTimes(),
                    $this->retrySleepMs(),
                    function ($exception, $request) use (&$attempt) {
                        $attempt++;

                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        if ($exception instanceof RequestException) {
                            $status = $exception->response?->status();
                            return in_array($status, [408, 429], true) || ($status !== null && $status >= 500);
                        }

                        return false;
                    },
                    throw: false,
                );

            if ($this->expectsJson()) {
                $pending = $pending->asJson();
            }

            $response = $pending->send($method, ltrim($path, '/'), $body === [] ? [] : ['json' => $body]);

            $this->throwIfError($response, $method);

            $this->logSuccess($method, $response, $start, $attempt);

            return $response;
        } catch (ApiException $e) {
            $this->logFailure($method, $path, $start, $attempt, $e);
            throw $e;
        } catch (Throwable $e) {
            $this->logFailure($method, $path, $start, $attempt, $e);
            throw new ApiException(
                service: $this->serviceKey(),
                method: $method,
                url: $this->fullUrl($baseUrl, $path),
                status: null,
                responseBody: null,
                message: $e->getMessage(),
                previous: $e,
            );
        }
    }

    protected function ensureKeys(array $data, array $requiredKeys, string $context): void
    {
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \RuntimeException("Missing required key '{$key}' ({$context})");
            }
        }
    }

    protected function enforceRateLimit(): void
    {
        $maxPerMinute = $this->rateLimitPerMinute();
        if ($maxPerMinute === null || $maxPerMinute <= 0) {
            return;
        }

        $key = $this->rateLimitKey();
        if (RateLimiter::tooManyAttempts($key, $maxPerMinute)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw new RateLimitException(
                service: $this->serviceKey(),
                method: 'RATE_LIMIT',
                url: $key,
                status: 429,
                responseBody: null,
                retryAfterSeconds: $retryAfter,
                message: "Rate limit exceeded for {$this->serviceKey()}",
            );
        }

        RateLimiter::hit($key, $this->rateLimitDecaySeconds());
    }

    protected function throwIfError(Response $response, string $method): void
    {
        $status = $response->status();

        if ($status < 400) {
            return;
        }

        $url = (string) $response->effectiveUri();
        $body = $response->body();

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException(
                service: $this->serviceKey(),
                method: $method,
                url: $url,
                status: $status,
                responseBody: $body,
                message: "Authentication failed ({$this->serviceKey()})",
            );
        }

        if ($status === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 0);
            throw new RateLimitException(
                service: $this->serviceKey(),
                method: $method,
                url: $url,
                status: $status,
                responseBody: $body,
                retryAfterSeconds: $retryAfter > 0 ? $retryAfter : null,
                message: "Rate limit response from {$this->serviceKey()}",
            );
        }

        if ($status >= 500) {
            throw new ServiceUnavailableException(
                service: $this->serviceKey(),
                method: $method,
                url: $url,
                status: $status,
                responseBody: $body,
                message: "Upstream service error ({$this->serviceKey()})",
            );
        }

        throw new ApiException(
            service: $this->serviceKey(),
            method: $method,
            url: $url,
            status: $status,
            responseBody: $body,
            message: "HTTP {$status} from {$this->serviceKey()}",
        );
    }

    protected function logSuccess(string $method, Response $response, int $startNs, int $attempt): void
    {
        $durationMs = (int) ((hrtime(true) - $startNs) / 1_000_000);

        Log::info('integration.request', [
            'service' => $this->serviceKey(),
            'method' => $method,
            'url' => $response->effectiveUri(),
            'status' => $response->status(),
            'duration_ms' => $durationMs,
            'attempt' => $attempt,
        ]);
    }

    protected function logFailure(string $method, string $path, int $startNs, int $attempt, Throwable $e): void
    {
        $durationMs = (int) ((hrtime(true) - $startNs) / 1_000_000);

        Log::warning('integration.request_failed', [
            'service' => $this->serviceKey(),
            'method' => $method,
            'path' => $path,
            'duration_ms' => $durationMs,
            'attempt' => $attempt,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    protected function fullUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}


