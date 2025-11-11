<?php

namespace Neuron\Routing\RateLimit\Storage;

use Neuron\Routing\RateLimit\RateLimitConfig;
use Exception;

/**
 * Factory for creating rate limit storage instances.
 *
 * @package Neuron\Routing\RateLimit\Storage
 */
class RateLimitStorageFactory
{
	/**
	 * Create storage instance from configuration.
	 *
	 * @param RateLimitConfig $config
	 * @param string $basePath Base path for file storage
	 * @return IRateLimitStorage
	 * @throws Exception
	 */
	public static function create( RateLimitConfig $config, string $basePath = '' ): IRateLimitStorage
	{
		$storageType = $config->getStorage();

		switch( $storageType )
		{
			case 'redis':
				return self::createRedisStorage( $config );

			case 'file':
				return self::createFileStorage( $config, $basePath );

			case 'memory':
				return self::createMemoryStorage( $config );

			default:
				throw new Exception( "Unknown rate limit storage type: $storageType" );
		}
	}

	/**
	 * Create Redis storage instance.
	 *
	 * @param RateLimitConfig $config
	 * @return RedisRateLimitStorage
	 */
	private static function createRedisStorage( RateLimitConfig $config ): RedisRateLimitStorage
	{
		return new RedisRateLimitStorage( $config->getRedisConfig() );
	}

	/**
	 * Create file storage instance.
	 *
	 * @param RateLimitConfig $config
	 * @param string $basePath
	 * @return FileRateLimitStorage
	 */
	private static function createFileStorage( RateLimitConfig $config, string $basePath ): FileRateLimitStorage
	{
		$path = $config->getFilePath();

		// Make path absolute if relative
		if( !str_starts_with( $path, '/' ) && $basePath )
		{
			$path = $basePath . '/' . $path;
		}

		return new FileRateLimitStorage([
			'path' => $path,
			'prefix' => $config->getKeyPrefix()
		]);
	}

	/**
	 * Create memory storage instance.
	 *
	 * @param RateLimitConfig $config
	 * @return MemoryRateLimitStorage
	 */
	private static function createMemoryStorage( RateLimitConfig $config ): MemoryRateLimitStorage
	{
		return new MemoryRateLimitStorage([
			'prefix' => $config->getKeyPrefix()
		]);
	}
}