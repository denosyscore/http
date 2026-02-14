<?php

declare(strict_types=1);

namespace CFXP\Core\Exceptions;

/**
 * Trait providing common functionality for exception handlers.
 * 
 * Use this trait in classes implementing ExceptionHandlerInterface
 * to get access to shared helper methods.
 */
trait ExceptionHandlerTrait
{
    /**
     * Clean all output buffers to prevent content corruption.
     */
    protected function cleanOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Check if the current request is an API request.
     */
    protected function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Check if request path starts with /api
        if (str_starts_with($uri, '/api')) {
            return true;
        }

        // Check Accept header for JSON
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Check Content-Type header for JSON
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        // Check X-Requested-With header for AJAX
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Check if debug mode is enabled.
     */
    protected function isDebugMode(): bool
    {
        // Check environment variable
        $appDebug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG');
        
        if ($appDebug !== false) {
            return filter_var($appDebug, FILTER_VALIDATE_BOOLEAN);
        }

        // Default to false in production environment
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        
        return $appEnv === 'local' || $appEnv === 'development';
    }
}
