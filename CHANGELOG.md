# Changelog

## Unreleased

- Attach the existing security-header policy to immutable responses returned
  by the HTTP kernel across SAPIs, without replacing explicit headers.
- Declare the PSR HTTP message and server interface dependencies used by the
  package directly.
- Add package-level HTTP tests and run them in CI. These corrections make no
  public API change and are intended for the next patch release.
