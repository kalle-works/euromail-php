<?php

namespace EuroMail\Http;

final class Request
{
    public string $method;
    public string $url;
    /** @var array<string, string> */
    public array $headers;
    public ?string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $method, string $url, array $headers = [], ?string $body = null)
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
    }
}
