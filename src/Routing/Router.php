<?php

namespace Neuron\Routing;

use Neuron\Core\NString;
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
	 * @param string $routeName
	 * @param $function
	 * @param $filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	protected function addRoute( array &$routes, string $routeName, $function, $filter ) : RouteMap
	{
		$route    = new RouteMap( $routeName, $function, $filter ?? '' );
		$routes[] = $route;

		return $route;
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|null $filter |null $filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function delete( string $route, $function, ?string $filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_delete, $route, $function, $filter );
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|null $filter |null $filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function get( string $route, $function, ?string $filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_get, $route, $function, $filter );
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|null $filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function post( string $route, $function, ?string $filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_post, $route, $function, $filter );
	}

	/**
	 * @param string $route
	 * @param $function
	 * @param string|null $filter |null $filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function put( string $route, $function, ?string $filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_put, $route, $function, $filter );
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
			$baseUrl = Registry::getInstance()->get( 'Base.Url' );
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
