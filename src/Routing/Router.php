<?php

namespace Neuron\Routing;

use Neuron\Core\NString;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Log\Log;
use Neuron\Patterns\IRunnable;
use Neuron\Patterns\Registry;
use Neuron\Patterns\Singleton\Memory;

/**
 * Core HTTP routing engine for the Neuron framework.
 * 
 * This singleton router manages HTTP request routing, URL pattern matching,
 * parameter extraction, and filter execution. It provides a centralized system
 * for mapping HTTP requests to controller actions with support for:
 * 
 * - RESTful HTTP methods (GET, POST, PUT, DELETE)
 * - Dynamic route parameters (e.g., /user/:id)
 * - Route filters for pre/post processing
 * - Flexible parameter extraction and validation
 * - URI normalization and processing
 * 
 * The router follows a singleton pattern ensuring consistent routing state
 * across the entire application lifecycle.
 * 
 * @package Neuron\Routing
 * 
 * @example
 * ```php
 * $router = Router::instance();
 * 
 * // Register routes
 * $router->get('/users', 'UserController@index');
 * $router->post('/users', 'UserController@create');
 * $router->get('/users/:id', 'UserController@show');
 * 
 * // Register filters
 * $router->registerFilter('auth', new AuthFilter());
 * 
 * // Execute routing
 * $router->run();
 * ```
 */
class Router extends Memory implements IRunnable
{
	private array $_delete  = [];
	private array $_get     = [];
	private array $_post    = [];
	private array $_put     = [];
	private array $_filter  = [];

	private array $_filterRegistry = [];
	private ?IIpResolver $_ipResolver = null;

	/** @var array Track registered routes to detect duplicates: ['method:path' => 'controller@action'] */
	private array $_registeredRoutes = [];

	/** @var array Track registered route names: ['name' => 'method:path'] */
	private array $_registeredNames = [];

	/** @var bool Enable strict duplicate checking (throws exceptions) */
	private bool $_strictMode = true;

	/** @var array URL rewrites: ['/from' => '/to'] */
	private array $_urlRewrites = [];

	/**
	 * Set the IP resolver for all requests handled by this router.
	 *
	 * @param IIpResolver $resolver The IP resolver to use
	 * @return void
	 */
	public function setIpResolver( IIpResolver $resolver ): void
	{
		$this->_ipResolver = $resolver;
	}

	/**
	 * Enable or disable strict mode for duplicate route detection.
	 *
	 * When strict mode is enabled (default), duplicate routes will throw
	 * a DuplicateRouteException. When disabled, duplicates are silently
	 * allowed (first match wins behavior).
	 *
	 * When enabling strict mode, this method validates all existing routes
	 * and throws an exception if any duplicates are found.
	 *
	 * @param bool $strict Enable strict duplicate checking
	 * @return void
	 * @throws Exceptions\DuplicateRouteException If duplicates exist when enabling strict mode
	 */
	public function setStrictMode( bool $strict ): void
	{
		// If enabling strict mode, validate existing routes for duplicates
		if( $strict && !$this->_strictMode )
		{
			$this->validateExistingRoutes();
		}

		$this->_strictMode = $strict;
	}

	/**
	 * Validate all existing routes for duplicates.
	 *
	 * This rebuilds the route and name registries from scratch and throws
	 * an exception if any duplicates are found.
	 *
	 * @return void
	 * @throws Exceptions\DuplicateRouteException If duplicates are found
	 */
	protected function validateExistingRoutes(): void
	{
		// Temporarily clear registries to rebuild from scratch
		$tempRoutes = $this->_registeredRoutes;
		$tempNames = $this->_registeredNames;
		$this->_registeredRoutes = [];
		$this->_registeredNames = [];

		try
		{
			// Check all routes across all HTTP methods
			$allRouteMaps = [
				'GET' => $this->_get,
				'POST' => $this->_post,
				'PUT' => $this->_put,
				'DELETE' => $this->_delete
			];

			foreach( $allRouteMaps as $method => $routes )
			{
				foreach( $routes as $route )
				{
					$path = $route->getPath();
					$signature = "{$method}:{$path}";

					// Check for duplicate method+path
					if( isset( $this->_registeredRoutes[ $signature ] ) )
					{
						throw new Exceptions\DuplicateRouteException(
							$method,
							$path,
							$this->_registeredRoutes[ $signature ],
							$method,
							$path,
							$this->extractControllerInfo( $route ),
							null
						);
					}

					// Register the route
					$this->_registeredRoutes[ $signature ] = $this->extractControllerInfo( $route );

					// Check for duplicate name
					$name = $route->getName();
					if( $name && isset( $this->_registeredNames[ $name ] ) )
					{
						$originalSignature = $this->_registeredNames[ $name ];
						list( $originalMethod, $originalPath ) = explode( ':', $originalSignature, 2 );

						throw new Exceptions\DuplicateRouteException(
							$originalMethod,
							$originalPath,
							$this->_registeredRoutes[ $originalSignature ] ?? 'unknown controller',
							$method,
							$path,
							$this->extractControllerInfo( $route ),
							$name
						);
					}

					// Register the name
					if( $name )
					{
						$this->_registeredNames[ $name ] = $signature;
					}
				}
			}
		}
		catch( Exceptions\DuplicateRouteException $e )
		{
			// Restore original registries and re-throw
			$this->_registeredRoutes = $tempRoutes;
			$this->_registeredNames = $tempNames;
			throw $e;
		}
	}

	/**
	 * Clear all registered routes and filters.
	 *
	 * This method resets the router to its initial state, removing all
	 * registered routes, route names, filters, and URL rewrites. Useful
	 * for testing or when you need to reconfigure routing from scratch.
	 *
	 * @return void
	 */
	public function clearRoutes(): void
	{
		$this->_delete = [];
		$this->_get = [];
		$this->_post = [];
		$this->_put = [];
		$this->_filter = [];
		$this->_filterRegistry = [];
		$this->_registeredRoutes = [];
		$this->_registeredNames = [];
		$this->_urlRewrites = [];
	}

	/**
	 * Set URL rewrite rules.
	 *
	 * URL rewrites are applied before route matching, transparently rewriting
	 * incoming URLs to different paths without HTTP redirects. This is useful
	 * for handling legacy URLs, providing clean URLs, or allowing packages to
	 * define default routes that can be overridden by applications.
	 *
	 * @param array $rewrites Associative array of ['/from' => '/to'] mappings
	 * @return void
	 *
	 * @example
	 * ```php
	 * $router->setUrlRewrites([
	 *     '/' => '/home/members',      // Root goes to members homepage
	 *     '/index' => '/home/members', // Legacy URL also redirected
	 *     '/blog' => '/posts'          // Clean URL mapping
	 * ]);
	 * ```
	 */
	public function setUrlRewrites( array $rewrites ): void
	{
		$this->_urlRewrites = $rewrites;
	}

	/**
	 * Rewrite a URL according to configured rewrite rules.
	 *
	 * This applies URL rewrites transparently before route matching. Rewrites
	 * are exact matches only (no pattern matching). The rewritten URL is used
	 * for route matching, but the original URL remains visible to the client.
	 *
	 * @param string $url The incoming URL to potentially rewrite
	 * @return string The rewritten URL, or original URL if no rewrite applies
	 */
	protected function rewriteUrl( string $url ): string
	{
		// Normalize URL: strip trailing slashes (except for root "/")
		$normalizedUrl = $url;
		if( strlen( $url ) > 1 && $url[ strlen( $url ) - 1 ] === '/' )
		{
			$normalizedUrl = substr( $url, 0, -1 );
		}

		// Sanitize URL for logging (remove query strings and fragments)
		$sanitizedUrl = $this->sanitizeUrlForLogging( $normalizedUrl );

		// Check for exact match rewrite
		if( isset( $this->_urlRewrites[ $normalizedUrl ] ) )
		{
			Log::debug( "URL rewrite: {$sanitizedUrl} -> {$this->_urlRewrites[$normalizedUrl]}" );
			return $this->_urlRewrites[ $normalizedUrl ];
		}

		// Also check the original URL (with trailing slash if present)
		if( $normalizedUrl !== $url && isset( $this->_urlRewrites[ $url ] ) )
		{
			Log::debug( "URL rewrite: {$sanitizedUrl} -> {$this->_urlRewrites[$url]}" );
			return $this->_urlRewrites[ $url ];
		}

		return $url;
	}

	/**
	 * Sanitize a URL for logging by removing query strings and fragments.
	 *
	 * This prevents sensitive tokens, API keys, or other data from being
	 * logged inadvertently.
	 *
	 * @param string $url The URL to sanitize
	 * @return string The sanitized URL (path only)
	 */
	protected function sanitizeUrlForLogging( string $url ): string
	{
		// Find the first occurrence of '?' or '#'
		$queryPos = strpos( $url, '?' );
		$fragmentPos = strpos( $url, '#' );

		// Determine where to truncate
		$truncatePos = false;
		if( $queryPos !== false && $fragmentPos !== false )
		{
			$truncatePos = min( $queryPos, $fragmentPos );
		}
		else if( $queryPos !== false )
		{
			$truncatePos = $queryPos;
		}
		else if( $fragmentPos !== false )
		{
			$truncatePos = $fragmentPos;
		}

		// Truncate if needed
		if( $truncatePos !== false )
		{
			return substr( $url, 0, $truncatePos );
		}

		return $url;
	}

	/**
	 * @param string $name
	 * @param Filter $filter
	 */
	public function registerFilter( string $name, Filter $filter ): void
	{
		$this->_filterRegistry[ $name ] = $filter;
	}

	/**
	 * @param string $routeName
	 * @return Filter
	 * @throws \Exception
	 */

	public function getFilter( string $routeName ) : Filter
	{
		$filter = null;

		if( array_key_exists( $routeName, $this->_filterRegistry ) )
		{
			$filter = $this->_filterRegistry[ $routeName ];
		}
		else
		{
			throw new \Exception( "Filter $routeName not registered." );
		}

		return $filter;
	}

	/**
	 * @param string $filter
	 */
	public function addFilter( string $filter ): void
	{
		$this->_filter[] = $filter;
	}

	/**
	 * @param array $routes
	 * @param string $method HTTP method (GET, POST, PUT, DELETE)
	 * @param string $routeName
	 * @param $function
	 * @param $filters string|array
	 * @param string|null $name Optional route name (for duplicate detection)
	 * @return RouteMap
	 * @throws \Exception
	 */
	protected function addRoute( array &$routes, string $method, string $routeName, $function, string|array $filters, ?string $name = null ) : RouteMap
	{
		// Normalize route path: strip trailing slashes (except for root "/")
		if( strlen( $routeName ) > 1 && $routeName[ strlen( $routeName ) - 1 ] == "/" )
		{
			$routeName = substr( $routeName, 0, -1 );
		}

		$route = new RouteMap( $routeName, $function, $filters ?? '' );

		// Set router context so the route can register names for duplicate detection
		$route->setRouterContext( $this, $method );

		// Always check for duplicate path+method combinations (registers signature)
		// In strict mode this throws on duplicates, in non-strict mode first match wins
		$this->checkDuplicateRoute( $route, $method );

		// Set the route name if provided (this will trigger duplicate name check)
		// If setName throws, we need to unregister the signature we just added
		if( $name )
		{
			try
			{
				$route->setName( $name );
			}
			catch( Exceptions\DuplicateRouteException $e )
			{
				// Rollback: unregister the signature we added in checkDuplicateRoute
				$signature = "{$method}:{$routeName}";
				unset( $this->_registeredRoutes[ $signature ] );
				throw $e;
			}
		}

		$routes[] = $route;

		return $route;
	}

	/**
	 * Register a route name and check for duplicates (called by RouteMap::setName).
	 *
	 * This method is called when a route name is set via the fluent API,
	 * allowing duplicate name detection to work properly even when names
	 * are set after route registration.
	 *
	 * In strict mode, duplicate names throw an exception. In non-strict mode,
	 * the first registered name wins (subsequent attempts to register the same
	 * name are silently ignored).
	 *
	 * @param string $name The route name to register
	 * @param string $method The HTTP method (GET, POST, PUT, DELETE)
	 * @param string $path The route path
	 * @param RouteMap $route The route being named
	 * @return void
	 * @throws Exceptions\DuplicateRouteException If name is already in use and strict mode is enabled
	 */
	public function registerRouteName( string $name, string $method, string $path, RouteMap $route ): void
	{
		// Check if this name is already registered
		if( isset( $this->_registeredNames[ $name ] ) )
		{
			// In non-strict mode, first match wins - don't overwrite
			if( !$this->_strictMode )
			{
				return;
			}

			// Strict mode: throw exception for duplicate name
			$originalSignature = $this->_registeredNames[ $name ];
			list( $originalMethod, $originalPath ) = explode( ':', $originalSignature, 2 );

			throw new Exceptions\DuplicateRouteException(
				$originalMethod,  // First route method
				$originalPath,    // First route path
				$this->_registeredRoutes[ $originalSignature ] ?? 'unknown controller',
				$method,          // Second route method
				$path,            // Second route path
				$this->extractControllerInfo( $route ),
				$name
			);
		}

		// Register the name (first time only)
		$this->_registeredNames[ $name ] = "{$method}:{$path}";
	}

	/**
	 * Unregister a route name (called by RouteMap::setName when renaming).
	 *
	 * This method is called when a route's name is being changed, allowing
	 * the old name to be removed from the registry before registering the new name.
	 *
	 * @param string $name The route name to unregister
	 * @return void
	 */
	public function unregisterRouteName( string $name ): void
	{
		unset( $this->_registeredNames[ $name ] );
	}

	/**
	 * Check if a route is a duplicate and throw exception if found (in strict mode).
	 *
	 * This method always registers the route signature for tracking. In strict mode,
	 * it throws an exception if a duplicate is found. In non-strict mode, the first
	 * registered route wins and subsequent duplicates are allowed (first match wins).
	 *
	 * @param RouteMap $route The route being added
	 * @param string $method The HTTP method (GET, POST, PUT, DELETE)
	 * @return void
	 * @throws Exceptions\DuplicateRouteException If duplicate found and strict mode is enabled
	 */
	protected function checkDuplicateRoute( RouteMap $route, string $method ): void
	{
		$path = $route->getPath();
		$signature = "{$method}:{$path}";

		// Check for duplicate method+path combination
		if( isset( $this->_registeredRoutes[ $signature ] ) )
		{
			// In non-strict mode, first match wins - don't throw, don't overwrite
			if( !$this->_strictMode )
			{
				return;
			}

			// Strict mode: throw exception for duplicate
			throw new Exceptions\DuplicateRouteException(
				$method,  // First route method (same as second)
				$path,    // First route path (same as second)
				$this->_registeredRoutes[ $signature ],
				$method,  // Second route method
				$path,    // Second route path
				$this->extractControllerInfo( $route ),
				null
			);
		}

		// Register this route (first time only - name registration is handled separately by registerRouteName)
		$this->_registeredRoutes[ $signature ] = $this->extractControllerInfo( $route );
	}

	/**
	 * Extract controller information from a route for error messages.
	 *
	 * @param RouteMap $route
	 * @return string
	 */
	protected function extractControllerInfo( RouteMap $route ): string
	{
		$payload = $route->Payload;

		if( is_array( $payload ) && isset( $payload['Controller'] ) )
		{
			return $payload['Controller'];
		}

		return 'unknown controller';
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|array|null $filters
	 * @param string|null $name
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function delete( string $route, $function, string|array|null $filters = null, ?string $name = null ) : RouteMap
	{
		return $this->addRoute( $this->_delete, 'DELETE', $route, $function, $filters ?? '', $name );
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|array|null $filters
	 * @param string|null $name
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function get( string $route, $function, string|array|null $filters = null, ?string $name = null ) : RouteMap
	{
		return $this->addRoute( $this->_get, 'GET', $route, $function, $filters ?? '', $name );
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|array|null $filters
	 * @param string|null $name
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function post( string $route, $function, string|array|null $filters = null, ?string $name = null ) : RouteMap
	{
		return $this->addRoute( $this->_post, 'POST', $route, $function, $filters ?? '', $name );
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|array|null $filters
	 * @param string|null $name
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function put( string $route, $function, string|array|null $filters = null, ?string $name = null ) : RouteMap
	{
		return $this->addRoute( $this->_put, 'PUT', $route, $function, $filters ?? '', $name );
	}

	/**
	 * @param RouteMap $route
	 * @return bool
	 */
	protected function isRouteWithParams( RouteMap $route ) : bool
	{
		return strpos( $route->Path, ':' ) !== false || strpos( $route->Path, '*' ) !== false;
	}

	/**
	 * Check if route contains a wildcard parameter
	 *
	 * @param RouteMap $route
	 * @return bool
	 */
	protected function hasWildcard( RouteMap $route ) : bool
	{
		return strpos( $route->Path, '*' ) !== false;
	}

	/**
	 * @param RouteMap $route
	 * @param $uri
	 * @return array|null
	 * @throws \Exception
	 */
	protected function processRoute( RouteMap $route, $uri ) : ?array
	{
		if( !$uri )
		{
			$uri = '/';
		}
		else if( $uri[ 0 ] != '/' )
		{
			$uri = '/' . $uri;
		}

		if( strlen( $uri ) > 1 && $uri[ strlen( $uri ) - 1 ] == "/" )
		{
			$string = new NString( $uri );
			$uri    = $string->left( $string->length() - 1 );
		}

		// Does route have parameters?

		if( $this->isRouteWithParams( $route ) )
		{
			$segments = count( explode( '/', $uri ) );

			$routeSegments = count( explode( '/', $route->Path ) );

			// Wildcard routes need >= segments, normal routes need exact match
			if( $this->hasWildcard( $route ) )
			{
				if( $segments >= $routeSegments )
				{
					return $this->processRouteWithParameters( $route, $uri );
				}
			}
			else if( $segments == $routeSegments )
			{
				return $this->processRouteWithParameters( $route, $uri );
			}
		}
		else
		{
			if( $route->Path == $uri )
			{
				return [];
			}
		}

		return null;
	}

	/**
	 * @param RouteMap $route
	 * @param string $uri
	 * @return array
	 * @throws \Exception
	 */

	protected function processRouteWithParameters( RouteMap $route, string $uri ) : array
	{
		$details = $route->parseParams();

		return $this->extractRouteParams( $uri, $details );
	}

	/**
	 * Populates a param array with the data from the uri.
	 * @param string $uri
	 * @param array $details
	 * @return array
	 */

	protected function extractRouteParams( string $uri, array $details ) : array
	{
		if( $uri && $uri[ 0 ] == '/' )
		{
			$string = new NString( $uri );
			$uri    = $string->right( $string->length() - 1 );
		}

		$uriParts = explode( '/', $uri );

		$params = [];
		$iOffset = 0;

		foreach( $details as $index => $detail )
		{
			if( $iOffset >= count( $uriParts ) )
			{
				return [];
			}

			$action = $detail[ 'action' ];
			$isWildcard = $detail[ 'wildcard' ] ?? false;

			if( $isWildcard )
			{
				// Wildcard parameter - capture all remaining URI segments
				$remainingParts = array_slice( $uriParts, $iOffset );
				$params[ $detail[ 'param' ] ] = implode( '/', $remainingParts );
				break; // Wildcard consumes rest of URI
			}
			else if( $action && $action != $uriParts[ $iOffset ] )
			{
				return [];
			}
			else if( $detail[ 'param' ] )
			{
				$params[ $detail[ 'param' ] ] = $uriParts[ $iOffset ];
			}

			$iOffset++;
		}

		return $params;
	}

	/**
	 * Returns a list of routes mapped to the current request method.
	 * @param int $method
	 * @return array
	 */

	protected function getRouteArray( int $method ) : array
	{
		$routes = [];

		switch( $method )
		{
			case RequestMethod::DELETE:
				$routes = $this->_delete;
				break;

			case RequestMethod::GET:
				$routes = $this->_get;
				break;

			case RequestMethod::POST:
				$routes = $this->_post;
				break;

			case RequestMethod::PUT:
				$routes = $this->_put;
				break;
		}

		return $routes;
	}

	/**
	 * @param int $method
	 * @param string $uri
	 * @return RouteMap|null
	 * @throws \Exception
	 */

	public function getRoute( int $method, string $uri ) : ?RouteMap
	{
		$routes = $this->getRouteArray( $method );

		foreach( $routes as $route )
		{
			if( !$this->isRouteWithParams( $route ) )
			{
				$params = $this->processRoute( $route, $uri );

				if( is_array( $params ) )
				{
					$route->Parameters = [];
					return $route;
				}
			}
		}

		foreach( $routes as $route )
		{
			$params = $this->processRoute( $route, $uri );

			if( $this->isRouteWithParams( $route ) )
			{
				if( $params )
				{
					$route->Parameters = $params;
					return $route;
				}
			}
		}

		return null;
	}

	protected function executePreFilters( RouteMap $route ): mixed
	{
		foreach( $this->_filter as $filterName )
		{
			$filter = $this->getFilter( $filterName );
			$result = $filter->pre( $route );

			// If filter returns a non-null value, stop execution and return it
			if( $result !== null )
			{
				return $result;
			}
		}

		return null;
	}

	protected function executePostFilters( RouteMap $route ): void
	{
		foreach( $this->_filter as $filterName )
		{
			$filter = $this->getFilter( $filterName );
			$filter->post( $route );
		}
	}

	/**
	 * @param RouteMap $route
	 * @return mixed
	 */

	public function dispatch( RouteMap $route ): mixed
	{
		$filterResult = $this->executePreFilters( $route );

		// If a filter returned a response, return it immediately without executing the route
		if( $filterResult !== null )
		{
			return $filterResult;
		}

		$result = $route->execute( $this );

		$this->executePostFilters( $route );

		return $result;
	}

	/**
	 * @param array $argv
	 * @return mixed result of route lambda.
	 * @throws \Exception
	 */

	function run( array $argv = [] ) : mixed
	{
		if( !$argv || !array_key_exists( 'route', $argv ) )
		{
			Log::error( "Missing route." );
			throw new \Exception( 'Missing route.' );
		}

		if( !$argv || !array_key_exists( 'type', $argv ) )
		{
			Log::error( "Missing method type." );
			throw new \Exception( 'Missing method type.' );
		}

		$type = '';

		if( array_key_exists( 'type', $argv ) )
		{
			$type = $argv[ 'type' ];
		}

		$uri = $argv[ 'route' ];

		// Normalize empty route to root path
		// When .htaccess passes ?route= with no value, normalize to '/'
		if( $uri === '' )
		{
			$uri = '/';
		}

		// Apply URL rewrites before route matching
		$uri = $this->rewriteUrl( $uri );

		$route = $this->getRoute( RequestMethod::getType( $type ), $uri );

		if( !$route )
		{
			Log::warning( "No route for: " . $argv[ 'route' ] );
			$route = $this->getRoute( RequestMethod::GET, '/404' );

			if( $route )
			{
				$route->Parameters = $argv;
			}
			else
			{
				Log::error( "Missing 404 route." );
				throw new \Exception( "Missing 404 route." );
			}
		}

		if( array_key_exists( 'extra', $argv ) )
		{
			$route->Parameters = array_merge( $route->Parameters, $argv[ 'extra' ] );
		}

		$route->Parameters = array_merge( $route->Parameters, $route->Payload );

		Log::debug( "Dispatching: $type " . $argv[ 'route' ] . " using: " . $route->getPath() );
		return $this->dispatch( $route );
	}

	/**
	 * Find a route by name across all HTTP methods.
	 *
	 * @param string $name The route name to search for
	 * @return RouteMap|null The route if found, null otherwise
	 */
	public function getRouteByName( string $name ): ?RouteMap
	{
		$allRoutes = array_merge(
			$this->_get,
			$this->_post,
			$this->_put,
			$this->_delete
		);

		foreach( $allRoutes as $route )
		{
			if( $route->getName() === $name )
			{
				return $route;
			}
		}

		return null;
	}

	/**
	 * Generate a URL for a named route with optional parameters.
	 * 
	 * @param string $name The route name
	 * @param array $parameters Parameters to substitute in the route path
	 * @param bool $absolute Whether to return an absolute URL
	 * @return string|null The generated URL or null if route not found
	 */
	public function generateUrl( string $name, array $parameters = [], bool $absolute = false ): ?string
	{
		$route = $this->getRouteByName( $name );
		
		if( !$route )
		{
			return null;
		}

		$path = $route->getPath();
		
		// Replace route parameters with actual values
		foreach( $parameters as $key => $value )
		{
			$path = str_replace( ':' . $key, $value, $path );
		}

		// If absolute URL requested, prepend base URL
		if( $absolute )
		{
			$baseUrl = Registry::getInstance()->get( RegistryKeys::BASE_URL );
			if( $baseUrl )
			{
				return rtrim( $baseUrl, '/' ) . $path;
			}
		}

		return $path;
	}

	/**
	 * Get all routes with their names for debugging/inspection.
	 *
	 * @return array Array of route information
	 */
	public function getAllNamedRoutes(): array
	{
		$namedRoutes = [];
		$allRoutes = [
			'GET' => $this->_get,
			'POST' => $this->_post,
			'PUT' => $this->_put,
			'DELETE' => $this->_delete
		];

		foreach( $allRoutes as $method => $routes )
		{
			foreach( $routes as $route )
			{
				$name = $route->getName();
				if( $name )
				{
					$namedRoutes[] = [
						'name' => $name,
						'method' => $method,
						'path' => $route->getPath()
					];
				}
			}
		}

		return $namedRoutes;
	}
}
