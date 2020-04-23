<?php

use PHPUnit\Framework\TestCase;

/**
 * Created by PhpStorm.
 * User: lee
 * Date: 8/15/16
 * Time: 5:45 PM
 */
class RequestMethodTest extends PHPUnit\Framework\TestCase
{
	public function testMethod()
	{
		$this->assertEquals(
			\Routing\RequestMethod::getType( 'GET' ),
			\Routing\RequestMethod::GET
		);

		$this->assertEquals(
			\Routing\RequestMethod::getType( 'PUT' ),
			\Routing\RequestMethod::PUT
		);

		$this->assertEquals(
			\Routing\RequestMethod::getType( 'DELETE' ),
			\Routing\RequestMethod::DELETE
		);

		$this->assertEquals(
			\Routing\RequestMethod::getType( 'POST' ),
			\Routing\RequestMethod::POST
		);

		$this->assertEquals(
			\Routing\RequestMethod::getType( 'FOO' ),
			\Routing\RequestMethod::UNKNOWN
		);

	}
}
