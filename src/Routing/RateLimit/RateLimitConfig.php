<?php

namespace Neuron\Routing\RateLimit;

use Neuron\Data\Settings\Source\ISettingSource;

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
	private array $_settings;

	/**
	 * @param array $settings Configuration settings
	 */
	public function __construct( array $settings = [] )
	{
		$this->_settings = array_merge([
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
		], $settings);
	}

	/**
	 * Create configuration from settings source.
	 *
	 * @param ISettingSource $source
	 * @param string $category Category to read settings from (default: 'rate_limit')
	 * @return self
	 */
	public static function fromSettings( ISettingSource $source, string $category = 'rate_limit' ): self
	{
		$settings = [
			'enabled' => $source->get( $category, 'enabled' ) ?? false,
			'storage' => $source->get( $category, 'storage' ) ?? 'memory',
			'requests' => (int) ($source->get( $category, 'requests' ) ?? 100),
			'window' => (int) ($source->get( $category, 'window' ) ?? 3600),
			'redis_host' => $source->get( $category, 'redis_host' ) ?? '127.0.0.1',
			'redis_port' => (int) ($source->get( $category, 'redis_port' ) ?? 6379),
			'redis_database' => (int) ($source->get( $category, 'redis_database' ) ?? 0),
			'redis_prefix' => $source->get( $category, 'redis_prefix' ) ?? 'rate_limit_',
			'redis_timeout' => (float) ($source->get( $category, 'redis_timeout' ) ?? 2.0),
			'redis_auth' => $source->get( $category, 'redis_auth' ),
			'redis_persistent' => (bool) ($source->get( $category, 'redis_persistent' ) ?? false),
			'file_path' => $source->get( $category, 'file_path' ) ?? 'cache/rate_limits',
			'key_prefix' => $source->get( $category, 'key_prefix' ) ?? 'rl_',
			'burst_size' => (int) ($source->get( $category, 'burst_size' ) ?? 0)
		];

		return new self( $settings );
	}

	/**
	 * @return bool
	 */
	public function isEnabled(): bool
	{
		return (bool) $this->_settings['enabled'];
	}

	/**
	 * @return string Storage type (redis, file, memory)
	 */
	public function getStorage(): string
	{
		return $this->_settings['storage'];
	}

	/**
	 * @return int Maximum requests allowed
	 */
	public function getLimit(): int
	{
		return (int) $this->_settings['requests'];
	}

	/**
	 * @return int Time window in seconds
	 */
	public function getWindow(): int
	{
		return (int) $this->_settings['window'];
	}

	/**
	 * @return int Burst size for token bucket algorithm
	 */
	public function getBurstSize(): int
	{
		return (int) $this->_settings['burst_size'];
	}

	/**
	 * @return string Key prefix for storage
	 */
	public function getKeyPrefix(): string
	{
		return $this->_settings['key_prefix'];
	}

	/**
	 * @return string Redis host
	 */
	public function getRedisHost(): string
	{
		return $this->_settings['redis_host'];
	}

	/**
	 * @return int Redis port
	 */
	public function getRedisPort(): int
	{
		return (int) $this->_settings['redis_port'];
	}

	/**
	 * @return int Redis database number
	 */
	public function getRedisDatabase(): int
	{
		return (int) $this->_settings['redis_database'];
	}

	/**
	 * @return string Redis key prefix
	 */
	public function getRedisPrefix(): string
	{
		return $this->_settings['redis_prefix'];
	}

	/**
	 * @return float Redis connection timeout
	 */
	public function getRedisTimeout(): float
	{
		return (float) $this->_settings['redis_timeout'];
	}

	/**
	 * @return ?string Redis authentication password
	 */
	public function getRedisAuth(): ?string
	{
		return $this->_settings['redis_auth'];
	}

	/**
	 * @return bool Use persistent Redis connections
	 */
	public function getRedisPersistent(): bool
	{
		return (bool) $this->_settings['redis_persistent'];
	}

	/**
	 * @return string File storage path
	 */
	public function getFilePath(): string
	{
		return $this->_settings['file_path'];
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
		return $this->_settings;
	}
}
