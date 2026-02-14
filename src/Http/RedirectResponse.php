<?php

declare(strict_types=1);

namespace CFXP\Core\Http;

use InvalidArgumentException;
use CFXP\Core\Http\Traits\FlashesMessages;
use Psr\Http\Message\StreamInterface;
use Laminas\Diactoros\Response;

/**
 * A redirect response with flash message capabilities.
 * 
 * Implements PSR-7 ResponseInterface via composition and adds
 * flash message support for displaying messages after redirect.
 * 
 * @example
 * return $this->redirect('/dashboard')->withSuccess('Welcome back!');
 * return $this->redirect('/login')->withError('Invalid credentials');
 * return $this->back()->withInput()->withErrors($validator->errors());
 */
class RedirectResponse implements FlashableResponseInterface
{
    use FlashesMessages;

    /**
     * The underlying PSR-7 response.
     */
    protected Response $response;

    /**
     * The target URL for the redirect.
     */
    protected string $targetUrl;

    /**
     * Valid redirect status codes.
     */
    private const REDIRECT_CODES = [201, 301, 302, 303, 307, 308];

    /**
     * Create a new redirect response.
     *
     * @param string $url The URL to redirect to
     * @param int $status HTTP status code (301, 302, 303, 307, 308)
     * @param array<string, string|string[]> $headers Additional headers
     * 
     * @throws InvalidArgumentException If status code is not a redirect code
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        if (!in_array($status, self::REDIRECT_CODES, true)) {
            throw new InvalidArgumentException(
                sprintf('The HTTP status code "%d" is not a redirect.', $status)
            );
        }

        $this->targetUrl = $url;
        $this->response = new Response();
        $this->response = $this->response->withStatus($status);
        $this->response = $this->response->withHeader('Location', $url);

        foreach ($headers as $name => $value) {
            $this->response = $this->response->withHeader($name, $value);
        }
    }

    /**
     * Get the target URL for this redirect.
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * Check if this is a redirect response.
     */
    public function isRedirect(?string $location = null): bool
    {
        $isRedirectCode = in_array($this->getStatusCode(), self::REDIRECT_CODES, true);
        
        if ($location === null) {
            return $isRedirectCode;
        }

        return $isRedirectCode && $location === $this->getHeaderLine('Location');
    }

    // =========================================================================
    // PSR-7 ResponseInterface Implementation (Delegation)
    // =========================================================================

    public function getProtocolVersion(): string
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->response = $this->response->withProtocolVersion($version);
        return $new;
    }

    /**

     * @return array<string, mixed>

     */

public function getHeaders(): array

    {
        return $this->response->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->response = $this->response->withHeader($name, $value);
        return $new;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->response = $this->response->withAddedHeader($name, $value);
        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $new = clone $this;
        $new->response = $this->response->withoutHeader($name);
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->response = $this->response->withBody($body);
        return $new;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $new = clone $this;
        $new->response = $this->response->withStatus($code, $reasonPhrase);
        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }
}
