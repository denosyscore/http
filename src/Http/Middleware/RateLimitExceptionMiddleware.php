<?php

declare(strict_types=1);

namespace Denosys\Http\Middleware;

use Denosys\Http\Exceptions\TooManyRequestsException;
use Denosys\Http\RedirectResponse;
use Denosys\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\HtmlResponse;

class RateLimitExceptionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (TooManyRequestsException $e) {
            return $this->handleRateLimitException($request, $e);
        }
    }

    /**
     * Handle a rate limit exception.
     */
    private function handleRateLimitException(
        ServerRequestInterface $request,
        TooManyRequestsException $e
    ): ResponseInterface {
        $retryAfter = $e->getRetryAfter();
        
        // For AJAX/API requests, return JSON
        if ($this->isJsonRequest($request)) {
            return $this->createJsonResponse($e, $retryAfter);
        }

        // For regular requests, flash error and redirect back
        return $this->createRedirectResponse($request, $e, $retryAfter);
    }

    /**
     * Create a JSON response for API/AJAX requests.
     */
    private function createJsonResponse(TooManyRequestsException $e, int $retryAfter): ResponseInterface
    {
        $response = new JsonResponse([
            'error' => 'Too Many Requests',
            'message' => $e->getMessage(),
            'retry_after' => $retryAfter,
        ], 429);

        return $response
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withHeader('X-RateLimit-Limit', '0')
            ->withHeader('X-RateLimit-Remaining', '0');
    }

    /**
     * Create a redirect response for regular requests.
     */
    private function createRedirectResponse(
        ServerRequestInterface $request,
        TooManyRequestsException $e,
        int $retryAfter
    ): ResponseInterface {
        // Format the wait time in a human-readable way
        $waitMessage = $this->formatWaitTime($retryAfter);
        
        // Flash the error message
        $this->session->flash('error', "Too many attempts. Please try again {$waitMessage}.");
        $this->session->flash('errors', [
            'email' => ["Too many attempts. Please try again {$waitMessage}."]
        ]);

        // Get referer URL for redirect back
        $referer = $this->getRefererUrl($request);

        return (new RedirectResponse($referer, 302))
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    /**
     * Format wait time in a human-readable way.
     */
    private function formatWaitTime(int $seconds): string
    {
        if ($seconds < 60) {
            return "in {$seconds} second" . ($seconds !== 1 ? 's' : '');
        }

        $minutes = (int) ceil($seconds / 60);
        return "in {$minutes} minute" . ($minutes !== 1 ? 's' : '');
    }

    /**
     * Check if this is a JSON/API request.
     */
    private function isJsonRequest(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');
        $xRequestedWith = $request->getHeaderLine('X-Requested-With');

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || $xRequestedWith === 'XMLHttpRequest';
    }

    /**
     * Get the referer URL from the request headers.
     */
    private function getRefererUrl(ServerRequestInterface $request): string
    {
        $referer = $request->getHeaderLine('Referer');

        if (empty($referer)) {
            // Fall back to previous URL in session or home
            return $this->session->previousUrl() ?? '/';
        }

        return $referer;
    }
}
