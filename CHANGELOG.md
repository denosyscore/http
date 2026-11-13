# Changelog

## 0.18.0 – 2026-11-13

- Released with the framework's 0.18 line. Requires its DenoSys dependencies
  at `^0.18`; no other changes.

## 0.17.0 – 2026-11-12

- Released with the framework's 0.17 line. Requires its DenoSys dependencies
  at `^0.17`; no other changes.

## 0.16.0 – 2026-11-12

- Released with the framework's 0.16 line; no changes to this package.

## 0.15.0 – 2026-11-12

- Released with the framework's 0.15 line; no changes to this package.

## 0.14.0 – 2026-11-12

- **Breaking:** `Events\KernelBooting` and `Events\KernelBooted` are removed.
  The framework now boots once for HTTP and the console, and nothing
  listened for them.

## 0.13.0 – 2026-11-12

- Released with the framework's 0.13 line; no changes to this package.

## 0.12.0 – 2026-11-12

- **Breaking:** `ValidationExceptionHandler` takes `bool $debug` in its
  constructor; `ExceptionHandlerTrait::isDebugMode()`, which read `APP_DEBUG`
  from the process environment, is removed.

## 0.11.1 – 2026-11-12

### Fixed

- Requirements on other DenoSys packages are `^0.11`. In 0.11.0 they were
  still `^0.10`, which for a 0.x version allows only 0.10 releases, so 0.11.0
  packages resolved their siblings to 0.10 or could not be installed. Use
  0.11.1 instead of 0.11.0.

## 0.11.0 – 2026-11-12

- **Breaking:** `Kernel` moved to `Denosys\Framework\Http\Kernel`; `ThrottleRequestsMiddleware` moved to rate-limiter; providers moved to the framework.
- **Breaking:** `ResponseFactory` uses `TemplateRenderer` (`setTemplateRenderer()`, `getTemplateRenderer()`).
- **Security:** fallback 401/403/404/500 pages escape their message.
- Requires routing, session, `filp/whoops`, `psr/log`.

## 0.10.0 – 2026-11-12

### Changed

- Released from the [denosyscore/framework](https://github.com/denosyscore/framework)
  monorepo, where this package now lives under `packages/http`. All DenoSys
  packages share one version line, so the version moves from v0.4.0 to
  0.10.0. There are no API changes in this release.
- Requirements on other DenoSys packages are `^0.10`.

## 0.4.0 – 2026-11-11

### Removed (breaking)

- Copies of the container exceptions under `Denosys\Http\Exceptions`:
  `BindingConflictException`, `CircularDependencyException`,
  `ContainerException`, `ContainerExceptionFactory`,
  `ContainerResolutionException` and `NotFoundException`. The container
  never threw them; catch the `Denosys\Container\Exceptions` classes or the
  PSR-11 interfaces instead.
- Unused `DatabaseException`, `HandlerException`, `ItemNotFoundException`,
  `NoConfigFileSampleException` and `WrongValueException`.
- `Denosys\Http\Listeners\ScreamBooting`, a debugging listener that called
  `dd()`.
- `VerifyCsrfToken::getRefererUrl()`, which nothing called.

### Changed

- The development error page reports the installed `denosyscore/http`
  version instead of a placeholder `1.0.0`.

## 0.3.3 – 2026-11-11

### Security

- `ResponseFactory::back()` and `RateLimitExceptionMiddleware` only redirect
  to a `Referer` on the request's own origin; otherwise they use the fallback.
  Both previously followed any `Referer`, an open redirect.
- Add `SameOriginUrl::resolve()` and `SameOriginUrl::isSameOrigin()`, the
  check `ValidationExceptionMiddleware` already used, so every redirect-back
  path shares one rule.

- Test the optional session middleware against the installed session package
  in an isolated Composer project, without adding a runtime dependency cycle.

## 0.3.2

- Own the validation exception response handler on the HTTP side of the
  dependency and retain the former validation namespace as an alias when HTTP
  is installed.

## 0.3.1

- Include the required `Allow` header when routing method-not-allowed
  exceptions become HTTP 405 responses. Add standalone integration coverage
  with the optional routing package.

## 0.3.0

- Accept validation 0.3 while retaining the supported 0.2 line.

- Register the JSON Whoops handler for XHR and JSON requests in local debug
  mode, and set page titles only for HTML debug responses. This prevents a
  fatal error while bootstrapping non-HTML requests.
- Add opt-in XSRF cookie middleware backed by a token callback, without a
  required session dependency (compatible additive API).
- Attach the existing security-header policy to immutable responses returned
  by the HTTP kernel across SAPIs, without replacing explicit headers.
- Declare the PSR HTTP message and server interface dependencies used by the
  package directly.
- Add package-level HTTP tests and run them in CI. The opt-in XSRF middleware
  is an additive API proposed for the next 0.3 release.
