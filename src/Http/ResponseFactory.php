<?php

declare(strict_types=1);

namespace CFXP\Core\Http;

use CFXP\Core\View\ViewEngine;
use CFXP\Core\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use InvalidArgumentException;

class ResponseFactory
{
    private ?ViewEngine $viewEngine = null;
    private ?SessionInterface $session = null;

    public function __construct(
        ?ViewEngine $viewEngine = null,
        ?SessionInterface $session = null
    ) {
        $this->viewEngine = $viewEngine;
        $this->session = $session;
    }

    /**
     * Create a JSON response
     *
     * Note: CORS headers should be handled by CorsMiddleware, not here.
     *
     * @param array<string, mixed>|object $data
     * @param array<string, string|array<string>> $headers
     */
    public function json(array|object $data, int $status = 200, array $headers = []): ResponseInterface
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Create a success JSON response
     */
    public function success(mixed $data = null, string $message = 'Success', int $status = 200): ResponseInterface
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Create an error JSON response
     */
    public function error(string $message = 'Error', mixed $errors = null, int $status = 400): ResponseInterface
    {
        $payload = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $this->json($payload, $status);
    }

    /**
     * Create a validation error response
      * @param array<string, array<string>> $errors
     */
    public function validationError(array $errors, string $message = 'Validation failed'): ResponseInterface
    {
        return $this->error($message, $errors, 422);
    }

    /**
     * Create a view response
      * @param array<string, mixed> $data
      * @param array<string, string|array<string>> $headers
     */
    public function view(string $template, array $data = [], int $status = 200, array $headers = []): ResponseInterface
    {
        if ($this->viewEngine === null) {
            throw new InvalidArgumentException('ViewEngine not set. Cannot render view responses.');
        }

        $html = $this->viewEngine->render($template, $data);

        return new HtmlResponse($html, $status, $headers);
    }

    /**
     * Create an HTML response with raw content
      * @param array<string, string|array<string>> $headers
     */
    public function html(string $html, int $status = 200, array $headers = []): ResponseInterface
    {
        return new HtmlResponse($html, $status, $headers);
    }

    /**
     * Create a redirect response.
      * @param array<string, string|array<string>> $headers
     */
    public function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        $response = new RedirectResponse($url, $status, $headers);

        if ($this->session !== null) {
            $response->setSession($this->session);
        }

        return $response;
    }

    /**
     * Create a permanent redirect response.
      * @param array<string, string|array<string>> $headers
     */
    public function permanentRedirect(string $url, array $headers = []): RedirectResponse
    {
        return $this->redirect($url, 301, $headers);
    }

    /**
     * Create a redirect back response (to referer).
     *
     * @param ServerRequestInterface|null $request The current request (to get Referer header)
     * @param string $fallback Fallback URL if no referer is present
     * @param int $status HTTP status code for redirect
     */
    public function back(
        ?ServerRequestInterface $request = null,
        string $fallback = '/',
        int $status = 302
    ): RedirectResponse {
        $referer = $request?->getHeaderLine('Referer') ?: $fallback;
        return $this->redirect($referer, $status);
    }

    /**
     * Create a plain text response
      * @param array<string, string|array<string>> $headers
     */
    public function text(string $text, int $status = 200, array $headers = []): ResponseInterface
    {
        return new TextResponse($text, $status, $headers);
    }

    /**
     * Create a file download response
      * @param array<string, string|array<string>> $headers
     */
    public function download(string $filePath, ?string $filename = null, array $headers = []): ResponseInterface
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $filename = $filename ?? basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $defaultHeaders = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) filesize($filePath),
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => '0'
        ];

        $response = new Response();

        foreach (array_merge($defaultHeaders, $headers) as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write(file_get_contents($filePath));

        return $response;
    }

    /**
     * Create a file response for inline display
      * @param array<string, string|array<string>> $headers
     */
    public function file(string $filePath, ?string $mimeType = null, array $headers = []): ResponseInterface
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $mimeType = $mimeType ?? mime_content_type($filePath) ?: 'application/octet-stream';

        $defaultHeaders = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) filesize($filePath),
        ];

        $response = new Response();

        foreach (array_merge($defaultHeaders, $headers) as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write(file_get_contents($filePath));

        return $response;
    }

    /**
     * Create a 404 Not Found response
     */
    public function notFound(string $message = 'Not Found'): ResponseInterface
    {
        if ($this->viewEngine !== null) {
            try {
                return $this->view('errors.404', ['message' => $message], 404);
            } catch (\Exception $e) {
                // Fall back to simple response if view fails
            }
        }

        return $this->html("<h1>404 Not Found</h1><p>{$message}</p>", 404);
    }

    /**
     * Create a 403 Forbidden response
     */
    public function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        if ($this->viewEngine !== null) {
            try {
                return $this->view('errors.403', ['message' => $message], 403);
            } catch (\Exception $e) {
                // Fall back to simple response if view fails
            }
        }

        return $this->html("<h1>403 Forbidden</h1><p>{$message}</p>", 403);
    }

    /**
     * Create a 500 Internal Server Error response
     */
    public function serverError(string $message = 'Internal Server Error'): ResponseInterface
    {
        if ($this->viewEngine !== null) {
            try {
                return $this->view('errors.500', ['message' => $message], 500);
            } catch (\Exception $e) {
                // Fall back to simple response if view fails
            }
        }

        return $this->html("<h1>500 Internal Server Error</h1><p>{$message}</p>", 500);
    }

    /**
     * Create a 401 Unauthorized response
     */
    public function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->html("<h1>401 Unauthorized</h1><p>{$message}</p>", 401);
    }

    /**
     * Create an XML response
      * @param array<string, string|array<string>> $headers
     */
    public function xml(string $xml, int $status = 200, array $headers = []): ResponseInterface
    {
        $defaultHeaders = [
            'Content-Type' => 'application/xml; charset=utf-8'
        ];

        $response = new Response();

        foreach (array_merge($defaultHeaders, $headers) as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response = $response->withStatus($status);
        $response->getBody()->write($xml);

        return $response;
    }

    /**
     * Create a CSV response
      * @param array<string, mixed> $data
      * @param array<string, string|array<string>> $headers
     */
    public function csv(array $data, string $filename = 'export.csv', array $headers = []): ResponseInterface
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $defaultHeaders = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $response = new Response();

        foreach (array_merge($defaultHeaders, $headers) as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $response->getBody()->write($csv);

        return $response;
    }

    /**
     * Create an empty response
      * @param array<string, string|array<string>> $headers
     */
    public function noContent(array $headers = []): ResponseInterface
    {
        $response = new Response();

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response->withStatus(204);
    }

    /**
     * Set the view engine for rendering views
     */
    public function setViewEngine(ViewEngine $viewEngine): void
    {
        $this->viewEngine = $viewEngine;
    }

    /**
     * Set the session instance for flash message support
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    /**
     * Get the current view engine
     */
    public function getViewEngine(): ?ViewEngine
    {
        return $this->viewEngine;
    }
}
