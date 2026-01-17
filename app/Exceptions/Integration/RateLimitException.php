<?php
declare(strict_types=1);

namespace App\Exceptions\Integration;

class RateLimitException extends ApiException
{
    public function __construct(
        string $service,
        string $method,
        string $url,
        ?int $status = 429,
        ?string $responseBody = null,
        public readonly ?int $retryAfterSeconds = null,
        string $message = 'Rate limit exceeded',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            service: $service,
            method: $method,
            url: $url,
            status: $status,
            responseBody: $responseBody,
            message: $message,
            previous: $previous,
        );
    }
}








