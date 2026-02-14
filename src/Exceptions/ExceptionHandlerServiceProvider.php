<?php

declare(strict_types=1);

namespace Denosys\Http\Exceptions;

use Denosys\Container\ContainerInterface;
use Denosys\Contracts\ServiceProviderInterface;
use Denosys\Bootstrap\Configuration\ExceptionConfiguration;
use Psr\Log\LoggerInterface;
use Denosys\Environment\EnvironmentManager;
use Psr\EventDispatcher\EventDispatcherInterface;

class ExceptionHandlerServiceProvider implements ServiceProviderInterface
{
    /**
     * @throws NotFoundException
     * @throws ContainerResolutionException
     */
    public function register(ContainerInterface $container): void
    {
        $logger = $container->get(LoggerInterface::class);

        /** @var EnvironmentManager $environmentManager */
        $environmentManager = $container->get(EnvironmentManager::class);
        $debug = $environmentManager->get('APP_DEBUG') ?? false;
        $environment = $environmentManager->get('APP_ENV') ?? 'production';

        $exceptionHandler = new ExceptionHandler(
            $container->get('path.base'),
            $logger,
            $debug,
            $environment
        );
        $exceptionHandler->register();

        $container->instance(ExceptionHandler::class, $exceptionHandler);
        $container->alias('exception.handler', ExceptionHandler::class);
    }

    /**
     * @throws ContainerResolutionException
     */
    public function boot(ContainerInterface $container, ?EventDispatcherInterface $dispatcher = null): void
    {
        $this->applyExceptionConfiguration($container);
    }

    /**
     * @throws ContainerResolutionException
     */
    private function applyExceptionConfiguration(ContainerInterface $container): void
    {
        if (!$container->has(ExceptionConfiguration::class)) {
            return;
        }

        $config = $container->get(ExceptionConfiguration::class);
        $handler = $container->get(ExceptionHandler::class);
        $config->apply($handler);
    }
}
