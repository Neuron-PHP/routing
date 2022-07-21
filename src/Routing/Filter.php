<?php

namespace Neuron\Routing;

/**
 * Filters allow arbitrary code to be run before or after a route is executed.
 */
class Filter
{
	private ?\Closure $_PreFn;
	private ?\Closure $_PostFn;

	/**
	 * @param \Closure|null $PreFn
	 * @param \Closure|null $PostFn
	 */
	public function __construct( ?\Closure $PreFn, ?\Closure $PostFn = null )
	{
		$this->_PreFn  = $PreFn;
		$this->_PostFn = $PostFn;
	}

	/**
	 * @param RouteMap $Route
	 * @return mixed|null
	 */
	public function pre( RouteMap $Route )
	{
		if( !$this->_PreFn )
		{
			return null;
		}

		$Function = $this->_PreFn;

		return $Function( $Route );
	}

	/**
	 * @param RouteMap $Route
	 * @return mixed|null
	 */
	public function post( RouteMap $Route )
	{
		if( !$this->_PostFn )
		{
			return null;
		}

		$Function = $this->_PostFn;

		return $Function( $Route );
	}
}
