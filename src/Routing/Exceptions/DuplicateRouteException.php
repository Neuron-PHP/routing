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
	private string $firstMethod;
	private string $firstPath;
	private string $firstDefinition;
	private string $secondMethod;
	private string $secondPath;
	private string $secondDefinition;
	private ?string $routeName;

	/**
	 * Create a new duplicate route exception.
	 *
	 * @param string $firstMethod First route's HTTP method (GET, POST, etc.)
	 * @param string $firstPath First route's path (e.g., /users/:id)
	 * @param string $first First route definition (controller@method)
	 * @param string $secondMethod Second route's HTTP method
	 * @param string $secondPath Second route's path
	 * @param string $second Second (duplicate) route definition
	 * @param string|null $name Optional route name if the conflict is name-based
	 */
	public function __construct(
		string $firstMethod,
		string $firstPath,
		string $first,
		string $secondMethod,
		string $secondPath,
		string $second,
		?string $name = null
	)
	{
		$this->firstMethod = $firstMethod;
		$this->firstPath = $firstPath;
		$this->firstDefinition = $first;
		$this->secondMethod = $secondMethod;
		$this->secondPath = $secondPath;
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
				$this->firstMethod,
				$this->firstPath,
				$this->firstDefinition,
				$this->secondMethod,
				$this->secondPath,
				$this->secondDefinition
			);
		}

		return sprintf(
			"Duplicate route detected: %s %s\n" .
			"  First:  %s\n" .
			"  Second: %s\n" .
			"Suggestion: Use different paths, different HTTP methods, or combine into one controller method.",
			$this->secondMethod,
			$this->secondPath,
			$this->firstDefinition,
			$this->secondDefinition
		);
	}

	/**
	 * Get the HTTP method of the first route.
	 *
	 * @return string
	 */
	public function getFirstMethod(): string
	{
		return $this->firstMethod;
	}

	/**
	 * Get the path of the first route.
	 *
	 * @return string
	 */
	public function getFirstPath(): string
	{
		return $this->firstPath;
	}

	/**
	 * Get the HTTP method of the second route.
	 *
	 * @return string
	 */
	public function getSecondMethod(): string
	{
		return $this->secondMethod;
	}

	/**
	 * Get the path of the second route.
	 *
	 * @return string
	 */
	public function getSecondPath(): string
	{
		return $this->secondPath;
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
