<?php

use PHPUnit\Framework\TestCase;
use Neuron\Routing;

class RouterTest extends PHPUnit\Framework\TestCase
{
	public $Router;

	protected function setUp() : void
	{
		$this->Router = new Routing\Router();
	}

	public function testDelete()
	{
		$this->Router->delete(
			'/delete/:id',
			function()
			{
				return 'delete';
			}
		);

		$Route = $this->Router->getRoute(
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

	public function testDuplicateParamNames()
	{
		$Caught = false;

		$this->Router->get( '/:test/:test',
			function( $parameters )
			{
			}
		);

		try
		{
			$test = $this->Router->run(
				[
					'route' => '/test/test',
					'type'  => 'GET'
				]
			);
		}
		catch( Routing\RouteParamException $exception )
		{
			$Caught = true;
		}

		$this->assertTrue( $Caught );
	}

	public function testGet()
	{
		$this->Router->get( '/get/:id', function(){ return 'get'; } );

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/get/1'
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/get/:id'
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/get/1/2'
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/monkey/1/2'
		);
	}

	public function testGetMultipleParameters()
	{

		$this->Router->get( '/story/:id/set_state/:state_id',
			function( $parameters )
			{
				return $parameters[ 'id' ].':'.$parameters[ 'state_id' ];
			}
		);

		$this->Router->get( '/story/:id',
			function( $parameters )
			{
				return $parameters[ 'id' ];
			}
		);

		$this->Router->get( '/:controller/:action',
			function( $parameters )
			{
				return $parameters[ 'controller' ].':'.$parameters[ 'action' ];
			}
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/test/run'
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/:controller/:action'
		);

		$test = $this->Router->run(
			[
				'route' => '/test/run',
				'type'  => 'GET'
			]
		);

		$this->assertEquals(
			'test:run',
			$test
		);

		$test = $this->Router->run(
			[
				'route' => '/story/3/set_state/4',
				'type'  => 'GET'
			]
		);

		$this->assertEquals(
			'3:4',
			$test
		);

		$test = $this->Router->run(
			[
				'route' => '/story/3',
				'type'  => 'GET'
			]
		);

		$this->assertEquals(
			'3',
			$test
		);

	}

	public function testPost()
	{
		$this->Router->post( '/post', function(){ return 'post'; } );

		$Route = $this->Router->getRoute(
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
		$this->Router->put( '/put', function(){ return 'put'; } );

		$Route = $this->Router->getRoute(
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

	/**
	 * @doesNotPerformAssertions
	 */
	public function testDispatch()
	{
		$this->Router->delete(
			'/delete/:id',
			function( $parameters )
			{
			}
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::DELETE,
			'/delete/1'
		);

		$this->Router->dispatch( $Route );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testRunSuccess()
	{
		$this->Router->get( '/', function(){} );

		try
		{
			$this->Router->run(
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

	/**
	 * @doesNotPerformAssertions
	 */
	public function testRunMissingRoute()
	{
		$this->Router->get( '/', function(){} );

		try
		{
			$this->Router->run();
			$this->fail( "Should have failed due to missing route." );
		}
		catch( Exception $exception )
		{
		}
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testRun404Fail()
	{
		$this->Router->get( '/', function(){} );

		try
		{
			$this->Router->run(
				[
					'route' => '/foo',
					'type'  => 'GET'
				]
			);

			$this->fail( 'Should fail processing route.' );
		}
		catch( Exception $exception )
		{
		}
	}

	public function testRun404Success()
	{
		$this->Router->get( '/',    function(){} );
		$this->Router->get( '/404', function(){} );

		try
		{
			$this->Router->run(
				[
					'route' => '/foo',
					'type'  => 'GET'
				]
			);

			$this->fail( 'Should fail processing route.' );
		}
		catch( Exception $exception )
		{
		}
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testEmptyRoute()
	{
		$this->Router->get( '/',    function(){} );

		$this->Router->run(
			[
				'route' => '',
				'type'  => 'GET'
			]
		);
	}

	public function testStaticComesFirst()
	{
		$this->Router->get( '/story/:id',
			function( $parameters )
			{
				return $parameters[ 'id' ];
			}
		);

		$this->Router->get( '/story/static',
			function( $parameters )
			{
				return 'static';
			}
		);

		$Result = $this->Router->run(
			[
				'route' => '/story/static',
				'type'  => 'GET'
			]
		);

		$this->assertEquals(
			'static',
			$Result
		);
	}

	public function testStaticComesFirst2()
	{
		$this->Router->get( '/story/static',
			function( $parameters )
			{
				return 'static';
			}
		);

		$this->Router->get( '/story/:id',
			function( $parameters )
			{
				return $parameters[ 'id' ];
			}
		);

		$Result = $this->Router->run(
			[
				'route' => '/story/static',
				'type'  => 'GET'
			]
		);

		$this->assertEquals(
			'static',
			$Result
		);
	}

	public function testExtraParams()
	{
		$Extra = '';
		$this->Router->get( '/', function( $Parameters ){
			return $Parameters[ 'test' ];
		} );

		try
		{
			$Extra = $this->Router->run(
				[
					'route' => '/',
					'type'  => 'GET',
					'extra' =>
						[
							'test' => '1234'
						]
				]
			);

		}
		catch( Exception $exception )
		{
			$this->fail( $exception->getMessage() );
		}

		$this->assertEquals( '1234', $Extra );
	}

	public function testPayload()
	{
		try
		{
			$Route = $this->Router->get( "/test", function( $Parameters ){ return $Parameters[ 'Controller' ]; } );
			$Route->Payload = [ 'Controller' => 'Controller@method' ];
		}
		catch( Exception $Exception )
		{}

		try
		{
			$Payload = $this->Router->run(
				[
					'route' => '/test',
					'type'  => 'GET'
				]
			);

		}
		catch( Exception $exception )
		{
			$this->fail( $exception->getMessage() );
		}

		$this->assertEquals( 'Controller@method', $Payload );
	}
}
