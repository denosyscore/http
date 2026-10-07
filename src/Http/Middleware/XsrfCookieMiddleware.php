<?php

declare(strict_types=1);

namespace Denosys\Http\Middleware;

use Closure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Opt-in cookie-to-header CSRF support without a required session dependency. */
final readonly class XsrfCookieMiddleware implements MiddlewareInterface
{
    /** @var Closure(): string */
    private Closure $token;

    /** @param callable(): string $token */
    public function __construct(
        callable $token,
        private int $lifetimeSeconds = 7200,
        private string $cookieName = 'XSRF-TOKEN',
        private ?bool $secure = null,
    ) {
        if ($lifetimeSeconds < 1) {
            throw new InvalidArgumentException('Cookie lifetime must be positive.');
        }
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/D', $cookieName) !== 1) {
            throw new InvalidArgumentException('Cookie name must contain only letters, numbers, underscores, or hyphens.');
        }

        $this->token = Closure::fromCallable($token);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $token = ($this->token)();
        if ($token === '') {
            return $response;
        }

        $cookie = $this->cookieName . '=' . rawurlencode($token)
            . '; Path=/; Max-Age=' . $this->lifetimeSeconds . '; SameSite=Lax';
        if ($this->secure === true || ($this->secure === null && $request->getUri()->getScheme() === 'https')) {
            $cookie .= '; Secure';
        }

        return $response->withAddedHeader('Set-Cookie', $cookie);
    }
}
