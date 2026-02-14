<?php

declare(strict_types=1);

namespace CFXP\Core\Http\Middleware;

use CFXP\Core\Config\ConfigurationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing headers based on configuration.
 * Configure allowed origins, methods, and headers in config/cors.php.
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigurationInterface $config
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Get CORS configuration
        $allowedOrigins = $this->config->get('cors.allowed_origins', ['*']);
        $allowedMethods = $this->config->get('cors.allowed_methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $allowedHeaders = $this->config->get('cors.allowed_headers', 'Content-Type, Authorization');

        // Determine the appropriate Access-Control-Allow-Origin value
        $allowOrigin = $this->resolveAllowedOrigin($request, $allowedOrigins);

        if ($allowOrigin !== null) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                ->withHeader('Access-Control-Allow-Methods', $allowedMethods)
                ->withHeader('Access-Control-Allow-Headers', $allowedHeaders);

            // If not using wildcard, add Vary header
            if ($allowOrigin !== '*') {
                $response = $response->withAddedHeader('Vary', 'Origin');
            }
        }

        return $response;
    }

    /**
     * Resolve which origin to allow based on configuration.
     *
     * @param ServerRequestInterface $request
     * @param array<string> $allowedOrigins
     * @return string|null The origin to allow, or null if origin is not allowed
     */
    private function resolveAllowedOrigin(ServerRequestInterface $request, array $allowedOrigins): ?string
    {
        // Wildcard allows all origins
        if (in_array('*', $allowedOrigins, true)) {
            return '*';
        }

        // Get the Origin header from the request
        $origin = $request->getHeaderLine('Origin');

        if (empty($origin)) {
            // No Origin header means it's not a CORS request
            // Return first allowed origin for preflight compatibility
            return $allowedOrigins[0] ?? null;
        }

        // Check if the request origin is in the allowed list
        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }
}
