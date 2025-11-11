<?php

namespace Neuron\Routing;

use Neuron\Data\Filter\Get;
use Neuron\Data\Filter\Post;
use Neuron\Data\Filter\Server;

/**
 *
 */
class Request
{
	private $_requestMethod;
	private $_path;
	private $_route;
	private $_get;
	private $_post;
	private $_server;
	private ?IIpResolver $_ipResolver;
	private ?string $_sourceIp = null;

	/**
	 * Request constructor.
	 * @param RouteMap $route
	 * @param $method
	 * @param IIpResolver|null $ipResolver Optional IP resolver
	 */
	public function __construct( RouteMap $route, $method, ?IIpResolver $ipResolver = null )
	{
		$this->_get           = new Get();
		$this->_post          = new Post();
		$this->_server        = new Server();
		$this->_route         = $route;
		$this->_requestMethod = $method;
		$this->_ipResolver    = $ipResolver;
	}

	/**
	 * @return mixed
	 */
	public function getMethod()
	{
		return $this->_requestMethod;
	}

	/**
	 * @return mixed
	 */
	public function getPath()
	{
		return $this->_path;
	}

	/**
	 * @return RouteMap
	 */
	public function getRoute()
	{
		return $this->_route;
	}

	/**
	 * @param $name
	 * @return mixed
	 */
	public function getUrlParam( $name ): mixed
	{
		return $this->_get->filterScalar( $name );
	}

	/**
	 * @param $name
	 * @return mixed
	 */
	public function getPostParam( $name ): mixed
	{
		return $this->_post->filterScalar( $name );
	}

	/**
	 * @param $name
	 * @return mixed
	 */
	public function getRequest( $name ): mixed
	{
		$result = $this->get( $name );

		if( !$result )
		{
			$result = $this->post( $name );
		}

		return $result;
	}

	/**
	 * @param $name
	 */
	public function getRouteParam( $name )
	{
	}

	/**
	 * Get the source IP address of the request.
	 *
	 * Uses the configured IP resolver to determine the client's IP address,
	 * falling back to DefaultIpResolver if none is configured.
	 *
	 * @return string The client IP address
	 */
	public function getSourceIp(): string
	{
		if( $this->_sourceIp === null )
		{
			$resolver = $this->_ipResolver ?? new DefaultIpResolver();
			$this->_sourceIp = $resolver->resolve( $_SERVER );
		}
		return $this->_sourceIp;
	}
}
