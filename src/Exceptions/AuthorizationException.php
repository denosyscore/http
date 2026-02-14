<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Exception;

/**
 * Exception thrown when a user is not authorized to perform a request.
 * 
 * Typically thrown by FormRequest::authorize() when authorization fails.
 */
class AuthorizationException extends Exception
{
    /**
     * Create a new authorization exception.
     */
    public function __construct(
        string $message = 'This action is unauthorized.',
        int $code = 403,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
