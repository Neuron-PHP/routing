<?php

namespace Neuron\Routing;

/**
 * Data class representing a route definition discovered via attributes.
 *
 * This class holds all the information needed to register a route with the Router.
 *
 * @package Neuron\Routing
 */
class RouteDefinition
{
	/**
	 * @param string $path Route path (e.g., '/users/:id')
	 * @param string $method HTTP method (GET, POST, PUT, DELETE)
	 * @param string $controller Controller class name
	 * @param string $action Controller method name
	 * @param string|null $name Optional route name
	 * @param array $filters Array of filter names
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $method,
		public readonly string $controller,
		public readonly string $action,
		public readonly ?string $name = null,
		public readonly array $filters = []
	)
	{
	}

	/**
	 * Get the controller method in the format expected by Router
	 *
	 * @return string Format: "ClassName@methodName"
	 */
	public function getControllerMethod(): string
	{
		return $this->controller . '@' . $this->action;
	}
}
