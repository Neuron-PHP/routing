<?php

namespace Neuron\Routing\RateLimit\Storage;

use Neuron\Core\System\IClock;
use Neuron\Core\System\RealClock;
use Neuron\Log\Log;

/**
 * File-based rate limit storage for simple deployments.
 *
 * Uses file locking for concurrency control. Suitable for single-server
 * deployments where Redis is not available.
 *
 * @package Neuron\Routing\RateLimit\Storage
 */
class FileRateLimitStorage implements IRateLimitStorage
{
	private string $_path;
	private string $_prefix;
	private float $_gcProbability;
	private IClock $clock;

	/**
	 * @param array $config Configuration options
	 * @param IClock|null $clock Clock implementation (null = use real clock)
	 */
	public function __construct( array $config = [], ?IClock $clock = null )
	{
		$this->_path = $config['path'] ?? sys_get_temp_dir() . '/rate_limits';
		$this->_prefix = $config['prefix'] ?? 'rl_';
		$this->_gcProbability = $config['gc_probability'] ?? 0.01;
		$this->clock = $clock ?? new RealClock();

		// Ensure directory exists
		if( !is_dir( $this->_path ) )
		{
			if( !mkdir( $this->_path, 0777, true ) && !is_dir( $this->_path ) )
			{
				Log::error( "Failed to create rate limit directory: {$this->_path}" );
			}
		}

		// Run garbage collection probabilistically
		if( mt_rand() / mt_getrandmax() < $this->_gcProbability )
		{
			$this->gc();
		}
	}

	/**
	 * Get file path for a key.
	 *
	 * @param string $key
	 * @return string
	 */
	private function getFilePath( string $key ): string
	{
		// Hash the key to avoid filesystem issues with special characters
		$hashedKey = md5( $this->_prefix . $key );
		return $this->_path . '/' . $hashedKey . '.rl';
	}

	/**
	 * Read data from file with locking.
	 *
	 * @param string $filePath
	 * @return array|null
	 */
	private function readFile( string $filePath ): ?array
	{
		if( !file_exists( $filePath ) )
		{
			return null;
		}

		$fp = fopen( $filePath, 'r' );
		if( !$fp )
		{
			return null;
		}

		if( !flock( $fp, LOCK_SH ) )
		{
			fclose( $fp );
			return null;
		}

		$content = fread( $fp, filesize( $filePath ) );
		flock( $fp, LOCK_UN );
		fclose( $fp );

		$data = json_decode( $content, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Write data to file with locking.
	 *
	 * @param string $filePath
	 * @param array $data
	 * @return bool
	 */
	private function writeFile( string $filePath, array $data ): bool
	{
		$fp = fopen( $filePath, 'c' );
		if( !$fp )
		{
			return false;
		}

		if( !flock( $fp, LOCK_EX ) )
		{
			fclose( $fp );
			return false;
		}

		ftruncate( $fp, 0 );
		fwrite( $fp, json_encode( $data ) );
		fflush( $fp );
		flock( $fp, LOCK_UN );
		fclose( $fp );

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function allow( string $key, int $limit, int $window ): bool
	{
		$filePath = $this->getFilePath( $key );
		$now = $this->clock->time();
		$windowStart = $now - $window;

		// Read existing data
		$data = $this->readFile( $filePath );
		if( $data === null )
		{
			$data = ['attempts' => []];
		}

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

		// Write back to file
		if( !$this->writeFile( $filePath, $data ) )
		{
			Log::warning( "Failed to write rate limit file: $filePath" );
			// Fail open if we can't write
			return true;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getRemainingAttempts( string $key, int $limit, int $window ): int
	{
		$filePath = $this->getFilePath( $key );
		$now = $this->clock->time();
		$windowStart = $now - $window;

		$data = $this->readFile( $filePath );
		if( $data === null )
		{
			return $limit;
		}

		// Count non-expired attempts
		$activeAttempts = array_filter(
			$data['attempts'] ?? [],
			function( $timestamp ) use ( $windowStart ) {
				return $timestamp > $windowStart;
			}
		);

		return max( 0, $limit - count( $activeAttempts ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getResetTime( string $key, int $window ): int
	{
		$filePath = $this->getFilePath( $key );

		$data = $this->readFile( $filePath );
		if( $data === null || empty( $data['attempts'] ) )
		{
			return $this->clock->time() + $window;
		}

		// Find the oldest attempt
		$oldestAttempt = min( $data['attempts'] );
		return $oldestAttempt + $window;
	}

	/**
	 * @inheritDoc
	 */
	public function reset( string $key ): void
	{
		$filePath = $this->getFilePath( $key );
		if( file_exists( $filePath ) )
		{
			unlink( $filePath );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): void
	{
		$files = glob( $this->_path . '/*.rl' );
		if( $files !== false )
		{
			foreach( $files as $file )
			{
				unlink( $file );
			}
		}
	}

	/**
	 * Garbage collection - remove expired files.
	 *
	 * @return int Number of files removed
	 */
	public function gc(): int
	{
		$removed = 0;
		$files = glob( $this->_path . '/*.rl' );

		if( $files === false )
		{
			return 0;
		}

		foreach( $files as $file )
		{
			$data = $this->readFile( $file );

			// Remove empty or corrupted files
			if( $data === null || empty( $data['attempts'] ) )
			{
				unlink( $file );
				$removed++;
				continue;
			}

			// Remove files where all attempts are expired (max window of 1 day)
			$maxAge = $this->clock->time() - 86400;
			$hasRecent = false;

			foreach( $data['attempts'] as $timestamp )
			{
				if( $timestamp > $maxAge )
				{
					$hasRecent = true;
					break;
				}
			}

			if( !$hasRecent )
			{
				unlink( $file );
				$removed++;
			}
		}

		if( $removed > 0 )
		{
			Log::debug( "Rate limit GC: Removed $removed expired files" );
		}

		return $removed;
	}
}