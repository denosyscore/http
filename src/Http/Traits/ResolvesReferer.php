<?php

declare(strict_types=1);

namespace Denosys\Http\Traits;

use Denosys\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Trait for resolving referer URLs from request and session.
 *
 * Extracts common referer resolution logic used by multiple middlewares
 * (ValidationExceptionMiddleware, VerifyCsrfToken, etc.)
 */
trait ResolvesReferer
{
    /**
     * Get the referer URL from the request headers or session.
     *
     * @param ServerRequestInterface $request The current request
     * @param SessionInterface|null $session Optional session for fallback to previousUrl
     * @param string $fallback Default fallback URL if no referer can be determined
     * @return string The resolved referer URL
     */
    protected function getRefererUrl(
        ServerRequestInterface $request,
        ?SessionInterface $session = null,
        string $fallback = '/'
    ): string {
        // First, check the Referer header
        $referer = $request->getHeaderLine('Referer');

        if (!empty($referer)) {
            return $referer;
        }

        // Fall back to previous URL stored in session
        if ($session !== null) {
            $previousUrl = $session->previousUrl();
            if ($previousUrl !== null) {
                return $previousUrl;
            }
        }

        // Final fallback
        return $fallback;
    }
}
