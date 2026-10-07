<?php

declare(strict_types=1);

namespace Denosys\Http\Tests;

use Denosys\Http\Middleware\XsrfCookieMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class XsrfCookieMiddlewareTest extends TestCase
{
    public function testExposesTokenWithoutReplacingOtherCookies(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response()->withAddedHeader('Set-Cookie', 'session=existing; HttpOnly');
            }
        };
        $middleware = new XsrfCookieMiddleware(static fn (): string => 'test+token');

        $response = $middleware->process(new ServerRequest([], [], 'https://example.test/', 'GET'), $handler);

        self::assertSame('session=existing; HttpOnly', $response->getHeader('Set-Cookie')[0]);
        self::assertSame('XSRF-TOKEN=test%2Btoken; Path=/; Max-Age=7200; SameSite=Lax; Secure', $response->getHeader('Set-Cookie')[1]);
    }

    public function testEmptyTokenDoesNotEmitCookieAndHttpDoesNotSetSecure(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $empty = new XsrfCookieMiddleware(static fn (): string => '');
        self::assertSame([], $empty->process(new ServerRequest([], [], 'http://example.test/', 'GET'), $handler)->getHeader('Set-Cookie'));

        $plain = new XsrfCookieMiddleware(static fn (): string => 'abc', lifetimeSeconds: 60);
        self::assertSame(
            'XSRF-TOKEN=abc; Path=/; Max-Age=60; SameSite=Lax',
            $plain->process(new ServerRequest([], [], 'http://example.test/', 'GET'), $handler)->getHeader('Set-Cookie')[0],
        );
    }
}
