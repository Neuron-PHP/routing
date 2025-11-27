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
	public string $Filter;
	public string $Name;
	public array $Payload;

	/**
	 * RouteMap constructor.
	 * @param $path string route path i.e. /part/new or /part/:id
	 * @param $function callable the function to call on a matching route.
	 * @param $filter string the name of the filter to match with this route.
	 * @throws Exception
	 */

	public function __construct( string $path, callable $function, string $filter = '' )
	{
		if( !is_callable( $function ) )
		{
			throw new Exception( 'RouteMap: function not callable.' );
		}

		$this->Path       = $path;
		$this->Function   = $function;
		$this->Parameters = [];
		$this->Filter     = $filter;
		$this->Name       = '';
		$this->Payload    = [];
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
	 * @return string|null
	 */
	public function getFilter()
	{
		return $this->Filter;
	}

	/**
	 * @param string $filter
	 * @return RouteMap
	 */
	public function setFilter( string $filter ) : RouteMap
	{
		$this->Filter = $filter;
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->Name;
	}

	/**
	 * @param mixed $name
	 * @return RouteMap
	 */
	public function setName( $name ) : RouteMap
	{
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
		$filter = null;

		if( $this->Filter )
		{
			$filter = $router->getFilter( $this->Filter );
		}

		if( $filter )
		{
			$filter->pre( $this );
		}

		$function = $this->Function;

		$result = $function( $this->Parameters );

		if( $filter )
		{
			$filter->post( $this );
		}

		return $result;
	}
}
