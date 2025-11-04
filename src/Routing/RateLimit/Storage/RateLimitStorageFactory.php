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
	 * @param RateLimitConfig $Config
	 * @param string $BasePath Base path for file storage
	 * @return IRateLimitStorage
	 * @throws Exception
	 */
	public static function create( RateLimitConfig $Config, string $BasePath = '' ): IRateLimitStorage
	{
		$storageType = $Config->getStorage();

		switch( $storageType )
		{
			case 'redis':
				return self::createRedisStorage( $Config );

			case 'file':
				return self::createFileStorage( $Config, $BasePath );

			case 'memory':
				return self::createMemoryStorage( $Config );

			default:
				throw new Exception( "Unknown rate limit storage type: $storageType" );
		}
	}

	/**
	 * Create Redis storage instance.
	 *
	 * @param RateLimitConfig $Config
	 * @return RedisRateLimitStorage
	 */
	private static function createRedisStorage( RateLimitConfig $Config ): RedisRateLimitStorage
	{
		return new RedisRateLimitStorage( $Config->getRedisConfig() );
	}

	/**
	 * Create file storage instance.
	 *
	 * @param RateLimitConfig $Config
	 * @param string $BasePath
	 * @return FileRateLimitStorage
	 */
	private static function createFileStorage( RateLimitConfig $Config, string $BasePath ): FileRateLimitStorage
	{
		$path = $Config->getFilePath();

		// Make path absolute if relative
		if( !str_starts_with( $path, '/' ) && $BasePath )
		{
			$path = $BasePath . '/' . $path;
		}

		return new FileRateLimitStorage([
			'path' => $path,
			'prefix' => $Config->getKeyPrefix()
		]);
	}

	/**
	 * Create memory storage instance.
	 *
	 * @param RateLimitConfig $Config
	 * @return MemoryRateLimitStorage
	 */
	private static function createMemoryStorage( RateLimitConfig $Config ): MemoryRateLimitStorage
	{
		return new MemoryRateLimitStorage([
			'prefix' => $Config->getKeyPrefix()
		]);
	}
}