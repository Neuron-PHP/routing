<?php

namespace Neuron\Routing\RateLimit;

use Neuron\Data\Setting\Source\ISettingSource;

/**
 * Configuration for rate limiting.
 *
 * Manages rate limit settings with support for environment variables
 * using flat configuration structure (category_name pattern).
 *
 * @package Neuron\Routing\RateLimit
 */
class RateLimitConfig
{
	private array $_Settings;

	/**
	 * @param array $Settings Configuration settings
	 */
	public function __construct( array $Settings = [] )
	{
		$this->_Settings = array_merge([
			'enabled' => false,
			'storage' => 'memory',
			'requests' => 100,
			'window' => 3600,
			'redis_host' => '127.0.0.1',
			'redis_port' => 6379,
			'redis_database' => 0,
			'redis_prefix' => 'rate_limit_',
			'redis_timeout' => 2.0,
			'redis_auth' => null,
			'redis_persistent' => false,
			'file_path' => 'cache/rate_limits',
			'key_prefix' => 'rl_',
			'burst_size' => 0
		], $Settings);
	}

	/**
	 * Create configuration from settings source.
	 *
	 * @param ISettingSource $Source
	 * @param string $Category Category to read settings from (default: 'rate_limit')
	 * @return self
	 */
	public static function fromSettings( ISettingSource $Source, string $Category = 'rate_limit' ): self
	{
		$Settings = [
			'enabled' => $Source->get( $Category, 'enabled' ) ?? false,
			'storage' => $Source->get( $Category, 'storage' ) ?? 'memory',
			'requests' => (int) ($Source->get( $Category, 'requests' ) ?? 100),
			'window' => (int) ($Source->get( $Category, 'window' ) ?? 3600),
			'redis_host' => $Source->get( $Category, 'redis_host' ) ?? '127.0.0.1',
			'redis_port' => (int) ($Source->get( $Category, 'redis_port' ) ?? 6379),
			'redis_database' => (int) ($Source->get( $Category, 'redis_database' ) ?? 0),
			'redis_prefix' => $Source->get( $Category, 'redis_prefix' ) ?? 'rate_limit_',
			'redis_timeout' => (float) ($Source->get( $Category, 'redis_timeout' ) ?? 2.0),
			'redis_auth' => $Source->get( $Category, 'redis_auth' ),
			'redis_persistent' => (bool) ($Source->get( $Category, 'redis_persistent' ) ?? false),
			'file_path' => $Source->get( $Category, 'file_path' ) ?? 'cache/rate_limits',
			'key_prefix' => $Source->get( $Category, 'key_prefix' ) ?? 'rl_',
			'burst_size' => (int) ($Source->get( $Category, 'burst_size' ) ?? 0)
		];

		return new self( $Settings );
	}

	/**
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return (bool) $this->_Settings['enabled'];
	}

	/**
	 * @return string Storage type (redis, file, memory)
	 */
	public function getStorage(): string
	{
		return $this->_Settings['storage'];
	}

	/**
	 * @return int Maximum requests allowed
	 */
	public function getLimit(): int
	{
		return (int) $this->_Settings['requests'];
	}

	/**
	 * @return int Time window in seconds
	 */
	public function getWindow(): int
	{
		return (int) $this->_Settings['window'];
	}

	/**
	 * @return int Burst size for token bucket algorithm
	 */
	public function getBurstSize(): int
	{
		return (int) $this->_Settings['burst_size'];
	}

	/**
	 * @return string Key prefix for storage
	 */
	public function getKeyPrefix(): string
	{
		return $this->_Settings['key_prefix'];
	}

	/**
	 * @return string Redis host
	 */
	public function getRedisHost(): string
	{
		return $this->_Settings['redis_host'];
	}

	/**
	 * @return int Redis port
	 */
	public function getRedisPort(): int
	{
		return (int) $this->_Settings['redis_port'];
	}

	/**
	 * @return int Redis database number
	 */
	public function getRedisDatabase(): int
	{
		return (int) $this->_Settings['redis_database'];
	}

	/**
	 * @return string Redis key prefix
	 */
	public function getRedisPrefix(): string
	{
		return $this->_Settings['redis_prefix'];
	}

	/**
	 * @return float Redis connection timeout
	 */
	public function getRedisTimeout(): float
	{
		return (float) $this->_Settings['redis_timeout'];
	}

	/**
	 * @return ?string Redis authentication password
	 */
	public function getRedisAuth(): ?string
	{
		return $this->_Settings['redis_auth'];
	}

	/**
	 * @return bool Use persistent Redis connections
	 */
	public function getRedisPersistent(): bool
	{
		return (bool) $this->_Settings['redis_persistent'];
	}

	/**
	 * @return string File storage path
	 */
	public function getFilePath(): string
	{
		return $this->_Settings['file_path'];
	}

	/**
	 * Get Redis configuration as array.
	 *
	 * @return array
	 */
	public function getRedisConfig(): array
	{
		return [
			'host' => $this->getRedisHost(),
			'port' => $this->getRedisPort(),
			'database' => $this->getRedisDatabase(),
			'prefix' => $this->getRedisPrefix(),
			'timeout' => $this->getRedisTimeout(),
			'auth' => $this->getRedisAuth(),
			'persistent' => $this->getRedisPersistent()
		];
	}

	/**
	 * Get all settings as array.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return $this->_Settings;
	}
}