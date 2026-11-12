# denosyscore/http

> **Read-only split.** This package is developed in [denosyscore/framework](https://github.com/denosyscore/framework) under `packages/http`. Open issues and pull requests there.

HTTP kernel, middleware, and exception handling

## Installation

```bash
composer require denosyscore/http
```

## Quick start

PSR-7 responses and PSR-15 middleware. The response factory and middleware below need no container.

<!-- quickstart -->
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Denosys\Http\Middleware\JsonBodyParserMiddleware;
use Denosys\Http\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$responses = new ResponseFactory();

// A PSR-15 handler that reads the parsed JSON body.
$handler = new class ($responses) implements RequestHandlerInterface {
    public function __construct(private ResponseFactory $responses) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $order = $request->getParsedBody();

        return $this->responses->json(['received' => $order['sku']], 201);
    }
};

$request = (new ServerRequest([], [], 'https://shop.test/orders', 'POST'))
    ->withHeader('Content-Type', 'application/json')
    ->withBody((new StreamFactory())->createStream('{"sku":"sku-1"}'));

$response = (new JsonBodyParserMiddleware())->process($request, $handler);
echo $response->getStatusCode(), ' ', $response->getBody(), "\n";  // 201 {"received":"sku-1"}

// back() only follows a Referer on the request's own origin.
$back = $responses->back($request->withHeader('Referer', 'https://evil.example/'), '/orders');
echo $back->getHeaderLine('Location'), "\n";  // /orders
```

## Included Modules

- src/Http/*
- src/Exceptions/*
- src/Security/*

## PSR response security headers

The HTTP kernel attaches the existing default security policy to every
returned PSR-7 response before dispatching `ResponseReady`. It sets
`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and the existing
`X-XSS-Protection` default. HTTPS requests also receive
`Strict-Transport-Security`. Explicit response headers take precedence over
these defaults.

The existing `SecurityHeadersServiceProvider` remains available for consumers
that rely on native SAPI headers. Direct kernel handling and alternate emitters
receive the policy through the returned response object.

## JSON request bodies

`Denosys\Http\Middleware\JsonBodyParserMiddleware` is an opt-in PSR-15
middleware. Register it before CSRF checks and request validation so JSON
object and array bodies become available through `getParsedBody()` and
`Denosys\Http\Request::input()`. It accepts `application/json` (including
charset parameters) and `application/*+json` media types.

The middleware leaves non-JSON and already populated parsed bodies unchanged.
An empty JSON body is left for the application's normal validation rules;
malformed or scalar JSON returns a JSON `400` response. Seekable request-body
streams retain their cursor position after parsing. An unreadable request-body
stream returns a JSON `500` response rather than being treated as empty input.

## Validation failure redirects

`Denosys\Http\Middleware\ValidationExceptionMiddleware` requires the session
interface supplied by `denosyscore/session`. It flashes field errors under
`errors` and non-sensitive old input under `_old_input`, the key read by
`Denosys\Http\Request::old()`. Return URLs from the `Referer` header or
session are accepted only when they are local paths or match the request's
scheme, host, and port; otherwise the redirect falls back to `/`.

Validation failures redirect with `302` by default. The optional second
constructor argument accepts a callable that receives the PSR-7 request and
returns a redirect status code. For example, an application can return `303`
for its chosen mutation requests while leaving other requests at `302`.
`RedirectResponse` validates the returned status code. Applications can
register a different exception middleware when they need a JSON error
response or a different return-URL policy.

`Denosys\Http\Exceptions\ValidationExceptionHandler` is the HTTP-owned
exception-chain adapter for validation errors. It returns JSON or HTML with
status 422. The former
`Denosys\Validation\Handlers\ValidationExceptionHandler` name remains an
alias when HTTP is installed, so existing integrations can migrate without
changing behavior. Standalone validation has no HTTP response adapter.

## Browser XSRF cookie

`Denosys\Http\Middleware\XsrfCookieMiddleware` is an opt-in PSR-15 companion
to cookie-to-header CSRF clients. Supply a callback that returns the current
session's CSRF token and register the middleware after session startup. It
adds a host-only, browser-readable `XSRF-TOKEN` cookie with `Path=/` and
`SameSite=Lax`; `Secure` is set for HTTPS requests by default. Existing
`Set-Cookie` headers remain intact. An empty token emits no cookie. The
token callback keeps this HTTP package independent of any particular session
implementation. The cookie lifetime, name, and secure policy are configurable.

## Routing HTTP errors

When `denosyscore/routing` is installed, `HttpExceptionMiddleware` turns its
typed routing exceptions into PSR responses. Method-not-allowed responses
include `Allow` with the methods reported by the router. The routing package
is an optional integration for standalone HTTP consumers.

## Development

```bash
composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test
```

The session middleware has a separate integration root so its real session
interface can be installed without adding a dependency cycle to this package:

```bash
cd integration/session
composer install
php session-middleware.php
```


## Releases

CI tests against validation v0.2.2 and v0.3.0; the isolated session test
uses validation v0.3.3. After CI passes on `main`,
create an annotated semantic version tag from that reviewed commit and push
it. The Release workflow publishes a GitHub Release. Confirm the matching
version is available in the Composer package index before updating consumers.
