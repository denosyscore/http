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

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: Composer validation, isolated dependency installation, HTTP tests, and PHP syntax lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
