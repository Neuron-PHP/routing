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
	private $_RequestMethod;
	private $_Path;
	private $_Route;
	private $_Get;
	private $_Post;
	private $_Server;
	private ?IIpResolver $_ipResolver;
	private ?string $_sourceIp = null;

	/**
	 * Request constructor.
	 * @param RouteMap $Route
	 * @param $Method
	 * @param IIpResolver|null $ipResolver Optional IP resolver
	 */
	public function __construct( RouteMap $Route, $Method, ?IIpResolver $ipResolver = null )
	{
		$this->_Get           = new Get();
		$this->_Post          = new Post();
		$this->_Server        = new Server();
		$this->_Route         = $Route;
		$this->_RequestMethod = $Method;
		$this->_ipResolver    = $ipResolver;
	}

	/**
	 * @return mixed
	 */
	public function getMethod()
	{
		return $this->_RequestMethod;
	}

	/**
	 * @return mixed
	 */
	public function getPath()
	{
		return $this->_Path;
	}

	/**
	 * @return RouteMap
	 */
	public function getRoute()
	{
		return $this->_Route;
	}

	/**
	 * @param $Name
	 * @return mixed
	 */
	public function getUrlParam( $Name ): mixed
	{
		return $this->_Get->filterScalar( $Name );
	}

	/**
	 * @param $Name
	 * @return mixed
	 */
	public function getPostParam( $Name ): mixed
	{
		return $this->_Post->filterScalar( $Name );
	}

	/**
	 * @param $Name
	 * @return mixed
	 */
	public function getRequest( $Name ): mixed
	{
		$Result = $this->get( $Name );

		if( !$Result )
		{
			$Result = $this->post( $Name );
		}

		return $Result;
	}

	/**
	 * @param $Name
	 */
	public function getRouteParam( $Name )
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
