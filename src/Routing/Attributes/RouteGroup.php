<?php

namespace Neuron\Routing\Attributes;

use Attribute;

/**
 * Route group attribute for defining shared settings for all routes in a controller.
 *
 * This attribute is applied at the class level and affects all route attributes
 * within that controller. It allows you to define common prefixes and filters
 * that will be inherited by all routes.
 *
 * @package Neuron\Routing\Attributes
 *
 * @example
 * ```php
 * #[RouteGroup(prefix: '/admin', filters: ['auth'])]
 * class UsersController
 * {
 *     #[Get('/users')]  // Becomes /admin/users with 'auth' filter
 *     public function index() { }
 *
 *     #[Post('/users', filters: ['csrf'])]  // Becomes /admin/users with ['auth', 'csrf'] filters
 *     public function store() { }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RouteGroup
{
	/**
	 * @param string $prefix Path prefix to prepend to all routes (e.g., '/admin')
	 * @param array $filters Filters to apply to all routes in this group
	 */
	public function __construct(
		public readonly string $prefix = '',
		public readonly array $filters = []
	)
	{
	}

	/**
	 * Get the route prefix
	 */
	public function getPrefix(): string
	{
		return $this->prefix;
	}

	/**
	 * Get the group filters
	 */
	public function getFilters(): array
	{
		return $this->filters;
	}

	/**
	 * Apply group settings to a route path
	 *
	 * @param string $routePath The route path from the method attribute
	 * @return string The full path with prefix applied
	 */
	public function applyPrefix( string $routePath ): string
	{
		if( $this->prefix === '' )
		{
			return $routePath;
		}

		// Ensure prefix starts with / and doesn't end with /
		$prefix = '/' . trim( $this->prefix, '/' );

		// Ensure route starts with /
		$path = '/' . ltrim( $routePath, '/' );

		return $prefix . $path;
	}

	/**
	 * Merge group filters with route filters
	 *
	 * @param array $routeFilters Filters from the route attribute
	 * @return array Combined filters (group filters first, then route filters)
	 */
	public function mergeFilters( array $routeFilters ): array
	{
		return array_merge( $this->filters, $routeFilters );
	}
}
