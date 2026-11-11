<?php

declare(strict_types=1);

namespace Denosys\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Accepts a redirect target only when it points at the request's own origin.
 *
 * Relative paths must start with a single "/". Absolute URLs must use http or
 * https with the request's scheme, host and port, and carry no credentials.
 * Anything else resolves to the fallback, so values from headers or input can
 * never send a user to another site.
 */
final class SameOriginUrl
{
    public static function resolve(string $url, ServerRequestInterface $request, string $fallback = '/'): string
    {
        return self::isSameOrigin($url, $request) ? $url : $fallback;
    }

    public static function isSameOrigin(string $url, ServerRequestInterface $request): bool
    {
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        if (!isset($parts['scheme']) && !isset($parts['host'])) {
            return str_starts_with($url, '/') && !str_starts_with($url, '//');
        }

        $uri = $request->getUri();
        $scheme = strtolower($uri->getScheme());
        $host = strtolower($uri->getHost());
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
        $targetScheme = strtolower($parts['scheme'] ?? '');
        $targetPort = $parts['port'] ?? ($targetScheme === 'https' ? 443 : 80);

        return in_array($targetScheme, ['http', 'https'], true)
            && $targetScheme === $scheme
            && strtolower($parts['host'] ?? '') === $host
            && $targetPort === $port
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}
