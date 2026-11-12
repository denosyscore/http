<?php

declare(strict_types=1);

use Denosys\Http\Exceptions\TooManyRequestsException;
use Denosys\Http\Middleware\RateLimitExceptionMiddleware;
use Denosys\Session\SessionInterface;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitRedirectTest extends TestCase
{
    public function testRateLimitRedirectIgnoresAnOffSiteReferer(): void
    {
        $response = $this->process('https://evil.example/phish');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/previous', $response->getHeaderLine('Location'));
    }

    public function testRateLimitRedirectFollowsASameOriginReferer(): void
    {
        $response = $this->process('https://app.test/login');

        self::assertSame('https://app.test/login', $response->getHeaderLine('Location'));
    }

    private function process(string $referer): ResponseInterface
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('previousUrl')->willReturn('/previous');

        $request = (new ServerRequest([], [], 'https://app.test/login', 'POST'))
            ->withHeader('Referer', $referer)
            ->withHeader('Accept', 'text/html');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new TooManyRequestsException(30);
            }
        };

        return (new RateLimitExceptionMiddleware($session))->process($request, $handler);
    }
}
