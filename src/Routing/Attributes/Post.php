<?php

namespace Neuron\Routing\Attributes;

use Attribute;

/**
 * POST route attribute for defining POST HTTP routes on controller methods.
 *
 * @package Neuron\Routing\Attributes
 *
 * @example
 * ```php
 * #[Post('/users', filters: ['csrf'])]
 * public function store() { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Post extends Route
{
	/**
	 * @param string $path The route path (e.g., '/users')
	 * @param string|null $name Optional route name for URL generation
	 * @param array $filters Array of filter names to apply to this route
	 */
	public function __construct(
		string $path,
		?string $name = null,
		array $filters = []
	)
	{
		parent::__construct( $path, 'POST', $name, $filters );
	}
}
