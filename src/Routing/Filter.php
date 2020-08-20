<?php

namespace Neuron\Routing;

class Filter
{
	private ?\Closure $_PreFn;
	private ?\Closure $_PostFn;

	public function __construct( ?\Closure $PreFn, ?\Closure $PostFn = null )
	{
		$this->_PreFn  = $PreFn;
		$this->_PostFn = $PostFn;
	}

	public function pre( RouteMap $Route )
	{
		if( !$this->_PreFn )
		{
			return null;
		}

		$Function = $this->_PreFn;

		return $Function( $Route );
	}

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
