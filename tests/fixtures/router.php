<?php

/**
 * Router fixture for PHP's built-in web server, used by TransportIntegrationTest
 * to exercise CurlTransport and StreamTransport against a real HTTP server
 * instead of a mock. Started via `php -S 127.0.0.1:<port> router.php`.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/ok') {
    header('X-Custom-Header: custom-value');
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(['data' => ['status' => 'ok']]);
    exit;
}

if ($path === '/error') {
    header('Content-Type: application/json');
    http_response_code(422);
    echo json_encode([
        'error' => [
            'type' => 'validation_error',
            'code' => 'bad_input',
            'message' => 'Invalid input',
        ],
    ]);
    exit;
}

if ($path === '/redirect') {
    header('Location: /ok');
    http_response_code(302);
    exit;
}

if ($path === '/echo') {
    $body = file_get_contents('php://input');
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name = str_replace('_', '-', substr($key, 5));
            $headers[$name] = $value;
        }
    }

    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'method' => $method,
        'body' => $body,
        'headers' => $headers,
    ]);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => ['message' => 'not found']]);
