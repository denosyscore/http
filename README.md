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

Register `Denosys\Http\Middleware\SecurityHeadersMiddleware` in the global
PSR-15 middleware stack to attach default security headers to every returned
PSR-7 response. It sets `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, and the existing `X-XSS-Protection` default. HTTPS
requests also receive `Strict-Transport-Security`. Explicit response headers
take precedence over these defaults.

The existing `SecurityHeadersServiceProvider` remains available for consumers
that rely on native SAPI headers. Framework integration should use the
middleware so direct response handling and alternate emitters receive the
same policy.

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: Composer validation, isolated dependency installation, HTTP tests, and PHP syntax lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
