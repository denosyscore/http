<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Denosys\Routing\Exceptions\HttpExceptionInterface;

class TokenMismatchException extends \Exception implements HttpExceptionInterface
{
    public function __construct(
        string $message = 'CSRF token mismatch.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code for this exception.
     */
    public function getStatusCode(): int
    {
        return 419;
    }

    /**
     * Get the HTTP headers for this exception.
     *
     * @return array<string, string>
     */
    /**
     * @return array<string, mixed>
     */
public function getHeaders(): array
    {
        return [];
    }

    /**
     * Get the HTTP reason phrase for this exception.
     */
    public function getReasonPhrase(): string
    {
        return 'Page Expired';
    }
}
