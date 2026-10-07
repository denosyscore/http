<?php

declare(strict_types=1);

namespace Denosys\Session;

// The optional session package depends on HTTP, so it cannot be installed as
// a development dependency of the root HTTP package. These are the methods
// this middleware consumes from its collaborator.
interface SessionInterface
{
    public function flash(string $key, mixed $value): void;

    public function previousUrl(): ?string;
}
