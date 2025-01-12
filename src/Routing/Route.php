<?php

namespace Neuron\Routing;

/**
 * Static wrapper for the Router singleton.
 */
class Route
{
	/**
	 * @throws \Exception
	 */
	public static function delete( string $Route, $Function ) : RouteMap
	{
		/** @var Router $Router */
		$Router = Router::getInstance();

		return $Router->delete( $Route, $Function );
	}

	/**
	 * @throws \Exception
	 */
	public static function get( string $Route, $Function ) : RouteMap
	{
		/** @var Router $Router */
		$Router = Router::getInstance();

		return $Router->get( $Route, $Function );
	}

	/**
	 * @throws \Exception
	 */
	public static function post( string $Route, $Function ) : RouteMap
	{
		/** @var Router $Router */
		$Router = Router::getInstance();

		return $Router->post( $Route, $Function );
	}

	/**
	 * @throws \Exception
	 */
	public static function put( string $Route, $Function ) : RouteMap
	{
		/** @var Router $Router */
		$Router = Router::getInstance();

		return $Router->put( $Route, $Function );
	}

	/**
	 * @throws \Exception
	 */
	public static function dispatch( array $Params )
	{
		/** @var Router $Router */
		$Router = Router::getInstance();

		return $Router->run( $Params );
	}
}
