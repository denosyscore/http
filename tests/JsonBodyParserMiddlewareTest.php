<?php

declare(strict_types=1);

namespace Denosys\Http\Tests;

use Denosys\Http\Middleware\JsonBodyParserMiddleware;
use Denosys\Http\Request;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonBodyParserMiddlewareTest extends TestCase
{
    public function testJsonObjectFromEmptyParsedBodyBecomesRequestInput(): void
    {
        self::assertTrue(class_exists(JsonBodyParserMiddleware::class));

        $request = new ServerRequest(
            [],
            [],
            'https://example.test/submit',
            'POST',
            (new StreamFactory())->createStream('{"email":"person@example.test"}'),
            ['Content-Type' => 'application/json; charset=utf-8'],
            [],
            [],
            [],
        );
        $handler = new CapturingHandler();

        $response = (new JsonBodyParserMiddleware())->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->received);
        self::assertSame('person@example.test', Request::createFromPsr7($handler->received)->input('email'));
    }

    public function testStructuredJsonMediaTypeIsParsed(): void
    {
        $request = new ServerRequest(
            [],
            [],
            'https://example.test/submit',
            'POST',
            (new StreamFactory())->createStream('{"message":"accepted"}'),
            ['Content-Type' => 'application/vnd.api+json'],
        );
        $handler = new CapturingHandler();

        (new JsonBodyParserMiddleware())->process($request, $handler);

        self::assertNotNull($handler->received);
        self::assertSame(['message' => 'accepted'], $handler->received->getParsedBody());
    }

    public function testMalformedJsonReturnsBadRequestWithoutCallingHandler(): void
    {
        $request = new ServerRequest(
            [],
            [],
            'https://example.test/submit',
            'POST',
            (new StreamFactory())->createStream('{"email":'),
            ['Content-Type' => 'application/json'],
        );
        $handler = new CapturingHandler();

        $response = (new JsonBodyParserMiddleware())->process($request, $handler);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertNull($handler->received);
    }

    public function testScalarJsonReturnsBadRequest(): void
    {
        foreach (['42', 'true', 'null', '"text"'] as $body) {
            $request = new ServerRequest(
                [],
                [],
                'https://example.test/submit',
                'POST',
                (new StreamFactory())->createStream($body),
                ['Content-Type' => 'application/json'],
            );
            $handler = new CapturingHandler();

            $response = (new JsonBodyParserMiddleware())->process($request, $handler);

            self::assertSame(400, $response->getStatusCode());
            self::assertNull($handler->received);
        }
    }

    public function testParsingRestoresSeekableBodyPosition(): void
    {
        $body = (new StreamFactory())->createStream('{"status":"ready"}');
        $body->seek(4);
        $request = new ServerRequest(
            [],
            [],
            'https://example.test/submit',
            'POST',
            $body,
            ['Content-Type' => 'application/json'],
        );
        $handler = new CapturingHandler();

        (new JsonBodyParserMiddleware())->process($request, $handler);

        self::assertNotNull($handler->received);
        self::assertSame(['status' => 'ready'], $handler->received->getParsedBody());
        self::assertSame(4, $handler->received->getBody()->tell());
    }

    public function testExistingParsedBodyIsNotReplaced(): void
    {
        $request = new ServerRequest(
            [],
            [],
            'https://example.test/submit',
            'POST',
            (new StreamFactory())->createStream('{"submitted":"ignored"}'),
            ['Content-Type' => 'application/json'],
            [],
            [],
            ['submitted' => 'trusted'],
        );
        $handler = new CapturingHandler();

        (new JsonBodyParserMiddleware())->process($request, $handler);

        self::assertSame($request, $handler->received);
        self::assertSame(['submitted' => 'trusted'], $handler->received->getParsedBody());
    }

    public function testNonJsonRequestIsPassedThrough(): void
    {
        $request = new ServerRequest(
            [],
            [],
            'https://example.test/submit',
            'POST',
            (new StreamFactory())->createStream('{"submitted":"not-json-input"}'),
            ['Content-Type' => 'text/plain'],
        );
        $handler = new CapturingHandler();

        (new JsonBodyParserMiddleware())->process($request, $handler);

        self::assertSame($request, $handler->received);
        self::assertNull($handler->received->getParsedBody());
    }
}

final class CapturingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $received = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->received = $request;

        return new HtmlResponse('ok');
    }
}
