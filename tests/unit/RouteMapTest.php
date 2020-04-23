<?php

use PHPUnit\Framework\TestCase;

/**
 * Created by PhpStorm.
 * User: lee
 * Date: 8/15/16
 * Time: 5:45 PM
 */
class RouteMapTest extends PHPUnit\Framework\TestCase
{
	public function testRouteSuccess()
	{
		try
		{
			$Route = new \Routing\RouteMap( 'method', function() { return 'test';} );

			$this->assertEquals(
				$Route->Path,
				'method'
			);
		}
		catch( Exception $exception )
		{
			$this->fail( $exception->getMessage() );
		}
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testRouteFail()
	{
		try
		{
			$Route = new \Routing\RouteMap( 'method', null );

			$this->fail( 'Creation of this route should have failed.' );
		}
		catch( Exception $exception )
		{
		}
	}
}
