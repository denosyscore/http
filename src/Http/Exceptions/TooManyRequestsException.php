<?php

declare(strict_types=1);

namespace CFXP\Core\Http\Exceptions;

use RuntimeException;

class TooManyRequestsException extends RuntimeException
{
    /**
     * Create a new too many requests exception.
     *
     * @param int $retryAfter Seconds until the limit resets
     * @param string $message The exception message
     */
    public function __construct(
        public readonly int $retryAfter = 60,
        string $message = 'Too many requests. Please try again later.',
    ) {
        parent::__construct($message, 429);
    }

    /**
     * Get the number of seconds until retry is allowed.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
