<?php

namespace Routing;

class RequestMethod
{
	const PUT      = 1;
	const POST     = 2;
	const GET      = 3;
	const DELETE   = 5;
	const UNKNOWN  = 256;

	/**
	 * Gets the text string for a type.
	 * @param $Method string type override.
	 * @return int
	 */

	static public function getType( $Method )
	{
		switch( $Method )
		{
			case 'PUT':
				return self::PUT;

			case 'POST':
				return self::POST;

			case 'GET':
				return self::GET;

			case 'DELETE':
				return self::DELETE;

			default:
				return self::UNKNOWN;
		}
	}
}
