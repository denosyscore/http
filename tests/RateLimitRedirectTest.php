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

if (!interface_exists(SessionInterface::class)) {
    require __DIR__ . '/Fixtures/SessionInterface.php';
}

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
        $session = new class implements SessionInterface {
            public function flash(string $key, mixed $value): void
            {
            }

            public function previousUrl(): ?string
            {
                return '/previous';
            }
        };

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
