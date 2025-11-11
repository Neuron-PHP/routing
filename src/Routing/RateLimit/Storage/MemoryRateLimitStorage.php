<?php

namespace Neuron\Routing\RateLimit\Storage;

/**
 * In-memory rate limit storage for testing.
 *
 * WARNING: This storage backend is only suitable for testing as data
 * is lost when the PHP process terminates. Use Redis or File storage
 * for production deployments.
 *
 * @package Neuron\Routing\RateLimit\Storage
 */
class MemoryRateLimitStorage implements IRateLimitStorage
{
	private array $_storage = [];
	private string $_prefix;

	/**
	 * @param array $config Configuration options
	 */
	public function __construct( array $config = [] )
	{
		$this->_prefix = $config['prefix'] ?? 'rl_';
	}

	/**
	 * @inheritDoc
	 */
	public function allow( string $key, int $limit, int $window ): bool
	{
		$fullKey = $this->_prefix . $key;
		$now = time();
		$windowStart = $now - $window;

		// Initialize or get existing data
		if( !isset( $this->_storage[$fullKey] ) )
		{
			$this->_storage[$fullKey] = [
				'attempts' => [],
				'window_start' => $now
			];
		}

		$data = &$this->_storage[$fullKey];

		// Remove expired attempts
		$data['attempts'] = array_filter(
			$data['attempts'],
			function( $timestamp ) use ( $windowStart ) {
				return $timestamp > $windowStart;
			}
		);

		// Check if limit exceeded
		if( count( $data['attempts'] ) >= $limit )
		{
			return false;
		}

		// Add current attempt
		$data['attempts'][] = $now;
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getRemainingAttempts( string $key, int $limit, int $window ): int
	{
		$fullKey = $this->_prefix . $key;
		$now = time();
		$windowStart = $now - $window;

		if( !isset( $this->_storage[$fullKey] ) )
		{
			return $limit;
		}

		$data = $this->_storage[$fullKey];

		// Count non-expired attempts
		$activeAttempts = array_filter(
			$data['attempts'],
			function( $timestamp ) use ( $windowStart ) {
				return $timestamp > $windowStart;
			}
		);

		$remaining = $limit - count( $activeAttempts );
		return max( 0, $remaining );
	}

	/**
	 * @inheritDoc
	 */
	public function getResetTime( string $key, int $window ): int
	{
		$fullKey = $this->_prefix . $key;

		if( !isset( $this->_storage[$fullKey] ) || empty( $this->_storage[$fullKey]['attempts'] ) )
		{
			return time() + $window;
		}

		// Find the oldest attempt in the current window
		$oldestAttempt = min( $this->_storage[$fullKey]['attempts'] );
		return $oldestAttempt + $window;
	}

	/**
	 * @inheritDoc
	 */
	public function reset( string $key ): void
	{
		$fullKey = $this->_prefix . $key;
		unset( $this->_storage[$fullKey] );
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): void
	{
		$this->_storage = [];
	}

	/**
	 * Get current storage state (for testing).
	 *
	 * @return array
	 */
	public function getStorage(): array
	{
		return $this->_storage;
	}
}