<?php

declare(strict_types=1);

namespace CFXP\Core\Http;

use Throwable;
use Denosys\Routing\Router;
use CFXP\Core\Events\Dispatcher;
use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\Events\ResponseReady;
use CFXP\Core\Events\RequestHandling;
use CFXP\Core\Events\ListenerProvider;
use CFXP\Core\Http\Events\KernelBooted;
use Psr\Http\Message\ResponseInterface;
use CFXP\Core\Http\Events\KernelBooting;
use Laminas\Diactoros\ServerRequestFactory;
use CFXP\Core\Exceptions\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use CFXP\Core\Exceptions\ContainerException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use CFXP\Core\Bootstrap\ServiceProviderBootstrapper;
use CFXP\Core\Exceptions\ContainerResolutionException;
use CFXP\Core\Exceptions\ExceptionHandler;

class Kernel
{
    private Dispatcher $dispatcher;
    private ExceptionHandler $exceptionHandler;
    private bool $booted = false;

    public function __construct(
        protected readonly ContainerInterface $container,
        private readonly ServiceProviderBootstrapper $bootstrapper
    ) {
        // Create the single event dispatcher instance
        $this->dispatcher = new Dispatcher(new ListenerProvider());
        
        // Register it in container BEFORE providers bootstrap
        // This ensures all providers and services use the same instance
        $this->container->instance(Dispatcher::class, $this->dispatcher);
        $this->container->instance(EventDispatcherInterface::class, $this->dispatcher);
        $this->container->alias('events', EventDispatcherInterface::class);
    }

    /**
     * Bootstrap the application services.
     *
     * @throws ContainerResolutionException
    */
    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        // Dispatch booting event
        $this->dispatcher->dispatch(new KernelBooting($this->container));

        // Bootstrap all services via the bootstrapper
        // Note: Event dispatcher is already in container from constructor
        $this->bootstrapper->bootstrap($this->dispatcher);

        // Get exception handler from container (configured by ExceptionHandlerServiceProvider)
        $this->exceptionHandler = $this->container->get('exception.handler');

        $this->booted = true;

        // Dispatch booted event
        $this->dispatcher->dispatch(new KernelBooted($this->container));
    }

    /**
     * Handle HTTP request.
     *
     * @throws ContainerResolutionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function handle(?ServerRequestInterface $request = null): ResponseInterface
    {
        $this->bootstrap();

        // Create request if not provided
        if ($request === null) {
            $request = ServerRequestFactory::fromGlobals();
        }

        // Wrap request with enhanced Request if not already wrapped
        if (!$request instanceof Request) {
            $request = Request::createFromPsr7($request);
        }

        // Bind request in container
        $this->container->instance(ServerRequestInterface::class, $request);
        $this->container->alias('request', ServerRequestInterface::class);

        // Dispatch request handling event
        $this->dispatcher->dispatch(new RequestHandling($request));

        // Get router and dispatch request
        /** @var Router $router */
        $router = $this->container->get(Router::class);
        $response = $router->dispatch($request);

        // Dispatch response ready event
        $this->dispatcher->dispatch(new ResponseReady($response));

        return $response;
    }

    /**
     * Emit response to client.
     */
    public function emit(ResponseInterface $response): void
    {
        (new SapiEmitter())->emit($response);
    }

    /**
     * Complete request handling - handle and emit.
     *
     * @throws ContainerResolutionException
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $response = $this->handle($request);
        $this->emit($response);
    }

    /**
     * Get the container instance.
     *
     * @throws ContainerResolutionException
     */
    public function getContainer(): ContainerInterface
    {
        $this->bootstrap();
        return $this->container;
    }

    /**
     * Get the event dispatcher.
     */
    public function getEventDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    /**
     * Get the exception handler chain (available after bootstrap).
     *
     * @throws ContainerResolutionException
     */
    public function getExceptionHandler(): ExceptionHandler
    {
        $this->bootstrap();

        return $this->exceptionHandler;
    }
}
