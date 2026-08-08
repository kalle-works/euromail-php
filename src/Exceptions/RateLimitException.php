<?php

namespace EuroMail\Exceptions;

class RateLimitException extends EuroMailException
{
    public function __construct(
        string $message,
        ?int $retryAfter = null,
        ?int $statusCode = null,
        ?string $errorType = null,
        ?string $errorCode = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $errorType, $errorCode, $requestId, $retryAfter, $previous);
    }
}
