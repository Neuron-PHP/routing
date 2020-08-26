<?php

namespace Neuron\Routing;

use Neuron\Data\StringData;
use Neuron\Patterns\Singleton\Memory;

use \Neuron\Patterns\IRunnable;

/**
 * Class Router
 * @package Notion
 */

class Router extends Memory implements IRunnable
{
	private array $_Delete  = [];
	private array $_Get     = [];
	private array $_Post    = [];
	private array $_Put     = [];
	private array $_Filter  = [];

	private array $_FilterRegistry = [];

	/**
	 * @param string $Name
	 * @param Filter $Filter
	 */
	public function registerFilter( string $Name, Filter $Filter )
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
	 * @param $sRoute
	 * @param $function
	 * @return RouteMap
	 * @param $Filter
	 * @throws \Exception
	 */
	public function delete( $sRoute, $function, $Filter = null ) : RouteMap
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
	public function get( $sRoute, $function, $Filter = null ) : RouteMap
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
	public function post( $sRoute, $function, $Filter = null ) : RouteMap
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
	public function put( $sRoute, $function, $Filter = null ) : RouteMap
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
	 * @param $Uri
	 * @param $Details
	 * @return array
	 */
	protected function extractRouteParams( $Uri, $Details ) : array
	{
		if( $Uri && $Uri[ 0 ]  == '/' )
		{
			$String = new StringData( $Uri );
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
			if( $action )
			{
				if( $action != $Part )
				{
					return [];
				}
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
	 * @param $iMethod
	 * @return array
	 */

	protected function getRouteArray( $iMethod ) : array
	{
		$Routes = [];

		switch( $iMethod )
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
	 * @param $Uri
	 * @param $Method
	 * @return RouteMap|null
	 * @throws \Exception
	 */

	public function getRoute( $Method, $Uri ) : ?RouteMap
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
					if( is_array( $Params ) )
					{
						$Route->Parameters = $Params;
					}
					else
					{
						$Route->Parameters = [];
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
	 * @param RouteMap $Route
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

		$Type = '';

		if( array_key_exists( 'type', $Argv ) )
		{
			$Type = $Argv[ 'type' ];
		}

		$Route = $this->getRoute( RequestMethod::getType( $Type ), $Argv[ 'route' ] );

		if( !$Route )
		{
			$Route = $this->getRoute( RequestMethod::GET, '/404' );

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

		$Route->Parameters = array_merge( $Route->Parameters, $Route->Payload );

		return $this->dispatch( $Route );
	}
}
