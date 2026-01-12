<?php

namespace Neuron\Routing\Exceptions;

use Exception;

/**
 * Exception thrown when duplicate routes are detected during registration.
 *
 * This exception helps developers identify routing conflicts early by providing
 * detailed information about both the original and duplicate route definitions.
 *
 * @package Neuron\Routing\Exceptions
 */
class DuplicateRouteException extends Exception
{
	private string $routeMethod;
	private string $routePath;
	private string $firstDefinition;
	private string $secondDefinition;
	private ?string $routeName;

	/**
	 * Create a new duplicate route exception.
	 *
	 * @param string $method HTTP method (GET, POST, etc.)
	 * @param string $path Route path (e.g., /users/:id)
	 * @param string $first First route definition (controller@method)
	 * @param string $second Second (duplicate) route definition
	 * @param string|null $name Optional route name if the conflict is name-based
	 */
	public function __construct(
		string $method,
		string $path,
		string $first,
		string $second,
		?string $name = null
	)
	{
		$this->routeMethod = $method;
		$this->routePath = $path;
		$this->firstDefinition = $first;
		$this->secondDefinition = $second;
		$this->routeName = $name;

		$message = $this->buildMessage();
		parent::__construct( $message );
	}

	/**
	 * Build a detailed error message for the duplicate route.
	 *
	 * @return string
	 */
	protected function buildMessage(): string
	{
		if( $this->routeName )
		{
			return sprintf(
				"Duplicate route name detected: '%s'\n" .
				"  First:  %s %s → %s\n" .
				"  Second: %s %s → %s\n" .
				"Suggestion: Use different route names or remove one of the routes.",
				$this->routeName,
				$this->routeMethod,
				$this->routePath,
				$this->firstDefinition,
				$this->routeMethod,
				$this->routePath,
				$this->secondDefinition
			);
		}

		return sprintf(
			"Duplicate route detected: %s %s\n" .
			"  First:  %s\n" .
			"  Second: %s\n" .
			"Suggestion: Use different paths, different HTTP methods, or combine into one controller method.",
			$this->routeMethod,
			$this->routePath,
			$this->firstDefinition,
			$this->secondDefinition
		);
	}

	/**
	 * Get the HTTP method of the duplicate route.
	 *
	 * @return string
	 */
	public function getRouteMethod(): string
	{
		return $this->routeMethod;
	}

	/**
	 * Get the path of the duplicate route.
	 *
	 * @return string
	 */
	public function getRoutePath(): string
	{
		return $this->routePath;
	}

	/**
	 * Get the first route definition.
	 *
	 * @return string
	 */
	public function getFirstDefinition(): string
	{
		return $this->firstDefinition;
	}

	/**
	 * Get the second (duplicate) route definition.
	 *
	 * @return string
	 */
	public function getSecondDefinition(): string
	{
		return $this->secondDefinition;
	}

	/**
	 * Get the route name if this is a name-based conflict.
	 *
	 * @return string|null
	 */
	public function getRouteName(): ?string
	{
		return $this->routeName;
	}
}
