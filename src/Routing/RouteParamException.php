<?php

namespace Routing;

class RouteParamException extends \Exception
{
	public function __construct( $message )
	{
		parent::__construct( $message );
	}
}
