<?php

declare(strict_types=1);

namespace CFXP\Core\Http\Events;

use CFXP\Core\Container\Container;

readonly class KernelBooted
{
    public function __construct(
        public Container $container
    ) {}
}
