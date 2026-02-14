<?php

declare(strict_types=1);

namespace Denosys\Http\Middleware;

use Denosys\Http\Exceptions\TooManyRequestsException;
use Denosys\RateLimiter\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ThrottleRequestsMiddleware implements MiddlewareInterface
{
    /**
     * Create a new throttle middleware instance.
     *
     * @param RateLimiter $limiter The rate limiter service
     * @param int $maxAttempts Maximum requests allowed in the decay period
     * @param int $decayMinutes Time window in minutes
     * @param string|null $keyPrefix Optional prefix for the rate limit key
     */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts = 5,
        private readonly int $decayMinutes = 1,
        private readonly ?string $keyPrefix = null,
    ) {}

    /**
     * Process the request and enforce rate limits.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveRequestKey($request);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            throw new TooManyRequestsException($retryAfter);
        }

        // Record the attempt
        $this->limiter->hit($key, $this->decayMinutes * 60);

        // Process the request
        $response = $handler->handle($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders($response, $key);
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(ResponseInterface $response, string $key): ResponseInterface
    {
        $remaining = $this->limiter->remaining($key, $this->maxAttempts);
        $retryAfter = $this->limiter->availableIn($key);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) ($this->limiter->availableAt($key) ?? time()));
    }

    /**
     * Resolve the rate limit key from the request.
     * 
     * Uses client IP address combined with the request path and method.
     */
    private function resolveRequestKey(ServerRequestInterface $request): string
    {
        $ip = $this->getClientIp($request);
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        $key = $ip . '|' . $method . '|' . $path;

        if ($this->keyPrefix !== null) {
            $key = $this->keyPrefix . ':' . $key;
        }

        return sha1($key);
    }

    /**
     * Get the client IP address from the request.
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        // Check common proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Older proxy header
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Create a new instance with different limits.
     * 
     * Useful for applying different limits to different routes.
     *
     * @param int $maxAttempts Maximum requests allowed
     * @param int $decayMinutes Time window in minutes
     * @return self
     */
    public function withLimits(int $maxAttempts, int $decayMinutes): self
    {
        return new self(
            $this->limiter,
            $maxAttempts,
            $decayMinutes,
            $this->keyPrefix
        );
    }

    /**
     * Create a new instance with a different key prefix.
     *
     * @param string $prefix The key prefix
     * @return self
     */
    public function withPrefix(string $prefix): self
    {
        return new self(
            $this->limiter,
            $this->maxAttempts,
            $this->decayMinutes,
            $prefix
        );
    }
}
