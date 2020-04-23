<?php

namespace Routing;

class Filter
{
	private $_PreFn;
	private $_PostFn;

	public function __construct( $PreFn, $PostFn = null )
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
