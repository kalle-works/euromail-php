<?php

namespace EuroMail\Exceptions;

class RateLimitException extends EuroMailException
{
    private ?int $retryAfter;

    public function __construct(
        string $message,
        ?int $retryAfter = null,
        ?int $statusCode = null,
        ?string $errorType = null,
        ?string $errorCode = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $errorType, $errorCode, $requestId, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
