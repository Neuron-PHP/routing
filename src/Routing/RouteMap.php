<?php

namespace Neuron\Routing;

use Exception;
use Neuron\Core\Exceptions\RouteParam;

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
	 * @param $Path string route path i.e. /part/new or /part/:id
	 * @param $Function callable the function to call on a matching route.
	 * @param $Filter string the name of the filter to match with this route.
	 * @throws Exception
	 */

	public function __construct( string $Path, callable $Function, string $Filter = '' )
	{
		if( !is_callable( $Function ) )
		{
			throw new Exception( 'RouteMap: function not callable.' );
		}

		$this->Path       = $Path;
		$this->Function   = $Function;
		$this->Parameters = [];
		$this->Filter     = $Filter;
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
	 * @param string $Path
	 * @return RouteMap
	 */
	public function setPath( string $Path ) : RouteMap
	{
		$this->Path = $Path;
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
	 * @param callable $Function
	 * @return RouteMap
	 */
	public function setFunction( callable $Function ) : RouteMap
	{
		$this->Function = $Function;
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
	 * @param array $Parameters
	 * @return RouteMap
	 */
	public function setParameters( array $Parameters ) : RouteMap
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
	 * @param string $Filter
	 * @return RouteMap
	 */
	public function setFilter( string $Filter ) : RouteMap
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
	public function setName( $Name ) : RouteMap
	{
		$this->Name = $Name;
		return $this;
	}


	/**
	 * Extracts the template array from the route definition.
	 * @return array
	 * @throws Exception
	 */

	public function parseParams(): array
	{
		$Details = [];

		$Parts = explode( '/', $this->Path );
		array_shift( $Parts );

		foreach( $Parts as $Part )
		{
			if( substr( $Part, 0, 1 ) == ':' )
			{
				$Param = substr( $Part, 1 );

				$this->checkForDuplicateParams( $Param, $Details );

				$Details[] = [
					'param'  => $Param,
					'action' => false
				];
			}
			else
			{
				$Details[] = [
					'param'  => false,
					'action' => $Part
				];
			}
		}
		return $Details;
	}

	/**
	 * @param $Param
	 * @param $Params
	 * @throws RouteParam
	 */

	protected function checkForDuplicateParams( $Param, $Params ): void
	{
		foreach( $Params as $Current )
		{
			if( $Param == $Current[ 'param' ] )
			{
				throw new RouteParam( "Duplicate parameter '$Param' found for route {$this->Path}'." );
			}
		}
	}

	/**
	 * @param Router $Router
	 * @return mixed
	 * @throws Exception
	 */
	public function execute( Router $Router ): mixed
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
