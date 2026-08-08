<?php

namespace EuroMail\Tests;

use EuroMail\Exceptions\AuthenticationException;
use EuroMail\Exceptions\ConflictException;
use EuroMail\Exceptions\EuroMailException;
use EuroMail\Exceptions\NotFoundException;
use EuroMail\Exceptions\RateLimitException;
use EuroMail\Exceptions\ServerException;
use EuroMail\Exceptions\TransportException;
use EuroMail\Exceptions\ValidationException;
use EuroMail\Http\Response;
use PHPUnit\Framework\TestCase;

final class ErrorMappingTest extends TestCase
{
    /**
     * @dataProvider statusToExceptionProvider
     */
    public function testStatusCodeMapsToCorrectExceptionClass(int $statusCode, string $expectedClass): void
    {
        $body = json_encode(['error' => ['type' => 'error', 'code' => 'oops', 'message' => 'Something went wrong']]);
        $response = new Response($statusCode, [], $body);

        $exception = EuroMailException::fromResponse($response);

        $this->assertInstanceOf($expectedClass, $exception);
        $this->assertSame($statusCode, $exception->getStatusCode());
    }

    public function statusToExceptionProvider(): array
    {
        return [
            '401 -> AuthenticationException' => [401, AuthenticationException::class],
            '403 -> AuthenticationException' => [403, AuthenticationException::class],
            '404 -> NotFoundException' => [404, NotFoundException::class],
            '409 -> ConflictException' => [409, ConflictException::class],
            '422 -> ValidationException' => [422, ValidationException::class],
            '429 -> RateLimitException' => [429, RateLimitException::class],
            '500 -> ServerException' => [500, ServerException::class],
            '502 -> ServerException' => [502, ServerException::class],
            '503 -> ServerException' => [503, ServerException::class],
        ];
    }

    public function testValidationErrorTypeMapsToValidationExceptionRegardlessOfStatus(): void
    {
        $body = json_encode(['error' => ['type' => 'validation_error', 'code' => 'bad_field', 'message' => 'Invalid field']]);
        $response = new Response(400, [], $body);

        $exception = EuroMailException::fromResponse($response);

        $this->assertInstanceOf(ValidationException::class, $exception);
    }

    public function testUnmappedStatusCodeFallsBackToBaseException(): void
    {
        $response = new Response(418, [], json_encode(['error' => ['message' => "I'm a teapot"]]));

        $exception = EuroMailException::fromResponse($response);

        $this->assertSame(EuroMailException::class, get_class($exception));
    }

    public function testErrorEnvelopeIsParsedForTypeCodeAndMessage(): void
    {
        $body = json_encode(['error' => ['type' => 'not_found', 'code' => 'email_not_found', 'message' => 'No such email']]);
        $response = new Response(404, [], $body);

        $exception = EuroMailException::fromResponse($response);

        $this->assertSame('not_found', $exception->getErrorType());
        $this->assertSame('email_not_found', $exception->getErrorCode());
        $this->assertSame('No such email', $exception->getMessage());
    }

    public function testFlatErrorBodyWithoutErrorWrapperIsTolerated(): void
    {
        $body = json_encode(['code' => 'flat_code', 'message' => 'flat message', 'type' => 'flat_type']);
        $response = new Response(400, [], $body);

        $exception = EuroMailException::fromResponse($response);

        $this->assertSame('flat_code', $exception->getErrorCode());
        $this->assertSame('flat message', $exception->getMessage());
        $this->assertSame('flat_type', $exception->getErrorType());
    }

    public function testNonJsonBodyFallsBackToStatusText(): void
    {
        $response = new Response(500, [], 'Internal Server Error (plain text, not JSON)');

        $exception = EuroMailException::fromResponse($response);

        $this->assertInstanceOf(ServerException::class, $exception);
        $this->assertSame('Internal Server Error', $exception->getMessage());
        $this->assertNull($exception->getErrorType());
        $this->assertNull($exception->getErrorCode());
    }

    public function testEmptyBodyFallsBackToStatusText(): void
    {
        $response = new Response(404, [], '');

        $exception = EuroMailException::fromResponse($response);

        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function testRequestIdIsCapturedFromHeaderWhenPresent(): void
    {
        $response = new Response(500, ['X-Request-Id' => 'req_abc123'], '{}');

        $exception = EuroMailException::fromResponse($response);

        $this->assertSame('req_abc123', $exception->getRequestId());
    }

    public function testRequestIdIsNullWhenHeaderMissing(): void
    {
        $response = new Response(500, [], '{}');

        $exception = EuroMailException::fromResponse($response);

        $this->assertNull($exception->getRequestId());
    }

    public function testRetryAfterIsParsedAsIntegerSecondsFromHeader(): void
    {
        $response = new Response(429, ['Retry-After' => '120'], '{}');

        $exception = EuroMailException::fromResponse($response);

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertSame(120, $exception->getRetryAfter());
    }

    public function testRetryAfterIsNullWhenHeaderMissing(): void
    {
        $response = new Response(429, [], '{}');

        $exception = EuroMailException::fromResponse($response);

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertNull($exception->getRetryAfter());
    }

    /**
     * @dataProvider retryableMatrixProvider
     */
    public function testIsRetryableMatrix(EuroMailException $exception, bool $expected): void
    {
        $this->assertSame($expected, $exception->isRetryable());
    }

    public function retryableMatrixProvider(): array
    {
        return [
            'TransportException is retryable' => [new TransportException('network down'), true],
            'RateLimitException (429) is retryable' => [new RateLimitException('rate limited', null, 429), true],
            'ServerException (500) is retryable' => [new ServerException('boom', 500), true],
            'ServerException (503) is retryable' => [new ServerException('boom', 503), true],
            'ValidationException (422) is not retryable' => [new ValidationException('bad input', 422), false],
            'AuthenticationException (401) is not retryable' => [new AuthenticationException('nope', 401), false],
            'NotFoundException (404) is not retryable' => [new NotFoundException('missing', 404), false],
            'ConflictException (409) is not retryable' => [new ConflictException('conflict', 409), false],
        ];
    }
}
