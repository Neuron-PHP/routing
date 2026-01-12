<?php

namespace Neuron\Routing;

use Exception;
use Neuron\Core\Exceptions\RouteParam;

/**
 * Route mapping and parameter extraction for HTTP requests.
 * 
 * This class represents a single route definition within the routing system,
 * handling URL pattern matching, dynamic parameter extraction, and route
 * execution with integrated filter support.
 * 
 * Key responsibilities:
 * - Parse route templates with dynamic parameters (e.g., /users/:id)
 * - Extract parameters from incoming URIs that match the route pattern
 * - Execute the associated callable with extracted parameters
 * - Integrate with the filter system for pre/post processing
 * - Handle route-specific parameter validation and type conversion
 * 
 * Route patterns support:
 * - Static segments: /users/profile
 * - Dynamic parameters: /users/:id, /posts/:slug
 * - Mixed patterns: /users/:id/posts/:post_id
 * 
 * @package Neuron\Routing
 * 
 * @example
 * ```php
 * // Create a route for user profile
 * $route = new RouteMap('/users/:id', function($id) {
 *     return UserController::show($id);
 * }, 'auth');
 * 
 * // Execute with URI /users/123
 * $result = $route->execute('/users/123');
 * ```
 */
class RouteMap
{
	public string $Path;
	public $Function;
	public array $Parameters;
	public array $Filters;
	public string $Name;
	public array $Payload;

	/** @var Router|null Reference to router for name validation */
	private ?Router $_router = null;

	/** @var string|null HTTP method for this route (GET, POST, etc.) */
	private ?string $_method = null;

	/**
	 * RouteMap constructor.
	 * @param $path string route path i.e. /part/new or /part/:id
	 * @param $function callable the function to call on a matching route.
	 * @param $filters string|array the name(s) of the filter(s) to match with this route.
	 * @throws Exception
	 */

	public function __construct( string $path, callable $function, string|array $filters = '' )
	{
		if( !is_callable( $function ) )
		{
			throw new Exception( 'RouteMap: function not callable.' );
		}

		$this->Path       = $path;
		$this->Function   = $function;
		$this->Parameters = [];

		// Support both string (legacy) and array (new) filter specification
		if( is_string( $filters ) )
		{
			$this->Filters = $filters === '' ? [] : [ $filters ];
		}
		else
		{
			$this->Filters = $filters;
		}

		$this->Name       = '';
		$this->Payload    = [];
	}

	/**
	 * Set the router and method for this route (used for name duplicate detection)
	 * @param Router $router
	 * @param string $method HTTP method (GET, POST, PUT, DELETE)
	 * @return RouteMap
	 */
	public function setRouterContext( Router $router, string $method ): RouteMap
	{
		$this->_router = $router;
		$this->_method = $method;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->Path;
	}

	/**
	 * @param string $path
	 * @return RouteMap
	 */
	public function setPath( string $path ) : RouteMap
	{
		$this->Path = $path;
		return $this;
	}

	/**
	 * @return callable
	 */
	public function getFunction() : callable
	{
		return $this->Function;
	}

	/**
	 * @param callable $function
	 * @return RouteMap
	 */
	public function setFunction( callable $function ) : RouteMap
	{
		$this->Function = $function;
		return $this;
	}

	/**
	 * @return null
	 */
	public function getParameters() : array
	{
		return $this->Parameters;
	}

	/**
	 * @param array $parameters
	 * @return RouteMap
	 */
	public function setParameters( array $parameters ) : RouteMap
	{
		$this->Parameters = $parameters;
		return $this;
	}

	/**
	 * Get filters for this route
	 * @return array
	 */
	public function getFilters(): array
	{
		return $this->Filters;
	}

	/**
	 * Set filters for this route
	 * @param string|array $filters
	 * @return RouteMap
	 */
	public function setFilters( string|array $filters ) : RouteMap
	{
		if( is_string( $filters ) )
		{
			$this->Filters = $filters === '' ? [] : [ $filters ];
		}
		else
		{
			$this->Filters = $filters;
		}
		return $this;
	}

	/**
	 * Legacy method for backward compatibility
	 * @deprecated Use getFilters() instead
	 * @return string|null
	 */
	public function getFilter(): ?string
	{
		return count( $this->Filters ) > 0 ? $this->Filters[0] : null;
	}

	/**
	 * Legacy method for backward compatibility
	 * @deprecated Use setFilters() instead
	 * @param string $filter
	 * @return RouteMap
	 */
	public function setFilter( string $filter ) : RouteMap
	{
		return $this->setFilters( $filter );
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->Name;
	}

	/**
	 * Set the name for this route and register it with the router for duplicate detection.
	 *
	 * @param string $name
	 * @return RouteMap
	 * @throws Exceptions\DuplicateRouteException If name is already in use
	 */
	public function setName( string $name ) : RouteMap
	{
		// If the name is already set to this value, no need to re-register
		if( $this->Name === $name )
		{
			return $this;
		}

		// If router context is set, register new name and unregister old
		if( $this->_router && $this->_method )
		{
			$oldName = $this->Name;

			// Register the new name first (will check for duplicates and throw if needed)
			// Only if this succeeds do we unregister the old name
			$this->_router->registerRouteName( $name, $this->_method, $this->Path, $this );

			// Registration succeeded, safe to unregister the old name
			if( $oldName !== '' )
			{
				$this->_router->unregisterRouteName( $oldName );
			}
		}

		$this->Name = $name;
		return $this;
	}


	/**
	 * Extracts the template array from the route definition.
	 * @return array
	 * @throws Exception
	 */

	public function parseParams(): array
	{
		$details = [];

		$parts = explode( '/', $this->Path );
		array_shift( $parts );

		foreach( $parts as $part )
		{
			if( substr( $part, 0, 1 ) == '*' )
			{
				// Wildcard parameter - captures all remaining URI segments
				$param = substr( $part, 1 );

				$this->checkForDuplicateParams( $param, $details );

				$details[] = [
					'param'    => $param,
					'action'   => false,
					'wildcard' => true
				];
			}
			else if( substr( $part, 0, 1 ) == ':' )
			{
				$param = substr( $part, 1 );

				$this->checkForDuplicateParams( $param, $details );

				$details[] = [
					'param'    => $param,
					'action'   => false,
					'wildcard' => false
				];
			}
			else
			{
				$details[] = [
					'param'    => false,
					'action'   => $part,
					'wildcard' => false
				];
			}
		}
		return $details;
	}

	/**
	 * @param $param
	 * @param $params
	 * @throws RouteParam
	 */

	protected function checkForDuplicateParams( $param, $params ): void
	{
		foreach( $params as $current )
		{
			if( $param == $current[ 'param' ] )
			{
				throw new RouteParam( "Duplicate parameter '$param' found for route {$this->Path}'." );
			}
		}
	}

	/**
	 * @param Router $router
	 * @return mixed
	 * @throws Exception
	 */
	public function execute( Router $router ): mixed
	{
		$filters = [];

		// Get all filter instances for this route
		foreach( $this->Filters as $filterName )
		{
			$filters[] = $router->getFilter( $filterName );
		}

		// Execute pre-filters in order
		foreach( $filters as $filter )
		{
			$filter->pre( $this );
		}

		// Execute route function
		$function = $this->Function;
		$result = $function( $this->Parameters );

		// Execute post-filters in reverse order (LIFO)
		foreach( array_reverse( $filters ) as $filter )
		{
			$filter->post( $this );
		}

		return $result;
	}
}
