<?php

namespace EuroMail\Http;

use EuroMail\Exceptions\TransportException;

final class StreamTransport implements TransportInterface
{
    private int $timeout;

    public function __construct(int $timeout = 15)
    {
        $this->timeout = $timeout;
    }

    public function send(Request $request): Response
    {
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $request->method,
                'header' => implode("\r\n", $headerLines),
                'content' => $request->body ?? '',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $errorMessage = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$errorMessage): bool {
            $errorMessage = $errstr;
            return true;
        });
        $body = @file_get_contents($request->url, false, $context);
        restore_error_handler();

        if ($body === false) {
            throw new TransportException('Stream transport error: ' . ($errorMessage ?? 'unknown error'));
        }

        $statusCode = 0;
        $responseHeaders = [];

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $index => $line) {
                if ($index === 0) {
                    if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $matches)) {
                        $statusCode = (int) $matches[1];
                    }
                    continue;
                }
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        return new Response($statusCode, $responseHeaders, (string) $body);
    }
}
