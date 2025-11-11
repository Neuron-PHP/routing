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
	public static function delete( string $route, $function ) : RouteMap
	{
		/** @var Router $router */
		$router = Router::getInstance();

		return $router->delete( $route, $function );
	}

	/**
	 * @throws \Exception
	 */
	public static function get( string $route, $function ) : RouteMap
	{
		/** @var Router $router */
		$router = Router::getInstance();

		return $router->get( $route, $function );
	}

	/**
	 * @throws \Exception
	 */
	public static function post( string $route, $function ) : RouteMap
	{
		/** @var Router $router */
		$router = Router::getInstance();

		return $router->post( $route, $function );
	}

	/**
	 * @throws \Exception
	 */
	public static function put( string $route, $function ) : RouteMap
	{
		/** @var Router $router */
		$router = Router::getInstance();

		return $router->put( $route, $function );
	}

	/**
	 * @throws \Exception
	 */
	public static function dispatch( array $params )
	{
		/** @var Router $router */
		$router = Router::getInstance();

		return $router->run( $params );
	}
}
