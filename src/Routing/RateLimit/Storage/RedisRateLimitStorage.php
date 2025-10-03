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
	private ?Redis $_Redis = null;
	private array $_Config;
	private string $_Prefix;

	/**
	 * @param array $Config Redis configuration
	 */
	public function __construct( array $Config = [] )
	{
		$this->_Config = array_merge([
			'host' => '127.0.0.1',
			'port' => 6379,
			'database' => 0,
			'prefix' => 'rate_limit_',
			'timeout' => 2.0,
			'auth' => null,
			'persistent' => false
		], $Config);

		$this->_Prefix = $this->_Config['prefix'];
	}

	/**
	 * Get Redis connection.
	 *
	 * @return Redis
	 * @throws RedisException
	 */
	private function getRedis(): Redis
	{
		if( $this->_Redis === null )
		{
			$this->_Redis = new Redis();

			try
			{
				if( $this->_Config['persistent'] )
				{
					$this->_Redis->pconnect(
						$this->_Config['host'],
						$this->_Config['port'],
						$this->_Config['timeout']
					);
				}
				else
				{
					$this->_Redis->connect(
						$this->_Config['host'],
						$this->_Config['port'],
						$this->_Config['timeout']
					);
				}

				if( $this->_Config['auth'] )
				{
					$this->_Redis->auth( $this->_Config['auth'] );
				}

				if( $this->_Config['database'] > 0 )
				{
					$this->_Redis->select( $this->_Config['database'] );
				}
			}
			catch( RedisException $e )
			{
				Log::error( 'Redis connection failed: ' . $e->getMessage() );
				throw $e;
			}
		}

		return $this->_Redis;
	}

	/**
	 * @inheritDoc
	 */
	public function allow( string $key, int $limit, int $window ): bool
	{
		try
		{
			$redis = $this->getRedis();
			$fullKey = $this->_Prefix . $key;
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
			$fullKey = $this->_Prefix . $key;
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
			$fullKey = $this->_Prefix . $key;
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
			$fullKey = $this->_Prefix . $key;
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
			$pattern = $this->_Prefix . '*';
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
		if( $this->_Redis !== null )
		{
			try
			{
				$this->_Redis->close();
			}
			catch( RedisException $e )
			{
				// Ignore close errors
			}
		}
	}
}