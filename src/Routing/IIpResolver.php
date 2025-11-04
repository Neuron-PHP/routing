<?php

namespace Neuron\Routing;

/**
 * Interface for IP address resolution strategies.
 *
 * Implementations of this interface define how to extract
 * the client's IP address from server variables, handling
 * various proxy and load balancer configurations.
 *
 * @package Neuron\Routing
 */
interface IIpResolver
{
	/**
	 * Resolve the client IP address from server variables.
	 *
	 * @param array $server The $_SERVER array containing request information
	 * @return string The resolved IP address (returns '0.0.0.0' if unable to resolve)
	 */
	public function resolve( array $server ): string;
}