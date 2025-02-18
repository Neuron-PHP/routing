<?php

use Neuron\Log\Log;
use PHPUnit\Framework\TestCase;
use Neuron\Routing;

class RouteTest extends TestCase
{
	protected function setUp(): void
	{
		Log::setRunLevel( \Neuron\Log\ILogger::DEBUG );
		parent::setUp();
	}

	public function testDelete()
	{
		Routing\Route::delete(
			'/delete/:id',
			function()
			{
				return 'delete';
			}
		);

		$Route = Routing\Router::getInstance()->getRoute(
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
		Routing\Route::post( '/post', function(){ return 'post'; } );

		$Route = Routing\Router::getInstance()->getRoute(
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
		Routing\Route::put( '/put', function(){ return 'put'; } );

		$Route = Routing\Router::getInstance()->getRoute(
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

	public function testMissingMethodType()
	{
		Routing\Route::get(
			'/get/:id',
			function(){ return 'get'; }
		)->setName( 'test.get' );

		$this->expectException( \Exception::class );

		$Route = Routing\Router::getInstance()->run(
			[
				'route' => '/get/1'
			]
		);
	}

	public function testGet()
	{
		Routing\Route::get(
			'/get/:id',
			function(){ return 'get'; }
			)
			->setName( 'test.get' );

		$Route = Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::GET,
			'/get/1/'
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

		$Route = Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::GET,
			'/get/1/2'
		);

		$Route = Routing\Router::getInstance()->getRoute(
			Routing\RequestMethod::GET,
			'/monkey/1/2'
		);

	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testDispatch()
	{
		Routing\Route::get( '/', function(){} );

		try
		{
			Routing\Route::dispatch(
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
