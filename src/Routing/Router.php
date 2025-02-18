<?php

namespace Neuron\Routing;

use Neuron\Core\NString;
use Neuron\Log\Log;
use Neuron\Patterns\IRunnable;
use Neuron\Patterns\Singleton\Memory;

/**
 * Singleton router implementation
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
	public function registerFilter( string $Name, Filter $Filter ): void
	{
		$this->_FilterRegistry[ $Name ] = $Filter;
	}

	/**
	 * @param string $routeName
	 * @return Filter
	 * @throws \Exception
	 */

	public function getFilter( string $routeName ) : Filter
	{
		$Filter = null;

		if( array_key_exists( $routeName, $this->_FilterRegistry ) )
		{
			$Filter = $this->_FilterRegistry[ $routeName ];
		}
		else
		{
			throw new \Exception( "Filter $routeName not registered." );
		}

		return $Filter;
	}

	/**
	 * @param string $Filter
	 */
	public function addFilter( string $Filter ): void
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
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter |null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function delete( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Delete, $Route, $function, $Filter );
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter |null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function get( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Get, $Route, $function, $Filter );
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function post( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Post, $Route, $function, $Filter );
	}

	/**
	 * @param string $Route
	 * @param $function
	 * @param string|null $Filter |null $Filter
	 * @return RouteMap
	 * @throws \Exception
	 */
	public function put( string $Route, $function, ?string $Filter = null ) : RouteMap
	{
		return $this->addRoute( $this->_Put, $Route, $function, $Filter );
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
	 * @param RouteMap $Route
	 * @param $Uri
	 * @return array|null
	 * @throws \Exception
	 */
	protected function processRoute( RouteMap $Route, $Uri ) : ?array
	{
		if( !$Uri )
		{
			$Uri = '/';
		}
		else if( $Uri[ 0 ] != '/' )
		{
			$Uri = '/' . $Uri;
		}

		if( strlen( $Uri ) > 1 && $Uri[ strlen( $Uri ) - 1 ] == "/" )
		{
			$string = new NString( $Uri );
			$Uri    = $string->left( $string->length() - 1 );
		}

		// Does route have parameters?

		if( $this->isRouteWithParams( $Route ) )
		{
			$Segments = count( explode( '/', $Uri ) );

			$RouteSegments = count( explode( '/', $Route->Path ) );

			if( $Segments == $RouteSegments )
			{
				return $this->processRouteWithParameters( $Route, $Uri );
			}
		}
		else
		{
			if( $Route->Path == $Uri )
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
	 * @param string $Uri
	 * @param array $Details
	 * @return array
	 */

	protected function extractRouteParams( string $Uri, array $Details ) : array
	{
		if( $Uri && $Uri[ 0 ] == '/' )
		{
			$String = new NString( $Uri );
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

			if( $action && $action != $Part )
			{
				return [];
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
	 * @param int $Method
	 * @return array
	 */

	protected function getRouteArray( int $Method ) : array
	{
		$Routes = [];

		switch( $Method )
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
	 * @param int $Method
	 * @param string $Uri
	 * @return RouteMap|null
	 * @throws \Exception
	 */

	public function getRoute( int $Method, string $Uri ) : ?RouteMap
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
					$Route->Parameters = $Params;
					return $Route;
				}
			}
		}

		return null;
	}

	protected function executePreFilters( RouteMap $Route ): void
	{
		foreach( $this->_Filter as $FilterName )
		{
			$Filter = $this->getFilter( $FilterName );
			$Filter->pre( $Route );
		}
	}

	protected function executePostFilters( RouteMap $Route ): void
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

	public function dispatch( RouteMap $Route ): mixed
	{
		$this->executePreFilters( $Route );

		$Result = $Route->execute( $this );

		$this->executePostFilters( $Route );

		return $Result;
	}

	/**
	 * @param array $Argv
	 * @return mixed result of route lambda.
	 * @throws \Exception
	 */

	function run( array $Argv = [] ) : mixed
	{
		if( !$Argv || !array_key_exists( 'route', $Argv ) )
		{
			Log::error( "Missing route." );
			throw new \Exception( 'Missing route.' );
		}

		if( !$Argv || !array_key_exists( 'type', $Argv ) )
		{
			Log::error( "Missing method type." );
			throw new \Exception( 'Missing method type.' );
		}

		$Type = '';

		if( array_key_exists( 'type', $Argv ) )
		{
			$Type = $Argv[ 'type' ];
		}

		$Uri = $Argv[ 'route' ];

		$Route = $this->getRoute( RequestMethod::getType( $Type ), $Uri );

		if( !$Route )
		{
			Log::warning( "No route for: " . $Argv[ 'route' ] );
			$Route = $this->getRoute( RequestMethod::GET, '/404' );

			if( $Route )
			{
				$Route->Parameters = $Argv;
			}
			else
			{
				Log::error( "Missing 404 route." );
				throw new \Exception( "Missing 404 route." );
			}
		}

		if( array_key_exists( 'extra', $Argv ) )
		{
			$Route->Parameters = array_merge( $Route->Parameters, $Argv[ 'extra' ] );
		}

		$Route->Parameters = array_merge( $Route->Parameters, $Route->Payload );

		Log::debug( "Dispatching: $Type " . $Argv[ 'route' ] . " using: " . $Route->getPath() );
		return $this->dispatch( $Route );
	}
}
