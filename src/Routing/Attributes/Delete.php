<?php

namespace Neuron\Routing\Attributes;

use Attribute;

/**
 * DELETE route attribute for defining DELETE HTTP routes on controller methods.
 *
 * @package Neuron\Routing\Attributes
 *
 * @example
 * ```php
 * #[Delete('/users/:id', filters: ['auth', 'csrf'])]
 * public function destroy(int $id) { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Delete extends Route
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
		parent::__construct( $path, 'DELETE', $name, $filters );
	}
}
