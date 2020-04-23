<?php

namespace Routing;

class RouteMap
{
	public $Path;
	public $Function;
	public $Parameters;
	public $Filter;
	public $Name;

	/**
	 * RouteMap constructor.
	 * @param $path string route path i.e. /part/new or /part/:id
	 * @param $function the function to call on a matching route.
	 * @param $Filter string the name of the filter to match with this route.
	 * @throws \Exception
	 */

	public function __construct( $path, $function, $Filter = null )
	{
		if( !is_callable( $function ) )
		{
			throw new \Exception( 'RouteMap: function not callable.' );
		}

		$this->Path       = $path;
		$this->Function   = $function;
		$this->Parameters = null;
		$this->Filter     = $Filter;
	}

	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->Path;
	}

	/**
	 * @param string $Path
	 * @return RouteMap
	 */
	public function setPath( $Path )
	{
		$this->Path = $Path;
		return $this;
	}

	/**
	 * @return callable|the
	 */
	public function getFunction()
	{
		return $this->Function;
	}

	/**
	 * @param callable|the $Function
	 * @return RouteMap
	 */
	public function setFunction( $Function )
	{
		$this->Function = $Function;
		return $this;
	}

	/**
	 * @return null
	 */
	public function getParameters()
	{
		return $this->Parameters;
	}

	/**
	 * @param null $Parameters
	 * @return RouteMap
	 */
	public function setParameters( $Parameters )
	{
		$this->Parameters = $Parameters;
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
	 * @param string|null $Filter
	 * @return RouteMap
	 */
	public function setFilter( $Filter )
	{
		$this->Filter = $Filter;
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
	 * @param mixed $Name
	 * @return RouteMap
	 */
	public function setName( $Name )
	{
		$this->Name = $Name;
		return $this;
	}


	/**
	 * Extracts the template array from the route definition.
	 * @return array
	 * @throws \Exception
	 */

	public function parseParams()
	{
		$aDetails = [];

		$aParts = explode( '/', $this->Path );
		array_shift( $aParts );

		foreach( $aParts as $sPart )
		{
			if( substr( $sPart, 0, 1 ) == ':' )
			{
				$Param = substr( $sPart, 1 );

				$this->checkForDuplicateParams( $Param, $aDetails );

				$aDetails[] = [
					'param'  => $Param,
					'action' => false
				];
			}
			else
			{
				$aDetails[] = [
					'param'  => false,
					'action' => $sPart
				];
			}
		}
		return $aDetails;
	}

	/**
	 * @param $Param
	 * @param $Params
	 * @throws RouteParamException
	 */

	protected function checkForDuplicateParams( $Param, $Params )
	{
		foreach( $Params as $Current )
		{
			if( $Param == $Current[ 'param' ] )
			{
				throw new RouteParamException( "Duplicate parameter '$Param' found for route {$this->Path}'." );
			}
		}
	}

	/**
	 * @param Router $Router
	 * @return mixed
	 * @throws \Exception
	 */
	public function execute( Router $Router )
	{
		$Filter = null;

		if( $this->Filter )
		{
			$Filter = $Router->getFilter( $this->Filter );
		}

		if( $Filter )
		{
			$Filter->pre( $this );
		}

		$Function = $this->Function;

		$Result = $Function( $this->Parameters );

		if( $Filter )
		{
			$Filter->post( $this );
		}

		return $Result;
	}
}
