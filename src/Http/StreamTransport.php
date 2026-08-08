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

        // When the stream wrapper follows a redirect, $http_response_header holds
        // the status line and headers of EVERY hop concatenated in order. Only the
        // last hop describes the response actually returned here, so find the last
        // "HTTP/" status line and parse headers from that point on, discarding the
        // earlier hops' headers (e.g. a redirect's Location header).
        if (isset($http_response_header) && is_array($http_response_header)) {
            $lastStatusIndex = null;
            foreach ($http_response_header as $index => $line) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $matches)) {
                    $statusCode = (int) $matches[1];
                    $lastStatusIndex = $index;
                }
            }

            if ($lastStatusIndex !== null) {
                $count = count($http_response_header);
                for ($i = $lastStatusIndex + 1; $i < $count; $i++) {
                    $parts = explode(':', $http_response_header[$i], 2);
                    if (count($parts) === 2) {
                        $responseHeaders[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
        }

        return new Response($statusCode, $responseHeaders, (string) $body);
    }
}
