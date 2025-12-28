## 0.8.9 2025-12-27
* Added attribute based route definitions.

## 0.8.8 2025-12-27
* Added the ability to add multiple filters to routes.

## 0.8.7 2025-12-11
* **Rate limit storage classes now use system abstractions** - FileRateLimitStorage and MemoryRateLimitStorage refactored to use `IClock` interface
* Added `neuron-php/core` 0.8.* dependency for system abstractions
* Rate limit storage supports dependency injection with optional `IClock` parameter for testability
* Tests updated to use `FrozenClock` for instant, deterministic time-based testing
* **Test performance improvement: 118x faster** - FileRateLimitStorageTest now runs in 0.088s instead of ~9s (eliminated sleep() calls)
* Maintains full backward compatibility - existing code works without changes

## 0.8.6 2025-11-28

## 0.8.5 2025-11-27
* Added wildcard support for route parameters.

## 0.8.4 2025-11-24

## 0.8.3 2025-11-12
* Updated to use the IPResolver for rate limiting.

## 0.8.2 2025-11-11

## 0.8.1 2025-11-07

* Added comprehensive rate limiting system with multiple storage backends.
* Added RateLimitFilter for request throttling.
* Added Redis, File, and Memory storage implementations for rate limiting.
* Added configurable rate limiting with environment variable support (flat structure).
* Added whitelisting and blacklisting support for rate limiting.
* Added automatic rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset).
* Added HTTP 429 response formatting with JSON/HTML content negotiation.
* Added comprehensive tests for rate limiting functionality.
* Added IPResolver.

## 0.8.0 2025-11-04
## 0.6.9 2025-05-21

## 0.6.8 2025-02-18
* Updated components.
* Refactoring.
* Improved tests.
* Added additional uri cleanup.

## 0.6.7 2025-02-06
## 0.6.6 2025-01-27

## 0.6.5 2025-11-04
## 0.6.4 2025-12-11
## 0.6.3 2024-11-27
* Updated route signatures.
* Added logging.

## 0.6.2 2024-11-27
* Updated dependencies.

## 0.6.1
* Scheduled release.

## 0.5.6 2022-04-04
* Scheduled release

## 0.5.5 2022-03-31
* Updated logger version.

## 0.5.4
* Updated 404 route.

## 0.5.3 2020-08-21
* Added Payload to RouteMap to enable passing extra route specific data to the lambda.

## 0.5.2 2020-08-20
* Refactored for php 74.

## 0.5.1 2020-08-19
* Updates to support changes in dependencies.
* Initial version.
