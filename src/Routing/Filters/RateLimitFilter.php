<?php

namespace Neuron\Routing\Filters;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;
use Neuron\Routing\RateLimit\RateLimitConfig;
use Neuron\Routing\RateLimit\RateLimitResponse;
use Neuron\Routing\RateLimit\Storage\IRateLimitStorage;
use Neuron\Routing\RateLimit\Storage\RateLimitStorageFactory;
use Neuron\Routing\IIpResolver;
use Neuron\Routing\DefaultIpResolver;
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
	private IRateLimitStorage $_storage;
	private RateLimitConfig $_config;
	private string $_keyStrategy;
	private array $_whitelist;
	private array $_blacklist;
	private IIpResolver $_ipResolver;

	/**
	 * @param RateLimitConfig $config Rate limit configuration
	 * @param string $keyStrategy Key generation strategy ('ip', 'user', 'route', 'custom')
	 * @param array $whitelist IP addresses or user IDs to exempt from rate limiting
	 * @param array $blacklist IP addresses or user IDs to apply stricter limits
	 * @param IIpResolver|null $ipResolver Optional IP resolver (defaults to DefaultIpResolver)
	 * @throws \Exception
	 */
	public function __construct(
		RateLimitConfig $config,
		string $keyStrategy = 'ip',
		array $whitelist = [],
		array $blacklist = [],
		?IIpResolver $ipResolver = null
	)
	{
		$this->_config = $config;
		$this->_keyStrategy = $keyStrategy;
		$this->_whitelist = $whitelist;
		$this->_blacklist = $blacklist;
		$this->_ipResolver = $ipResolver ?? new DefaultIpResolver();

		// Get base path from registry if available
		$basePath = Registry::getInstance()->get( RegistryKeys::BASE_PATH_LEGACY ) ?? '';

		// Create storage instance
		$this->_storage = RateLimitStorageFactory::create( $config, $basePath );

		// Set up filter callbacks
		parent::__construct(
			function( RouteMap $route ) { $this->checkRateLimit( $route ); },
			null
		);
	}

	/**
	 * Check the rate limit for the current request.
	 *
	 * @param RouteMap $route
	 * @return void
	 */
	protected function checkRateLimit( RouteMap $route ): void
	{
		// Skip if rate limiting is disabled
		if( !$this->_config->isEnabled() )
		{
			return;
		}

		// Generate key for this request
		$key = $this->generateKey( $route );

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
		if( !$this->_storage->allow( $key, $limit, $window ) )
		{
			// Get rate limit info
			$remaining = $this->_storage->getRemainingAttempts( $key, $limit, $window );
			$resetTime = $this->_storage->getResetTime( $key, $window );
			$attempts = $limit - $remaining;

			Log::warning( "Rate limit exceeded for key: $key" );

			// Emit rate limit exceeded event
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Mvc\Events\RateLimitExceededEvent(
				$this->getClientIp(),
				$route->getPath(),
				$limit,
				$window,
				$attempts
			) );

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
	 * @param RouteMap $route
	 * @return string
	 */
	protected function generateKey( RouteMap $route ): string
	{
		return match ( $this->_keyStrategy )
		{
			'user' => $this->getUserId(),
			'route' => $this->getClientIp() . ':' . $route->getPath(),
			'custom' => $this->getCustomKey( $route ),
			default => $this->getClientIp(),
		};
	}

	/**
	 * Get the client IP address using the configured IP resolver.
	 *
	 * @return string
	 */
	protected function getClientIp(): string
	{
		return $this->_ipResolver->resolve( $_SERVER );
	}

	/**
	 * Get user ID for authenticated requests.
	 *
	 * @return string
	 */
	protected function getUserId(): string
	{
		// Check registry for user ID (set by authentication)
		$userId = Registry::getInstance()->get( RegistryKeys::USER_ID );

		if( $userId )
		{
			return 'user:' . $userId;
		}

		// Fall back to IP-based limiting
		return 'ip:' . $this->getClientIp();
	}

	/**
	 * Get a custom key for rate limiting.
	 *
	 * @param RouteMap $route
	 * @return string
	 */
	protected function getCustomKey( RouteMap $route ): string
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
		return in_array( $key, $this->_whitelist, true );
	}

	/**
	 * Check if a key is blacklisted.
	 *
	 * @param string $key
	 * @return bool
	 */
	protected function isBlacklisted( string $key ): bool
	{
		return in_array( $key, $this->_blacklist, true );
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
			return max( 1, (int) ($this->_config->getLimit() / 10) );
		}

		return $this->_config->getLimit();
	}

	/**
	 * Get the time window for a key.
	 *
	 * @param string $key
	 * @return int
	 */
	protected function getWindow( string $key ): int
	{
		// Could be customized per key if needed
		return $this->_config->getWindow();
	}

	/**
	 * Add the rate limit headers to response.
	 *
	 * @param string $key
	 * @param int $limit
	 * @param int $window
	 * @return void
	 */
	protected function addRateLimitHeaders( string $key, int $limit, int $window ): void
	{
		$remaining = $this->_storage->getRemainingAttempts( $key, $limit, $window );
		$resetTime = $this->_storage->getResetTime( $key, $window );

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
		return $this->_storage;
	}

	/**
	 * Reset rate limit for a specific key.
	 *
	 * @param string $key
	 * @return void
	 */
	public function reset( string $key ): void
	{
		$this->_storage->reset( $key );
	}

	/**
	 * Clear all rate limits.
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->_storage->clear();
	}
}
