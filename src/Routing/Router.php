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
	private array $_Delete  = [];
	private array $_Get     = [];
	private array $_Post    = [];
	private array $_Put     = [];
	private array $_Filter  = [];

	private array $_FilterRegistry = [];
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
	 * @param string $Name
	 * @param Filter $Filter
	 */
	public function registerFilter( string $Name, Filter $Filter ): void
	{
		$this->_FilterRegistry[ $Name ] = $Filter;
	}

	/**
	 * @param string $routeName
	 * @return Filter
	 * @throws \Exception
	 */

	public function getFilter( string $routeName ) : Filter
	{
		$Filter = null;

		if( array_key_exists( $routeName, $this->_FilterRegistry ) )
		{
			$Filter = $this->_FilterRegistry[ $routeName ];
		}
		else
		{
			throw new \Exception( "Filter $routeName not registered." );
		}

		return $Filter;
	}

	/**
	 * @param string $Filter
	 */
	public function addFilter( string $Filter ): void
	{
		$this->_Filter[] = $Filter;
	}

	/**
	 * @param array $Routes
	 * @param string $RouteName
	 * @param $function
	 * @param $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	protected function addRoute( array &$Routes, string $RouteName, $function, $Filter ) : RouteMap
	{
		$Route    = new RouteMap( $RouteName, $function, $Filter ?? '' );
		$Routes[] = $Route;

		return $Route;
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter |null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function delete( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Delete, $Route, $function, $Filter );
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter |null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function get( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Get, $Route, $function, $Filter );
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function post( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Post, $Route, $function, $Filter );
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter |null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function put( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Put, $Route, $function, $Filter );
	}

	/**
	 * @param RouteMap $Route
	 * @return bool
	 */
	protected function isRouteWithParams( RouteMap $Route ) : bool
	{
		return strpos( $Route->Path, ':' ) == true;
	}

	/**
	 * @param RouteMap $Route
	 * @param $Uri
	 * @return array|null
	 * @throws \Exception
	 */
	protected function processRoute( RouteMap $Route, $Uri ) : ?array
	{
		if( !$Uri )
		{
			$Uri = '/';
		}
		else if( $Uri[ 0 ] != '/' )
		{
			$Uri = '/' . $Uri;
		}

		if( strlen( $Uri ) > 1 && $Uri[ strlen( $Uri ) - 1 ] == "/" )
		{
			$string = new NString( $Uri );
			$Uri    = $string->left( $string->length() - 1 );
		}

		// Does route have parameters?

		if( $this->isRouteWithParams( $Route ) )
		{
			$Segments = count( explode( '/', $Uri ) );

			$RouteSegments = count( explode( '/', $Route->Path ) );

			if( $Segments == $RouteSegments )
			{
				return $this->processRouteWithParameters( $Route, $Uri );
			}
		}
		else
		{
			if( $Route->Path == $Uri )
			{
				return [];
			}
		}

		return null;
	}

	/**
	 * @param RouteMap $Route
	 * @param string $Uri
	 * @return array
	 * @throws \Exception
	 */

	protected function processRouteWithParameters( RouteMap $Route, string $Uri ) : array
	{
		$Details = $Route->parseParams();

		return $this->extractRouteParams( $Uri, $Details );
	}

	/**
	 * Populates a param array with the data from the uri.
	 * @param string $Uri
	 * @param array $Details
	 * @return array
	 */

	protected function extractRouteParams( string $Uri, array $Details ) : array
	{
		if( $Uri && $Uri[ 0 ] == '/' )
		{
			$String = new NString( $Uri );
			$Uri    = $String->right( $String->length() - 1 );
		}

		$UriParts = explode( '/', $Uri );

		$Params = [];
		$iOffset = 0;

		foreach( $UriParts as $Part )
		{
			if( $iOffset >= count( $Details ) )
			{
				return [];
			}

			$action = $Details[ $iOffset ][ 'action' ];

			if( $action && $action != $Part )
			{
				return [];
			}
			else
			{
				$Params[ $Details[ $iOffset ][ 'param' ] ] = $Part;
			}

			$iOffset++;
		}

		return $Params;
	}

	/**
	 * Returns a list of routes mapped to the current request method.
	 * @param int $Method
	 * @return array
	 */

	protected function getRouteArray( int $Method ) : array
	{
		$Routes = [];

		switch( $Method )
		{
			case RequestMethod::DELETE:
				$Routes = $this->_Delete;
				break;

			case RequestMethod::GET:
				$Routes = $this->_Get;
				break;

			case RequestMethod::POST:
				$Routes = $this->_Post;
				break;

			case RequestMethod::PUT:
				$Routes = $this->_Put;
				break;
		}

		return $Routes;
	}

	/**
	 * @param int $Method
	 * @param string $Uri
	 * @return RouteMap|null
	 * @throws \Exception
	 */

	public function getRoute( int $Method, string $Uri ) : ?RouteMap
	{
		$Routes = $this->getRouteArray( $Method );

		foreach( $Routes as $Route )
		{
			if( !$this->isRouteWithParams( $Route ) )
			{
				$Params = $this->processRoute( $Route, $Uri );

				if( is_array( $Params ) )
				{
					$Route->Parameters = [];
					return $Route;
				}
			}
		}

		foreach( $Routes as $Route )
		{
			$Params = $this->processRoute( $Route, $Uri );

			if( $this->isRouteWithParams( $Route ) )
			{
				if( $Params )
				{
					$Route->Parameters = $Params;
					return $Route;
				}
			}
		}

		return null;
	}

	protected function executePreFilters( RouteMap $Route ): mixed
	{
		foreach( $this->_Filter as $FilterName )
		{
			$Filter = $this->getFilter( $FilterName );
			$Result = $Filter->pre( $Route );

			// If filter returns a non-null value, stop execution and return it
			if( $Result !== null )
			{
				return $Result;
			}
		}

		return null;
	}

	protected function executePostFilters( RouteMap $Route ): void
	{
		foreach( $this->_Filter as $FilterName )
		{
			$Filter = $this->getFilter( $FilterName );
			$Filter->post( $Route );
		}
	}

	/**
	 * @param RouteMap $Route
	 * @return mixed
	 */

	public function dispatch( RouteMap $Route ): mixed
	{
		$FilterResult = $this->executePreFilters( $Route );

		// If a filter returned a response, return it immediately without executing the route
		if( $FilterResult !== null )
		{
			return $FilterResult;
		}

		$Result = $Route->execute( $this );

		$this->executePostFilters( $Route );

		return $Result;
	}

	/**
	 * @param array $Argv
	 * @return mixed result of route lambda.
	 * @throws \Exception
	 */

	function run( array $Argv = [] ) : mixed
	{
		if( !$Argv || !array_key_exists( 'route', $Argv ) )
		{
			Log::error( "Missing route." );
			throw new \Exception( 'Missing route.' );
		}

		if( !$Argv || !array_key_exists( 'type', $Argv ) )
		{
			Log::error( "Missing method type." );
			throw new \Exception( 'Missing method type.' );
		}

		$Type = '';

		if( array_key_exists( 'type', $Argv ) )
		{
			$Type = $Argv[ 'type' ];
		}

		$Uri = $Argv[ 'route' ];

		$Route = $this->getRoute( RequestMethod::getType( $Type ), $Uri );

		if( !$Route )
		{
			Log::warning( "No route for: " . $Argv[ 'route' ] );
			$Route = $this->getRoute( RequestMethod::GET, '/404' );

			if( $Route )
			{
				$Route->Parameters = $Argv;
			}
			else
			{
				Log::error( "Missing 404 route." );
				throw new \Exception( "Missing 404 route." );
			}
		}

		if( array_key_exists( 'extra', $Argv ) )
		{
			$Route->Parameters = array_merge( $Route->Parameters, $Argv[ 'extra' ] );
		}

		$Route->Parameters = array_merge( $Route->Parameters, $Route->Payload );

		Log::debug( "Dispatching: $Type " . $Argv[ 'route' ] . " using: " . $Route->getPath() );
		return $this->dispatch( $Route );
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
			$this->_Get,
			$this->_Post,
			$this->_Put,
			$this->_Delete
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
			'GET' => $this->_Get,
			'POST' => $this->_Post,
			'PUT' => $this->_Put,
			'DELETE' => $this->_Delete
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
