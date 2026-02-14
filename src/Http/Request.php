<?php

declare(strict_types=1);

namespace CFXP\Core\Http;

use CFXP\Core\Session\SessionInterface;
use CFXP\Core\Session\SessionAttributeKeys;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class Request implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    private array $routeParams = [];
    private mixed $user = null;

    public function __construct(private ServerRequestInterface $request)
    {
    }

    /**
     * Get input value from request body or query parameters
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->getParsedBody();
        $query = $this->getQueryParams();

        // Check parsed body first (POST data)
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }

        // Then check query parameters (GET data)
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }

        return $default;
    }

    /**
     * Get all input data (body + query + files combined)
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $body = $this->getParsedBody();
        $query = $this->getQueryParams();
        $files = $this->getUploadedFiles();

        if (!is_array($body)) {
            $body = [];
        }

        // Merge query, body, and files (files take precedence if same key)
        return array_merge($query, $body, $files);
    }

    /**
     * Get only specified keys from input
      * @param array<string> $keys
      * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get all input except specified keys
      * @param array<string> $keys
      * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Check if input has a specific key
     */
    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    /**
     * Check if input has all specified keys
      * @param array<string> $keys
     */
    public function hasAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->has($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Set route parameters (called by router).
     * 
     * @deprecated Use withRouteParams() for immutability
      * @param array<string, mixed> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Return a new instance with the given route parameters.
      * @param array<string, mixed> $params
     */
    public function withRouteParams(array $params): static
    {
        $new = clone $this;
        $new->routeParams = $params;
        return $new;
    }

    /**
     * Get route parameter value
     */
    public function route(string $param, mixed $default = null): mixed
    {
        return $this->routeParams[$param] ?? $default;
    }

    /**
     * Get all route parameters
     *
     * @return array<string, mixed>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Get client IP address
     */
    public function ip(): string
    {
        // Check for IP from shared internet
        if (!empty($this->getServerParams()['HTTP_CLIENT_IP'])) {
            return $this->getServerParams()['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($this->getServerParams()['HTTP_X_FORWARDED_FOR'])) {
            return $this->getServerParams()['HTTP_X_FORWARDED_FOR'];
        }
        // Return normal IP
        else {
            return $this->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Check if request expects JSON response
     */
    public function expectsJson(): bool
    {
        return $this->isAjax() || $this->wantsJson();
    }

    /**
     * Check if request wants JSON response based on Accept header
     */
    public function wantsJson(): bool
    {
        $accept = $this->getHeaderLine('Accept');
        return str_contains($accept, 'application/json') || str_contains($accept, 'text/json');
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure(): bool
    {
        return $this->getUri()->getScheme() === 'https';
    }

    /**
     * Get request URL
     */
    public function url(): string
    {
        return (string) $this->getUri();
    }

    /**
     * Get request path
     */
    public function path(): string
    {
        return $this->getUri()->getPath();
    }

    /**
     * Get user agent
     */
    public function userAgent(): string
    {
        return $this->getHeaderLine('User-Agent');
    }

    /**
     * Get referer URL
     */
    public function referer(): string
    {
        return $this->getHeaderLine('Referer');
    }

    /**
     * Set authenticated user (called by auth middleware).
     * 
     * @deprecated Use withUser() for immutability
     */
    public function setUser(mixed $user): void
    {
        $this->user = $user;
    }

    /**
     * Return a new instance with the given user.
     */
    public function withUser(mixed $user): static
    {
        $new = clone $this;
        $new->user = $user;
        return $new;
    }

    /**
     * Get authenticated user
     */
    public function user(): mixed
    {
        return $this->user;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /**
     * Get the session instance.
     *
     * @throws \RuntimeException If session is not available
     */
    public function session(): SessionInterface
    {
        $session = $this->getAttribute(SessionAttributeKeys::SESSION);

        if ($session instanceof SessionInterface) {
            return $session;
        }

        throw new \RuntimeException(
            'Session is not available. Ensure StartSessionMiddleware is registered.'
        );
    }

    /**
     * Get a value from the previous request's flashed input.
     * 
     * Useful for repopulating form fields after validation failure.
     */
    public function old(string $key, mixed $default = null): mixed
    {
        if (!$this->hasSession()) {
            return $default;
        }

        $oldInput = $this->session()->get('_old_input', []);
        return $oldInput[$key] ?? $default;
    }

    /**
     * Get all flashed input from the previous request.
     *
     * @return array<string, mixed>
     */
    public function oldInput(): array
    {
        if (!$this->hasSession()) {
            return [];
        }

        return $this->session()->get('_old_input', []);
    }

    /**
     * Check if the request has a session.
     */
    public function hasSession(): bool
    {
        return $this->getAttribute(SessionAttributeKeys::SESSION) instanceof SessionInterface;
    }

    /**
     * Get uploaded file
     */
    public function file(string $key): ?object
    {
        $files = $this->getUploadedFiles();
        return $files[$key] ?? null;
    }

    /**
     * Check if request has file upload
     */
    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    public function getProtocolVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->request = $this->request->withProtocolVersion($version);
        return $new;
    }

    /**
     * @return array<string, string|array<string>>
     */
    public function getHeaders(): array
    {
        return $this->request->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->request->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->request->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->request = $this->request->withHeader($name, $value);
        return $new;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->request = $this->request->withAddedHeader($name, $value);
        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $new = clone $this;
        $new->request = $this->request->withoutHeader($name);
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->request->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->request = $this->request->withBody($body);
        return $new;
    }

    public function getRequestTarget(): string
    {
        return $this->request->getRequestTarget();
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $new = clone $this;
        $new->request = $this->request->withRequestTarget($requestTarget);
        return $new;
    }

    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    public function withMethod(string $method): static
    {
        $new = clone $this;
        $new->request = $this->request->withMethod($method);
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->request->getUri();
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $new = clone $this;
        $new->request = $this->request->withUri($uri, $preserveHost);
        return $new;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->request->getServerParams();
    }

    /**
     * @return array<string, string>
     */
    public function getCookieParams(): array
    {
        return $this->request->getCookieParams();
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function withCookieParams(array $cookies): static
    {
        $new = clone $this;
        $new->request = $this->request->withCookieParams($cookies);
        return $new;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->request->getQueryParams();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static
    {
        $new = clone $this;
        $new->request = $this->request->withQueryParams($query);
        return $new;
    }

    /**
     * @return array<string, \Psr\Http\Message\UploadedFileInterface>
     */
    public function getUploadedFiles(): array
    {
        return $this->request->getUploadedFiles();
    }

    /**
     * @param array<string, mixed> $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $new = clone $this;
        $new->request = $this->request->withUploadedFiles($uploadedFiles);
        return $new;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getParsedBody(): array|null|object
    {
        return $this->request->getParsedBody();
    }

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function withParsedBody($data): static
    {
        $new = clone $this;
        $new->request = $this->request->withParsedBody($data);
        return $new;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->request->getAttributes();
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->request->getAttribute($name, $default);
    }

    public function withAttribute(string $name, $value): static
    {
        $new = clone $this;
        $new->request = $this->request->withAttribute($name, $value);
        return $new;
    }

    public function withoutAttribute(string $name): static
    {
        $new = clone $this;
        $new->request = $this->request->withoutAttribute($name);
        return $new;
    }

    /**
     * Get the underlying PSR-7 request
     */
    public function getOriginalRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Create a new Request instance from PSR-7 request
     */
    public static function createFromPsr7(ServerRequestInterface $request): self
    {
        return new self($request);
    }
}
