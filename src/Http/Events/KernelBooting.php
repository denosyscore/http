<?php

declare(strict_types=1);

namespace CFXP\Core\Http\Events;

use CFXP\Core\Container\Container;

readonly class KernelBooting
{
    public function __construct(private Container $container)
    {}

    public function getContainer(): Container
    {
        return $this->container;
    }
}
