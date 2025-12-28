<?php

namespace Neuron\Routing\Attributes;

use Attribute;

/**
 * Base route attribute for defining HTTP routes on controller methods.
 *
 * This attribute allows routes to be defined directly on controller methods,
 * providing a modern alternative to YAML-based route configuration.
 *
 * @package Neuron\Routing\Attributes
 *
 * @example
 * ```php
 * #[Route('/users/:id', method: 'GET', name: 'users.show', filters: ['auth'])]
 * public function show(int $id) {
 *     // Implementation
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
	/**
	 * @param string $path The route path (e.g., '/users/:id')
	 * @param string $method HTTP method (GET, POST, PUT, DELETE)
	 * @param string|null $name Optional route name for URL generation
	 * @param array $filters Array of filter names to apply to this route
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $method = 'GET',
		public readonly ?string $name = null,
		public readonly array $filters = []
	)
	{
	}

	/**
	 * Get the route path
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Get the HTTP method
	 */
	public function getMethod(): string
	{
		return strtoupper( $this->method );
	}

	/**
	 * Get the route name
	 */
	public function getName(): ?string
	{
		return $this->name;
	}

	/**
	 * Get the filters
	 */
	public function getFilters(): array
	{
		return $this->filters;
	}
}
