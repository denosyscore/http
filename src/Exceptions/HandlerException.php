<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Exception;
use Throwable;

class HandlerException extends Exception
{
    private ?Throwable $originalException;

    public function __construct(
        string $message,
        ?Throwable $originalException = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->originalException = $originalException;
    }

    public function getOriginalException(): ?Throwable
    {
        return $this->originalException;
    }

    public static function fromHandler(string $handlerClass, Throwable $originalException, ?Throwable $handlerError = null): self
    {
        $message = sprintf(
            'Exception handler [%s] failed to handle [%s]: %s',
            $handlerClass,
            get_class($originalException),
            $originalException->getMessage()
        );

        return new self($message, $originalException, 0, $handlerError);
    }
}
