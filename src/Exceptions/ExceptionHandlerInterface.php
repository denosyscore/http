<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Interface for exception handlers.
 * 
 * Handlers are checked in priority order (highest first).
 * The first handler that can handle an exception will process it.
 */
interface ExceptionHandlerInterface
{
    /**
     * Determine if this handler can handle the given exception.
     */
    public function canHandle(Throwable $exception): bool;

    /**
     * Handle the exception and return a response.
     */
    public function handle(Throwable $exception): ResponseInterface;

    /**
     * Get the priority of this handler (higher = checked first).
     */
    public function getPriority(): int;
}
