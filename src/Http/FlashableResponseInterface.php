<?php

declare(strict_types=1);

namespace CFXP\Core\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Contract for responses that support flash messages.
 * 
 * Typically used for redirect responses where you want to
 * display a message on the next page.
 */
interface FlashableResponseInterface extends ResponseInterface
{
    /**
     * Flash a message to the session.
     */
    public function withFlash(string $type, string $message): static;

    /**
     * Flash a success message.
     */
    public function withSuccess(string $message): static;

    /**
     * Flash an error message.
     */
    public function withError(string $message): static;

    /**
     * Flash a warning message.
     */
    public function withWarning(string $message): static;

    /**
     * Flash an info message.
     */
    public function withInfo(string $message): static;

    /**
     * Flash the input data for form repopulation.
     * 
     * @param array<string, mixed>|null $input If null, uses all current input
      * @param array<string, mixed> $input
     */
    public function withInput(?array $input = null): static;

    /**
     * Flash validation errors.
     * 
     * @param array<string, array<string>|string> $errors
      * @param array<string, array<string>> $errors
     */
    public function withErrors(array $errors): static;
}
