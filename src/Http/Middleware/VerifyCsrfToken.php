<?php

declare(strict_types=1);

namespace CFXP\Core\Http\Middleware;

use CFXP\Core\Http\Exceptions\TokenMismatchException;
use CFXP\Core\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * HTTP methods that should skip CSRF verification.
     * These are "safe" methods that should not change state.
     *
     * @var array<string>
     */
    protected const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /**
     * URI patterns that should be excluded from CSRF verification.
     * Useful for webhooks, APIs, etc.
     *
     * @var array<string>
     */
    /** @var array<string, mixed> */

    protected array $except = [];

    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    /**
     * Process an incoming request and verify CSRF token.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldVerify($request) && !$this->tokensMatch($request)) {
            throw new TokenMismatchException('CSRF token mismatch.');
        }

        return $handler->handle($request);
    }

    /**
     * Determine if the request should have CSRF verification.
     */
    protected function shouldVerify(ServerRequestInterface $request): bool
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, self::SAFE_METHODS, true)) {
            return false;
        }

        if ($this->isExcluded($request)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the request URI matches any exclusion patterns.
     */
    protected function isExcluded(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri()->getPath();

        foreach ($this->except as $pattern) {
            if ($this->matchesPattern($uri, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a URI against a pattern (supports wildcards).
     */
    protected function matchesPattern(string $uri, string $pattern): bool
    {
        // Exact match
        if ($pattern === $uri) {
            return true;
        }

        // Wildcard match (e.g., /api/* matches /api/webhooks)
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');
            return str_starts_with($uri, $prefix);
        }

        return false;
    }

    /**
     * Verify that the CSRF tokens match.
     */
    protected function tokensMatch(ServerRequestInterface $request): bool
    {
        $sessionToken = $this->session->token();

        if (empty($sessionToken)) {
            return false;
        }

        $requestToken = $this->getTokenFromRequest($request);

        if (empty($requestToken)) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Get the CSRF token from the request.
     *
     * Checks both POST body (_token field) and X-CSRF-TOKEN header.
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        // First, check POST body for _token
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['_token']) && is_string($body['_token'])) {
            return $body['_token'];
        }

        // Fall back to X-CSRF-TOKEN header (useful for AJAX requests)
        $headerToken = $request->getHeaderLine('X-CSRF-TOKEN');
        if (!empty($headerToken)) {
            return $headerToken;
        }

        // Also check X-XSRF-TOKEN header (often used by JavaScript frameworks)
        $xsrfToken = $request->getHeaderLine('X-XSRF-TOKEN');
        if (!empty($xsrfToken)) {
            return $xsrfToken;
        }

        return null;
    }

    /**
     * Determine if the request is an API/AJAX request.
     */
    protected function isApiRequest(ServerRequestInterface $request): bool
    {
        // Check Accept header for JSON
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Check X-Requested-With header (jQuery sets this for AJAX)
        $xRequestedWith = $request->getHeaderLine('X-Requested-With');
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        // Check if URI starts with /api/
        $uri = $request->getUri()->getPath();
        if (str_starts_with($uri, '/api/')) {
            return true;
        }

        return false;
    }

    /**
     * Get the referer URL for redirect.
     */
    protected function getRefererUrl(ServerRequestInterface $request): string
    {
        $referer = $request->getHeaderLine('Referer');

        if (!empty($referer)) {
            return $referer;
        }

        // Fall back to previous URL in session or home
        return $this->session->previousUrl() ?? '/';
    }

    /**
     * Set URI patterns to exclude from CSRF verification.
     *
     * @param array<string> $patterns URI patterns (supports * wildcard)
     */
    public function setExcept(array $patterns): self
    {
        $this->except = $patterns;
        return $this;
    }

    /**
     * Add a URI pattern to exclude from CSRF verification.
     */
    public function addExcept(string $pattern): self
    {
        $this->except[] = $pattern;
        return $this;
    }
}
