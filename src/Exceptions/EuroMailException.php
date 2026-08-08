<?php

namespace EuroMail\Exceptions;

use EuroMail\Http\Response;

class EuroMailException extends \Exception
{
    protected ?int $statusCode;
    protected ?string $errorType;
    protected ?string $errorCode;
    protected ?string $requestId;

    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorType = null,
        ?string $errorCode = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
        $this->errorCode = $errorCode;
        $this->requestId = $requestId;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function isRetryable(): bool
    {
        if ($this instanceof TransportException) {
            return true;
        }

        if ($this->statusCode === 429) {
            return true;
        }

        if ($this->statusCode !== null && $this->statusCode >= 500 && $this->statusCode < 600) {
            return true;
        }

        return false;
    }

    public static function fromResponse(Response $response): self
    {
        $statusCode = $response->statusCode;
        $requestId = $response->getHeader('x-request-id');

        $errorType = null;
        $errorCode = null;
        $message = null;

        $decoded = json_decode($response->body, true);
        if (is_array($decoded)) {
            $errorBody = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : $decoded;
            $errorType = $errorBody['type'] ?? null;
            $errorCode = $errorBody['code'] ?? null;
            $message = $errorBody['message'] ?? null;
        }

        if ($message === null || $message === '') {
            $message = self::statusText($statusCode);
        }

        if ($statusCode === 422 || $errorType === 'validation_error') {
            return new ValidationException($message, $statusCode, $errorType, $errorCode, $requestId);
        }

        if ($statusCode === 401 || $statusCode === 403) {
            return new AuthenticationException($message, $statusCode, $errorType, $errorCode, $requestId);
        }

        if ($statusCode === 404) {
            return new NotFoundException($message, $statusCode, $errorType, $errorCode, $requestId);
        }

        if ($statusCode === 409) {
            return new ConflictException($message, $statusCode, $errorType, $errorCode, $requestId);
        }

        if ($statusCode === 429) {
            $retryAfter = self::parseRetryAfter($response->getHeader('retry-after'));
            return new RateLimitException($message, $retryAfter, $statusCode, $errorType, $errorCode, $requestId);
        }

        if ($statusCode >= 500 && $statusCode < 600) {
            return new ServerException($message, $statusCode, $errorType, $errorCode, $requestId);
        }

        return new self($message, $statusCode, $errorType, $errorCode, $requestId);
    }

    private static function statusText(int $statusCode): string
    {
        $map = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $map[$statusCode] ?? ('HTTP Error ' . $statusCode);
    }

    private static function parseRetryAfter(?string $header): ?int
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $trimmed = trim($header);
        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return max(0, $timestamp - time());
        }

        return null;
    }
}
