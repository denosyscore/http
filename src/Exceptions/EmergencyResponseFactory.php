<?php

declare(strict_types=1);

namespace CFXP\Core\Exceptions;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * Factory for creating emergency responses when exception handling fails
 * 
 * This class provides the absolute fallback when all other exception
 * handling mechanisms have failed. It creates minimal responses without
 * any external dependencies that could cause additional failures.
 */
class EmergencyResponseFactory
{
    /**
     * Create an emergency response for the given exception
     */
    public static function create(Throwable $exception): ResponseInterface
    {
        $isApiRequest = self::isApiRequest();
        $isProduction = self::isProduction();
        
        if ($isApiRequest) {
            return self::createJsonResponse($exception, $isProduction);
        }
        
        return self::createHtmlResponse($exception, $isProduction);
    }

    /**
     * Create a JSON error response
     */
    private static function createJsonResponse(Throwable $exception, bool $isProduction): ResponseInterface
    {
        $data = [
            'error' => true,
            'message' => $isProduction 
                ? 'An unexpected error occurred. Please try again later.'
                : $exception->getMessage(),
            'type' => 'server_error',
            'status' => 500
        ];

        if (!$isProduction) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice($exception->getTrace(), 0, 10) // Limit trace size
            ];
        }

        try {
            return new JsonResponse($data, 500);
        } catch (Throwable $e) {
            // If JSON response fails, create the most basic response possible
            return self::createBasicResponse();
        }
    }

    /**
     * Create an HTML error response
     */
    private static function createHtmlResponse(Throwable $exception, bool $isProduction): ResponseInterface
    {
        if ($isProduction) {
            $html = self::getProductionErrorHtml();
        } else {
            $html = self::getDebugErrorHtml($exception);
        }

        try {
            return new HtmlResponse($html, 500);
        } catch (Throwable $e) {
            return self::createBasicResponse();
        }
    }

    /**
     * Create the most basic response possible
     */
    private static function createBasicResponse(): ResponseInterface
    {
        // Create response without any external dependencies
        $response = new class implements ResponseInterface {
            public function getProtocolVersion(): string { return '1.1'; }
            public function withProtocolVersion($version): ResponseInterface { return $this; }
            /**
             * @return array<string, string|array<string>>
             */
            public function getHeaders(): array { return ['Content-Type' => ['text/plain']]; }
            public function hasHeader($name): bool { return strtolower($name) === 'content-type'; }
            public function getHeader($name): array { return $this->hasHeader($name) ? ['text/plain'] : []; }
            public function getHeaderLine($name): string { return $this->hasHeader($name) ? 'text/plain' : ''; }
            public function withHeader($name, $value): ResponseInterface { return $this; }
            public function withAddedHeader($name, $value): ResponseInterface { return $this; }
            public function withoutHeader($name): ResponseInterface { return $this; }
            public function getBody(): \Psr\Http\Message\StreamInterface { return new class implements \Psr\Http\Message\StreamInterface { 
                public function __toString(): string { return 'Internal Server Error'; }
                public function close(): void {}
                public function detach() { return null; }
                public function getSize(): ?int { return 21; }
                public function tell(): int { return 0; }
                public function eof(): bool { return true; }
                public function isSeekable(): bool { return false; }
                public function seek($offset, $whence = SEEK_SET): void {}
                public function rewind(): void {}
                public function isWritable(): bool { return false; }
                public function write($string): int { return 0; }
                public function isReadable(): bool { return false; }
                public function read($length): string { return ''; }
                public function getContents(): string { return 'Internal Server Error'; }
                public function getMetadata($key = null) { return null; }
            }; }
            public function withBody($body): ResponseInterface { return $this; }
            public function getStatusCode(): int { return 500; }
            public function withStatus($code, $reasonPhrase = ''): ResponseInterface { return $this; }
            public function getReasonPhrase(): string { return 'Internal Server Error'; }
        };

        return $response;
    }

    /**
     * Get production-safe error HTML
     */
    private static function getProductionErrorHtml(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; text-align: center; }
        .container { max-width: 600px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; margin-bottom: 20px; font-size: 2em; }
        p { color: #6c757d; line-height: 1.6; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Server Error</h1>
        <p>We apologize for the inconvenience. An unexpected error has occurred.</p>
        <p>Please try again later. If the problem persists, please contact our support team.</p>
    </div>
</body>
</html>';
    }

    /**
     * Get debug error HTML with exception details
     */
    private static function getDebugErrorHtml(Throwable $exception): string
    {
        $exceptionClass = htmlspecialchars(get_class($exception), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $exception->getLine();
        $trace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Application Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .exception-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .trace { background: #343a40; color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .emergency-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-bottom: 15px; color: #856404; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"header\">
            <h1>Application Error</h1>
        </div>
        <div class=\"content\">
            <div class=\"emergency-notice\">
                <strong>Emergency Response:</strong> This error page was generated by the emergency fallback system.
            </div>
            <div class=\"exception-info\">
                <h3>{$exceptionClass}</h3>
                <p><strong>Message:</strong> {$message}</p>
                <p><strong>File:</strong> {$file} (line {$line})</p>
            </div>
            <h4>Stack Trace:</h4>
            <div class=\"trace\"><pre>{$trace}</pre></div>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Check if the current request is an API request
     */
    private static function isApiRequest(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        return strpos($requestUri, '/api/') !== false ||
               strpos($acceptHeader, 'application/json') !== false ||
               strpos($contentType, 'application/json') !== false ||
               (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Check if running in production environment
     */
    private static function isProduction(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        return $env === 'production';
    }
}