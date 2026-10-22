# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Contract`, `Cookie`, `Identity`, `Permissions`, `CloudApi`, `CloudUser`, `Cache\Cache` with
  `Cache\ArrayCache`, `Http\Transport` with `Http\CurlTransport`.
- `Cookie::expires()` (the expiry as `DateTimeImmutable`, null for a deletion), `Cookie::$domain`
  null for host-only cookies, `Permissions::fromArray()` accepting a missing or malformed value as
  "no permissions" — shaped by the integration into Domotron Cloud.
- Unit tests for everything and an integration test of the curl transport.

### Changed

- Successor of `domotron/cloud-user` 1.5 and `domotron/cloud-client` 1.5, merged into one package
  with a new API: no framework coupling, no Guzzle, no PHP superglobals (the application passes the
  cookie in and sends cookies out), identity cache bound to the session key, only the five user
  endpoints kept. The market/object/project/partner/address/agreement endpoints and DTOs of
  `cloud-client` were unused and are gone.
