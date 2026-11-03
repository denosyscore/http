<?php

declare(strict_types=1);

use Denosys\Http\Middleware\ValidationExceptionMiddleware;
use Denosys\Session\SessionInterface;
use Denosys\Session\Store;
use Denosys\Validation\ValidationException;
use Denosys\Validation\Validator;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/vendor/autoload.php';

$session = new Store('integration', new SessionHandler());
if (!$session instanceof SessionInterface) {
    throw new RuntimeException('The installed session store must implement the real session interface.');
}

$request = (new ServerRequest([], [], 'https://example.test/submit', 'POST'))
    ->withHeader('Referer', 'https://example.test/form')
    ->withParsedBody(['email' => 'invalid', 'password' => 'secret']);

$handler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $validator = Validator::make([], []);
        $validator->validate();
        $validator->errors()->add('email', 'A valid email is required.');

        throw new ValidationException($validator);
    }
};

$response = (new ValidationExceptionMiddleware($session))->process($request, $handler);
$flashes = $session->getFlashAll();

if ($response->getStatusCode() !== 302
    || $response->getHeaderLine('Location') !== 'https://example.test/form'
    || ($flashes['errors'] ?? null) !== ['email' => ['A valid email is required.']]
    || ($flashes['_old_input'] ?? null) !== ['email' => 'invalid']) {
    throw new RuntimeException('The HTTP middleware did not flash and redirect through the installed session package.');
}

echo "Real session middleware integration passed.\n";
