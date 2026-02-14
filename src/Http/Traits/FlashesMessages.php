<?php

declare(strict_types=1);

namespace CFXP\Core\Http\Traits;

use RuntimeException;
use CFXP\Core\Session\SessionInterface;

/**
 * Trait for responses that can flash messages to the session.
 * 
 * This trait provides the implementation for FlashableResponseInterface.
 * Classes using this trait must also implement ResponseInterface.
 */
trait FlashesMessages
{
    /**
     * The session instance for storing flash data.
     */
    protected ?SessionInterface $session = null;

    /**
     * Set the session instance.
     */
    public function setSession(SessionInterface $session): static
    {
        $this->session = $session;
        return $this;
    }

    /**
     * Get the session instance.
     */
    public function getSession(): ?SessionInterface
    {
        return $this->session;
    }

    /**
     * Flash a message to the session.
     */
    public function withFlash(string $type, string $message): static
    {
        $this->ensureSession();
        $this->session->flash($type, $message);
        return $this;
    }

    /**
     * Flash a success message.
     */
    public function withSuccess(string $message): static
    {
        return $this->withFlash('success', $message);
    }

    /**
     * Flash an error message.
     */
    public function withError(string $message): static
    {
        return $this->withFlash('error', $message);
    }

    /**
     * Flash a warning message.
     */
    public function withWarning(string $message): static
    {
        return $this->withFlash('warning', $message);
    }

    /**
     * Flash an info message.
     */
    public function withInfo(string $message): static
    {
        return $this->withFlash('info', $message);
    }

    /**
     * Flash the input data for form repopulation.
     * 
     * @param array<string, mixed>|null $input If null, flashes empty array
      * @param array<string, mixed> $input
     */
    public function withInput(?array $input = null): static
    {
        $this->ensureSession();
        $this->session->flash('_old_input', $input ?? []);
        return $this;
    }

    /**
     * Flash validation errors.
     * 
     * @param array<string, array<string>|string> $errors
      * @param array<string, array<string>> $errors
     */
    public function withErrors(array $errors): static
    {
        $this->ensureSession();
        $this->session->flash('errors', $errors);
        return $this;
    }

    /**
     * Ensure the session is set.
     * 
     * @throws RuntimeException If session is not set
     */
    protected function ensureSession(): void
    {
        if ($this->session === null) {
            throw new RuntimeException(
                'Session is not set. Call setSession() before using flash methods.'
            );
        }
    }
}
