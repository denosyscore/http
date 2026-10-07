# denosyscore/http

HTTP kernel, middleware, and exception handling

## Status

Initial extraction snapshot from denosyscore monorepo as of 2026-02-14.

## Installation

composer require denosyscore/http

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

## Browser XSRF cookie

`Denosys\Http\Middleware\XsrfCookieMiddleware` is an opt-in PSR-15 companion
to cookie-to-header CSRF clients. Supply a callback that returns the current
session's CSRF token and register the middleware after session startup. It
adds a host-only, browser-readable `XSRF-TOKEN` cookie with `Path=/` and
`SameSite=Lax`; `Secure` is set for HTTPS requests by default. Existing
`Set-Cookie` headers remain intact. An empty token emits no cookie. The
token callback keeps this HTTP package independent of any particular session
implementation. The cookie lifetime, name, and secure policy are configurable.

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: Composer validation, isolated dependency installation, HTTP tests, and PHP syntax lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
