<?php

namespace Neuron\Routing;

/**
 * Filters allow arbitrary code to be run before or after a route is executed.
 */
class Filter
{
	private ?\Closure $_preFn;
	private ?\Closure $_postFn;

	/**
	 * @param \Closure|null $preFn
	 * @param \Closure|null $postFn
	 */
	public function __construct( ?\Closure $preFn, ?\Closure $postFn = null )
	{
		$this->_preFn  = $preFn;
		$this->_postFn = $postFn;
	}

	/**
	 * @param RouteMap $route
	 * @return mixed|null
	 */
	public function pre( RouteMap $route )
	{
		if( !$this->_preFn )
		{
			return null;
		}

		$function = $this->_preFn;

		return $function( $route );
	}

	/**
	 * @param RouteMap $route
	 * @return mixed|null
	 */
	public function post( RouteMap $route )
	{
		if( !$this->_postFn )
		{
			return null;
		}

		$function = $this->_postFn;

		return $function( $route );
	}
}
