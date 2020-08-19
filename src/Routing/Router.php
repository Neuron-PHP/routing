<?php

namespace Routing;

use Neuron\Data\StringData;
use Neuron\Patterns\Singleton\Memory;
use Routing;

use \Neuron\Patterns\IRunnable;

/**
 * Class Router
 * @package Notion
 */

class Router extends Memory implements IRunnable
{
	private array $_Delete = [];
	private array $_Get    = [];
	private array $_Post   = [];
	private array $_Put    = [];
	private array $_Filter = [];

	private array $_FilterRegistry = [];

	/**
	 * @param $Name
	 * @param Filter $Filter
	 */
	public function registerFilter( $Name, Filter $Filter )
	{
		$this->_FilterRegistry[ $Name ] = $Filter;
	}

	/**
	 * @param string $Name
	 * @return Filter
	 * @throws \Exception
	 */

	public function getFilter( string $Name ) : Filter
	{
		$Filter = null;

		if( array_key_exists( $Name, $this->_FilterRegistry ) )
		{
			$Filter = $this->_FilterRegistry[ $Name ];
		}
		else
		{
			throw new \Exception( "Filter $Name not registered." );
		}

		return $Filter;
	}

	/**
	 * @param string $Filter
	 */
	public function addFilter( string $Filter )
	{
		$this->_Filter[] = $Filter;
	}

	/**
	 * @param array $aRoutes
	 * @param $sRoute
	 * @param $function
	 * @param $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	protected function addRoute( array &$aRoutes, $sRoute, $function, $Filter ) : Routing\RouteMap
	{
		$Route = new Routing\RouteMap( $sRoute, $function, $Filter );
		$aRoutes[] = $Route;

		return $Route;
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @return RouteMap
	 * @param $Filter
	 * @throws \Exception
	 */
	public function delete( $sRoute, $function, $Filter = null )
	{
		return $this->addRoute( $this->_Delete, $sRoute, $function, $Filter );
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @param null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function get( $sRoute, $function, $Filter = null ) : Routing\RouteMap
	{
		return $this->addRoute( $this->_Get, $sRoute, $function, $Filter );
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @return RouteMap
	 * @param $Filter
	 * @throws \Exception
	 */
	public function post( $sRoute, $function, $Filter = null ) : Routing\RouteMap
	{
		return $this->addRoute( $this->_Post, $sRoute, $function, $Filter );
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @param $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function put( $sRoute, $function, $Filter = null ) : Routing\RouteMap
	{
		return $this->addRoute( $this->_Put, $sRoute, $function, $Filter );
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
	 * @param $Route
	 * @param $sUri
	 * @return array
	 * @throws \Exception
	 */
	protected function processRoute( RouteMap $Route, $sUri ) : ?array
	{
		// Does route have parameters?

		if( $this->isRouteWithParams( $Route ) )
		{
			$Segments = count( explode( '/', $sUri ) );

			$RouteSegments = count( explode( '/', $Route->Path ) );

			if( $Segments == $RouteSegments )
			{
				return $this->processRouteWithParameters( $Route, $sUri );
			}
		}
		else
		{
			if( !$sUri )
			{
				$sUri = '/';
			}
			else if( $sUri[ 0 ] != '/' )
			{
				$sUri = '/' . $sUri;
			}

			if( $Route->Path == $sUri )
			{
				return [];
			}
		}

		return null;
	}

	/**
	 * @param RouteMap $Route
	 * @param $sUri
	 * @return array
	 * @throws \Exception
	 */
	protected function processRouteWithParameters( RouteMap $Route, $sUri )
	{
		$aDetails = $Route->parseParams();

		return $this->extractRouteParams( $sUri, $aDetails );
	}

	/**
	 * Populates a param array with the data from the uri.
	 * @param $sUri
	 * @param $aDetails
	 * @return array
	 */
	protected function extractRouteParams( $sUri, $aDetails )
	{
		if( $sUri && $sUri[ 0 ]  == '/' )
		{
			$String = new StringData( $sUri );
			$sUri   = $String->right( $String->length() - 1 );
		}

		$aUri = explode( '/', $sUri );

		$aParams = [];
		$iOffset = 0;

		foreach( $aUri as $sPart )
		{
			if( $iOffset >= count( $aDetails ) )
			{
				return [];
			}

			$action = $aDetails[ $iOffset ][ 'action' ];
			if( $action )
			{
				if( $action != $sPart )
				{
					return [];
				}
			}
			else
			{
				$aParams[ $aDetails[ $iOffset ][ 'param' ] ] = $sPart;
			}

			$iOffset++;
		}
		return $aParams;
	}

	/**
	 * Returns a list of routes mapped to the current request method.
	 * @param $iMethod
	 * @return array
	 */

	protected function getRouteArray( $iMethod )
	{
		$aRoutes = [];

		switch( $iMethod )
		{
			case RequestMethod::DELETE:
				$aRoutes = $this->_Delete;
				break;

			case RequestMethod::GET:
				$aRoutes = $this->_Get;
				break;

			case RequestMethod::POST:
				$aRoutes = $this->_Post;
				break;

			case RequestMethod::PUT:
				$aRoutes = $this->_Put;
				break;
		}

		return $aRoutes;
	}

	/**
	 * @param $Uri
	 * @param $Method
	 * @return \Routing\RouteMap
	 * @throws \Exception
	 */

	public function getRoute( $Method, $Uri )
	{
		$Routes = $this->getRouteArray( $Method );

		foreach( $Routes as $Route )
		{
			if( !$this->isRouteWithParams( $Route ) )
			{
				$Params = $this->processRoute( $Route, $Uri );

				if( is_array( $Params ) )
				{
					$Route->Parameters = null;
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
					if( is_array( $Params ) )
					{
						$Route->Parameters = $Params;
					}
					else
					{
						$Route->Parameters = null;
					}

					return $Route;
				}
			}
		}

		return null;
	}

	protected function executePreFilters( RouteMap $Route )
	{
		foreach( $this->_Filter as $FilterName )
		{
			$Filter = $this->getFilter( $FilterName );
			$Filter->pre( $Route );
		}
	}

	protected function executePostFilters( RouteMap $Route )
	{
		foreach( $this->_Filter as $FilterName )
		{
			$Filter = $this->getFilter( $FilterName );
			$Filter->post( $Route );
		}
	}

	/**
	 * @param \Routing\RouteMap $Route
	 * @return mixed
	 */

	public function dispatch( RouteMap $Route )
	{
		$this->executePreFilters( $Route );

		$Result = $Route->execute( $this );

		$this->executePostFilters( $Route );

		return $Result;
	}

	/**
	 * @param array|null $Argv
	 * @return result of route lambda.
	 * @throws \Exception
	 */

	function run( array $Argv = [] )
	{
		if( !$Argv || !array_key_exists( 'route', $Argv ) )
		{
			throw new \Exception( 'Missing route.' );
		}

		if( !$Argv || !array_key_exists( 'type', $Argv ) )
		{
			throw new \Exception( 'Missing method type.' );
		}

		$sType = '';

		if( array_key_exists( 'type', $Argv ) )
		{
			$sType = $Argv[ 'type' ];
		}

		$Route = $this->getRoute( Routing\RequestMethod::getType( $sType ), $Argv[ 'route' ] );

		if( !$Route )
		{
			$Route = $this->getRoute( Routing\RequestMethod::GET, '404' );

			if( $Route )
			{
				$Route->Parameters = $Argv;
			}
			else
			{
				throw new \Exception( "Missing 404 route." );
			}
		}

		if( array_key_exists( 'extra', $Argv ) )
		{
			if( is_array( $Route->Parameters ) )
			{
				$Route->Parameters = array_merge( $Route->Parameters, $Argv[ 'extra' ] );
			}
			else
			{
				$Route->Parameters = $Argv[ 'extra' ];
			}
		}

		return $this->dispatch( $Route );
	}
}
