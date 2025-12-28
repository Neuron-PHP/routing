<?php

namespace Neuron\Routing\Attributes;

use Attribute;

/**
 * GET route attribute for defining GET HTTP routes on controller methods.
 *
 * @package Neuron\Routing\Attributes
 *
 * @example
 * ```php
 * #[Get('/users')]
 * public function index() { }
 *
 * #[Get('/users/:id', name: 'users.show', filters: ['auth'])]
 * public function show(int $id) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Get extends Route
{
	/**
	 * @param string $path The route path (e.g., '/users/:id')
	 * @param string|null $name Optional route name for URL generation
	 * @param array $filters Array of filter names to apply to this route
	 */
	public function __construct(
		string $path,
		?string $name = null,
		array $filters = []
	)
	{
		parent::__construct( $path, 'GET', $name, $filters );
	}
}
