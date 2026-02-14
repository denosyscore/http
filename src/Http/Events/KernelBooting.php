<?php

declare(strict_types=1);

namespace Denosys\Http\Events;

use Denosys\Container\Container;

readonly class KernelBooting
{
    public function __construct(private Container $container)
    {}

    public function getContainer(): Container
    {
        return $this->container;
    }
}
