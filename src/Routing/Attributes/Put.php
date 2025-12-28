<?php

namespace Neuron\Routing\Attributes;

use Attribute;

/**
 * PUT route attribute for defining PUT HTTP routes on controller methods.
 *
 * @package Neuron\Routing\Attributes
 *
 * @example
 * ```php
 * #[Put('/users/:id', filters: ['auth', 'csrf'])]
 * public function update(int $id) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Put extends Route
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
		parent::__construct( $path, 'PUT', $name, $filters );
	}
}
