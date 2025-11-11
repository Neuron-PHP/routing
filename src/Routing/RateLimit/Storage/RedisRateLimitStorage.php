<?php

namespace Neuron\Routing\RateLimit\Storage;

use Redis;
use RedisException;
use Neuron\Log\Log;

/**
 * Redis-based rate limit storage for production use.
 *
 * Uses Redis sorted sets with sliding window algorithm for accurate
 * rate limiting across distributed systems.
 *
 * @package Neuron\Routing\RateLimit\Storage
 */
class RedisRateLimitStorage implements IRateLimitStorage
{
	private ?Redis $_redis = null;
	private array $_config;
	private string $_prefix;

	/**
	 * @param array $config Redis configuration
	 */
	public function __construct( array $config = [] )
	{
		$this->_config = array_merge([
			'host' => '127.0.0.1',
			'port' => 6379,
			'database' => 0,
			'prefix' => 'rate_limit_',
			'timeout' => 2.0,
			'auth' => null,
			'persistent' => false
		], $config);

		$this->_prefix = $this->_config['prefix'];
	}

	/**
	 * Get Redis connection.
	 *
	 * @return Redis
	 * @throws RedisException
	 */
	private function getRedis(): Redis
	{
		if( $this->_redis === null )
		{
			$this->_redis = new Redis();

			try
			{
				if( $this->_config['persistent'] )
				{
					$this->_redis->pconnect(
						$this->_config['host'],
						$this->_config['port'],
						$this->_config['timeout']
					);
				}
				else
				{
					$this->_redis->connect(
						$this->_config['host'],
						$this->_config['port'],
						$this->_config['timeout']
					);
				}

				if( $this->_config['auth'] )
				{
					$this->_redis->auth( $this->_config['auth'] );
				}

				if( $this->_config['database'] > 0 )
				{
					$this->_redis->select( $this->_config['database'] );
				}
			}
			catch( RedisException $e )
			{
				Log::error( 'Redis connection failed: ' . $e->getMessage() );
				throw $e;
			}
		}

		return $this->_redis;
	}

	/**
	 * @inheritDoc
	 */
	public function allow( string $key, int $limit, int $window ): bool
	{
		try
		{
			$redis = $this->getRedis();
			$fullKey = $this->_prefix . $key;
			$now = microtime( true );
			$windowStart = $now - $window;

			// Use Redis pipeline for atomic operations
			$pipe = $redis->multi( Redis::PIPELINE );

			// Remove expired entries
			$pipe->zRemRangeByScore( $fullKey, 0, $windowStart );

			// Count current entries
			$pipe->zCard( $fullKey );

			// Add current request
			$pipe->zAdd( $fullKey, $now, uniqid( '', true ) );

			// Set expiry
			$pipe->expire( $fullKey, $window + 1 );

			$results = $pipe->exec();

			// Check if we're within the limit
			$currentCount = $results[1] ?? 0;

			if( $currentCount >= $limit )
			{
				// Remove the entry we just added since it exceeds the limit
				$redis->zRemRangeByScore( $fullKey, $now, $now );
				return false;
			}

			return true;
		}
		catch( RedisException $e )
		{
			Log::error( 'Redis rate limit check failed: ' . $e->getMessage() );
			// Fail open - allow request if Redis is down
			return true;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getRemainingAttempts( string $key, int $limit, int $window ): int
	{
		try
		{
			$redis = $this->getRedis();
			$fullKey = $this->_prefix . $key;
			$now = microtime( true );
			$windowStart = $now - $window;

			// Remove expired entries and get count
			$redis->zRemRangeByScore( $fullKey, 0, $windowStart );
			$currentCount = $redis->zCard( $fullKey );

			return max( 0, $limit - $currentCount );
		}
		catch( RedisException $e )
		{
			Log::error( 'Redis get remaining attempts failed: ' . $e->getMessage() );
			return $limit;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getResetTime( string $key, int $window ): int
	{
		try
		{
			$redis = $this->getRedis();
			$fullKey = $this->_prefix . $key;
			$now = microtime( true );
			$windowStart = $now - $window;

			// Remove expired entries
			$redis->zRemRangeByScore( $fullKey, 0, $windowStart );

			// Get the oldest entry
			$oldestEntries = $redis->zRange( $fullKey, 0, 0, true );

			if( empty( $oldestEntries ) )
			{
				return (int) ($now + $window);
			}

			$oldestTimestamp = reset( $oldestEntries );
			return (int) ($oldestTimestamp + $window);
		}
		catch( RedisException $e )
		{
			Log::error( 'Redis get reset time failed: ' . $e->getMessage() );
			return time() + $window;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function reset( string $key ): void
	{
		try
		{
			$redis = $this->getRedis();
			$fullKey = $this->_prefix . $key;
			$redis->del( $fullKey );
		}
		catch( RedisException $e )
		{
			Log::error( 'Redis reset failed: ' . $e->getMessage() );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): void
	{
		try
		{
			$redis = $this->getRedis();
			$pattern = $this->_prefix . '*';
			$keys = $redis->keys( $pattern );

			if( !empty( $keys ) )
			{
				$redis->del( $keys );
			}
		}
		catch( RedisException $e )
		{
			Log::error( 'Redis clear failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Close Redis connection.
	 */
	public function __destruct()
	{
		if( $this->_redis !== null )
		{
			try
			{
				$this->_redis->close();
			}
			catch( RedisException $e )
			{
				// Ignore close errors
			}
		}
	}
}