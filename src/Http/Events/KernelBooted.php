<?php

declare(strict_types=1);

namespace Denosys\Http\Events;

use Denosys\Container\Container;

readonly class KernelBooted
{
    public function __construct(
        public Container $container
    ) {}
}
