<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{
}
