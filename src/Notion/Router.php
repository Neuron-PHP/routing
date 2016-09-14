<?php

namespace Notion;

use Notion;

use \Neuron\Patterns\IRunnable;

/**
 * Class Router
 * @package Notion
 */

class Router implements IRunnable
{
	private $_aDelete = [];
	private $_aGet    = [];
	private $_aPost   = [];
	private $_aPut    = [];

	/**
	 * @param array $aRoutes
	 * @param $sRoute
	 * @param $function
	 */
	protected function addRoute( array &$aRoutes, $sRoute, $function )
	{
		$aRoutes[] = new Notion\Route( $sRoute, $function );
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @return $this
	 */
	public function delete( $sRoute, $function )
	{
		$this->addRoute( $this->_aDelete, $sRoute, $function );
		return $this;
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @return $this
	 */
	public function get( $sRoute, $function )
	{
		$this->addRoute( $this->_aGet, $sRoute, $function );
		return $this;
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @return $this
	 */
	public function post( $sRoute, $function )
	{
		$this->addRoute( $this->_aPost, $sRoute, $function );
		return $this;
	}

	/**
	 * @param $sRoute
	 * @param $function
	 * @return $this
	 */
	public function put( $sRoute, $function )
	{
		$this->addRoute( $this->_aPut, $sRoute, $function );
		return $this;
	}

	/**
	 * @param $Route
	 * @param $sUri
	 * @return array|bool
	 */
	protected function processRoute( Route $Route, $sUri )
	{
		// Does route have parameters?

		if( strpos( $Route->path, ':' ) )
		{
			return $this->processRouteWithParameters( $Route, $sUri );
		}
		else
		{
			if( $sUri[ 0 ] != '/' )
			{
				$sUri = '/' . $sUri;
			}

			if( $Route->path == $sUri )
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Route $Route
	 * @param $sUri
	 * @return array
	 */
	protected function processRouteWithParameters( Route $Route, $sUri )
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
			} else
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
				$aRoutes = $this->_aDelete;
				break;

			case RequestMethod::GET:
				$aRoutes = $this->_aGet;
				break;

			case RequestMethod::POST:
				$aRoutes = $this->_aPost;
				break;

			case RequestMethod::PUT:
				$aRoutes = $this->_aPut;
				break;
		}

		return $aRoutes;
	}

	/**
	 * @param $sUri
	 * @param $iMethod
	 * @return \Notion\Route
	 */

	public function getRoute( $iMethod, $sUri )
	{
		$aRoutes = $this->getRouteArray( $iMethod );

		foreach( $aRoutes as $Route )
		{
			$aParams = $this->processRoute( $Route, $sUri );

			if( $aParams )
			{
				if( is_array( $aParams ) )
				{
					$Route->parameters = $aParams;
				}
				else
				{
					$Route->parameters = null;
				}

				return $Route;
			}
		}

		return null;
	}

	/**
	 * @param \Notion\Route $Route
	 * @return mixed
	 */
	public function dispatch( Route $Route )
	{
		$function = $Route->function;

		return $function( $Route->parameters );
	}

	/**
	 * @param array|null $aArgv
	 * @return void
	 * @throws \Exception
	 */
	function run( array $aArgv = null )
	{
		if( !$aArgv || !array_key_exists( 'route', $aArgv ) )
		{
			throw new \Exception( 'Missing route.' );
		}

		$sType = '';

		if( array_key_exists( 'type', $aArgv ) )
		{
			$sType = $aArgv[ 'type' ];
		}

		$Route = $this->getRoute( Notion\RequestMethod::getType( $sType ), $aArgv[ 'route' ] );

		if( !$Route )
		{
			$Route = $this->getRoute( Notion\RequestMethod::GET, '404' );

			if( $Route )
			{
				$Route->parameters = $aArgv;
			}
			else
			{
				throw new \Exception( "Missing 404 route." );
			}
		}

		$this->dispatch( $Route );
	}
}
