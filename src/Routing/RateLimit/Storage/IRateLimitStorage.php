<?php

namespace Neuron\Routing\RateLimit\Storage;

/**
 * Interface for rate limit storage implementations.
 *
 * Provides a consistent API for tracking and enforcing rate limits
 * across different storage backends (Redis, File, Memory, etc).
 *
 * @package Neuron\Routing\RateLimit\Storage
 */
interface IRateLimitStorage
{
	/**
	 * Check if a request is allowed under the rate limit.
	 *
	 * @param string $key The unique identifier for the rate limit (e.g., IP address, user ID)
	 * @param int $limit Maximum number of requests allowed in the window
	 * @param int $window Time window in seconds
	 * @return bool True if request is allowed, false if rate limit exceeded
	 */
	public function allow( string $key, int $limit, int $window ): bool;

	/**
	 * Get the number of remaining attempts for a key.
	 *
	 * @param string $key The unique identifier for the rate limit
	 * @param int $limit Maximum number of requests allowed
	 * @param int $window Time window in seconds
	 * @return int Number of remaining attempts
	 */
	public function getRemainingAttempts( string $key, int $limit, int $window ): int;

	/**
	 * Get the timestamp when the rate limit window resets.
	 *
	 * @param string $key The unique identifier for the rate limit
	 * @param int $window Time window in seconds
	 * @return int Unix timestamp of reset time
	 */
	public function getResetTime( string $key, int $window ): int;

	/**
	 * Reset the rate limit counter for a specific key.
	 *
	 * @param string $key The unique identifier to reset
	 * @return void
	 */
	public function reset( string $key ): void;

	/**
	 * Clear all rate limit data (useful for testing).
	 *
	 * @return void
	 */
	public function clear(): void;
}