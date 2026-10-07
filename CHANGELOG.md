# Changelog

## Unreleased

- Add PSR-15 security-header middleware so immutable responses carry the
  existing default policy across SAPIs, without replacing explicit headers.
- Declare the PSR HTTP message and server interface dependencies used by the
  package directly.
- Add package-level HTTP tests and run them in CI. The new public middleware
  is intended for the next backward-compatible minor release.
