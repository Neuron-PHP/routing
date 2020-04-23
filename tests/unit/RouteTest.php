<?php

use Routing\RequestMethod;
use Routing\Route;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
	public function testDelete()
	{
		Route::delete(
			'/delete/:id',
			function()
			{
				return 'delete';
			}
		);

		$Route = \Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::DELETE,
			'/delete/1'
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/delete/:id'
		);
	}

	public function testPost()
	{
		Route::post( '/post', function(){ return 'post'; } );

		$Route = \Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::POST,
			'post'
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/post'
		);

	}

	public function testPut()
	{
		Route::put( '/put', function(){ return 'put'; } );

		$Route = \Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::PUT,
			'put'
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/put'
		);
	}

	public function testGet()
	{
		Route::get(
			'/get/:id',
			function(){ return 'get'; }
			)
			->setName( 'test.get' );

		$Route = \Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::GET,
			'/get/1'
		);

		$this->assertEquals(
			'test.get',
			$Route->getName()
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/get/:id'
		);

		$Route = \Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::GET,
			'/get/1/2'
		);

		$Route = \Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::GET,
			'/monkey/1/2'
		);

	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testDispatch()
	{
		Route::get( '/', function(){} );

		try
		{
			Route::dispatch(
				[
					'route' => '/',
					'type'  => 'GET'
				]
			);
		}
		catch( Exception $exception )
		{
			$this->fail( $exception->getMessage() );
		}

	}
}
