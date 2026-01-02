<?php
declare(strict_types=1);

namespace App\Exceptions\Integration;

use Throwable;

class InvalidResponseException extends ApiException
{
    public function __construct(
        string $service,
        string $method,
        string $url,
        ?int $status = null,
        ?string $responseBody = null,
        string $message = 'Invalid integration response',
        ?Throwable $previous = null,
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






