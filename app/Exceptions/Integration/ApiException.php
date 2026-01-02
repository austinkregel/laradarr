<?php
declare(strict_types=1);

namespace App\Exceptions\Integration;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $service,
        public readonly string $method,
        public readonly string $url,
        public readonly ?int $status = null,
        public readonly ?string $responseBody = null,
        string $message = 'Integration API error',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}






