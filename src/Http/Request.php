<?php

namespace EuroMail\Http;

final class Request
{
    public string $method;
    public string $url;
    public array $headers;
    public ?string $body;

    public function __construct(string $method, string $url, array $headers = [], ?string $body = null)
    {
        $this->method = $method;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
    }
}
