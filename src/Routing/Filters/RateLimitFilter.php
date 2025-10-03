<?php

namespace Neuron\Routing\Filters;

use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Routing\RateLimit\RateLimitConfig;
use Neuron\Routing\RateLimit\RateLimitResponse;
use Neuron\Routing\RateLimit\Storage\IRateLimitStorage;
use Neuron\Routing\RateLimit\Storage\RateLimitStorageFactory;
use Neuron\Patterns\Registry;
use Neuron\Log\Log;

/**
 * Rate limiting filter for HTTP requests.
 *
 * Implements configurable rate limiting with support for multiple
 * storage backends and flexible key generation strategies.
 *
 * @package Neuron\Routing\Filters
 */
class RateLimitFilter extends Filter
{
	private IRateLimitStorage $_Storage;
	private RateLimitConfig $_Config;
	private string $_KeyStrategy;
	private array $_Whitelist;
	private array $_Blacklist;

	/**
	 * @param RateLimitConfig $Config Rate limit configuration
	 * @param string $KeyStrategy Key generation strategy ('ip', 'user', 'route', 'custom')
	 * @param array $Whitelist IP addresses or user IDs to exempt from rate limiting
	 * @param array $Blacklist IP addresses or user IDs to apply stricter limits
	 */
	public function __construct(
		RateLimitConfig $Config,
		string $KeyStrategy = 'ip',
		array $Whitelist = [],
		array $Blacklist = []
	)
	{
		$this->_Config = $Config;
		$this->_KeyStrategy = $KeyStrategy;
		$this->_Whitelist = $Whitelist;
		$this->_Blacklist = $Blacklist;

		// Get base path from registry if available
		$basePath = Registry::getInstance()->get( 'BasePath' ) ?? '';

		// Create storage instance
		$this->_Storage = RateLimitStorageFactory::create( $Config, $basePath );

		// Set up filter callbacks
		parent::__construct(
			function( RouteMap $Route ) { $this->checkRateLimit( $Route ); },
			null
		);
	}

	/**
	 * Check rate limit for the current request.
	 *
	 * @param RouteMap $Route
	 * @return void
	 */
	protected function checkRateLimit( RouteMap $Route ): void
	{
		// Skip if rate limiting is disabled
		if( !$this->_Config->isEnabled() )
		{
			return;
		}

		// Generate key for this request
		$key = $this->generateKey( $Route );

		// Check whitelist
		if( $this->isWhitelisted( $key ) )
		{
			Log::debug( "Rate limit: Whitelisted key $key" );
			return;
		}

		// Get limits (stricter for blacklisted)
		$limit = $this->getLimit( $key );
		$window = $this->getWindow( $key );

		// Check if request is allowed
		if( !$this->_Storage->allow( $key, $limit, $window ) )
		{
			// Get rate limit info
			$remaining = $this->_Storage->getRemainingAttempts( $key, $limit, $window );
			$resetTime = $this->_Storage->getResetTime( $key, $window );

			Log::warning( "Rate limit exceeded for key: $key" );

			// Send rate limit response and exit
			RateLimitResponse::send( $limit, $remaining, $resetTime );
			exit;
		}

		// Add rate limit headers to successful requests
		$this->addRateLimitHeaders( $key, $limit, $window );
	}

	/**
	 * Generate a unique key for rate limiting.
	 *
	 * @param RouteMap $Route
	 * @return string
	 */
	protected function generateKey( RouteMap $Route ): string
	{
		switch( $this->_KeyStrategy )
		{
			case 'ip':
				return $this->getClientIp();

			case 'user':
				return $this->getUserId();

			case 'route':
				return $this->getClientIp() . ':' . $Route->getPath();

			case 'custom':
				return $this->getCustomKey( $Route );

			default:
				return $this->getClientIp();
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	protected function getClientIp(): string
	{
		// Check for forwarded IP (proxy/load balancer)
		if( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
		{
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return trim( $ips[0] );
		}

		if( !empty( $_SERVER['HTTP_X_REAL_IP'] ) )
		{
			return $_SERVER['HTTP_X_REAL_IP'];
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Get user ID for authenticated requests.
	 *
	 * @return string
	 */
	protected function getUserId(): string
	{
		// Check registry for user ID (set by authentication)
		$userId = Registry::getInstance()->get( 'User.Id' );

		if( $userId )
		{
			return 'user:' . $userId;
		}

		// Fall back to IP-based limiting
		return 'ip:' . $this->getClientIp();
	}

	/**
	 * Get custom key for rate limiting.
	 *
	 * @param RouteMap $Route
	 * @return string
	 */
	protected function getCustomKey( RouteMap $Route ): string
	{
		// Override this method in subclasses for custom key generation
		return $this->getClientIp();
	}

	/**
	 * Check if a key is whitelisted.
	 *
	 * @param string $key
	 * @return bool
	 */
	protected function isWhitelisted( string $key ): bool
	{
		return in_array( $key, $this->_Whitelist, true );
	}

	/**
	 * Check if a key is blacklisted.
	 *
	 * @param string $key
	 * @return bool
	 */
	protected function isBlacklisted( string $key ): bool
	{
		return in_array( $key, $this->_Blacklist, true );
	}

	/**
	 * Get rate limit for a key.
	 *
	 * @param string $key
	 * @return int
	 */
	protected function getLimit( string $key ): int
	{
		// Apply stricter limit for blacklisted keys
		if( $this->isBlacklisted( $key ) )
		{
			return max( 1, (int) ($this->_Config->getLimit() / 10) );
		}

		return $this->_Config->getLimit();
	}

	/**
	 * Get time window for a key.
	 *
	 * @param string $key
	 * @return int
	 */
	protected function getWindow( string $key ): int
	{
		// Could be customized per key if needed
		return $this->_Config->getWindow();
	}

	/**
	 * Add rate limit headers to response.
	 *
	 * @param string $key
	 * @param int $limit
	 * @param int $window
	 * @return void
	 */
	protected function addRateLimitHeaders( string $key, int $limit, int $window ): void
	{
		$remaining = $this->_Storage->getRemainingAttempts( $key, $limit, $window );
		$resetTime = $this->_Storage->getResetTime( $key, $window );

		header( 'X-RateLimit-Limit: ' . $limit );
		header( 'X-RateLimit-Remaining: ' . max( 0, $remaining ) );
		header( 'X-RateLimit-Reset: ' . $resetTime );
	}

	/**
	 * Get the storage instance (for testing).
	 *
	 * @return IRateLimitStorage
	 */
	public function getStorage(): IRateLimitStorage
	{
		return $this->_Storage;
	}

	/**
	 * Reset rate limit for a specific key.
	 *
	 * @param string $key
	 * @return void
	 */
	public function reset( string $key ): void
	{
		$this->_Storage->reset( $key );
	}

	/**
	 * Clear all rate limits.
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->_Storage->clear();
	}
}