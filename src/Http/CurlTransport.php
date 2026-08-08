<?php

namespace EuroMail\Http;

use EuroMail\Exceptions\TransportException;

final class CurlTransport implements TransportInterface
{
    private int $timeout;

    public function __construct(int $timeout = 15)
    {
        $this->timeout = $timeout;
    }

    public function send(Request $request): Response
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new TransportException('Failed to initialize cURL handle.');
        }

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            // When a redirect is followed, this callback fires once per hop. A new
            // "HTTP/" status line marks the start of a new hop's headers, so reset
            // what has been collected so far and keep only the final hop's headers
            // (e.g. discard a redirect's Location header).
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $trimmed = trim($headerLine);

                if ($trimmed === '') {
                    return $length;
                }

                if (preg_match('#^HTTP/\S+\s+\d+#', $trimmed)) {
                    $responseHeaders = [];
                    return $length;
                }

                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return $length;
            },
        ]);

        if ($request->body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->body);
        }

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransportException('cURL transport error: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new Response($statusCode, $responseHeaders, (string) $body);
    }
}
