<?php

declare(strict_types=1);

namespace CFXP\Core\Http;

use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\ServiceProviderInterface;
use CFXP\Core\View\ViewEngine;
use CFXP\Core\Session\SessionInterface;
use CFXP\Core\Exceptions\NotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * HTTP Service Provider
 *
 * Registers HTTP-related services including the enhanced Request wrapper
 * and ResponseFactory for improved developer experience.
 */
class HttpServiceProvider implements ServiceProviderInterface
{
    /**
     * @throws NotFoundException
     */
    public function register(ContainerInterface $container): void
    {
        // Register ResponseFactory
        $container->singleton(ResponseFactory::class, function (ContainerInterface $container) {
            $viewEngine = null;

            // Try to get ViewEngine if available
            try {
                $viewEngine = $container->get(ViewEngine::class);
            } catch (\Exception $e) {
                // ViewEngine not available yet, will be set later if needed
            }

            return new ResponseFactory($viewEngine);
        });

        // Bind alias for easier access
        $container->alias('response', ResponseFactory::class);

        // Note: ServerRequestInterface extension is handled in Kernel::handle()
        // We don't extend it here because it's not bound during bootstrap
    }

    public function boot(ContainerInterface $container, ?EventDispatcherInterface $dispatcher = null): void
    {
        // Update ResponseFactory with ViewEngine and Session once available
        try {
            $responseFactory = $container->get(ResponseFactory::class);
            
            if ($container->has(ViewEngine::class)) {
                $responseFactory->setViewEngine($container->get(ViewEngine::class));
            }
            
            if ($container->has(SessionInterface::class)) {
                $responseFactory->setSession($container->get(SessionInterface::class));
            }
        } catch (\Exception $e) {
            // Services might not be available in all contexts
        }
    }
}
