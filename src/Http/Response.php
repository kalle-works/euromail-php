<?php

namespace EuroMail\Http;

final class Response
{
    public int $statusCode;
    /** @var array<string, string> lower-cased header names */
    public array $headers;
    public string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }
        $this->headers = $normalized;

        $this->body = $body;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
