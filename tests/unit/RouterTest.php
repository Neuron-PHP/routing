<?php

class RouterTest extends PHPUnit_Framework_TestCase
{
	public $Router;

	public function setup()
	{
		$this->Router = new \Notion\Router();
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
			Notion\RequestMethod::DELETE,
			'delete/1'
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
					'route' => 'test/test',
					'type'  => 'GET'
				]
			);
		}
		catch( \Notion\RouteParamException $exception )
		{
			$Caught = true;
		}

		$this->assertTrue( $Caught );
	}

	public function testGet()
	{
		$this->Router->get( '/get/:id', function(){ return 'get'; } );

		$Route = $this->Router->getRoute(
			Notion\RequestMethod::GET,
			'get/1'
		);

		$this->assertNotNull(
			$Route
		);

		$this->assertEquals(
			$Route->Path,
			'/get/:id'
		);

		$Route = $this->Router->getRoute(
			Notion\RequestMethod::GET,
			'get/1/2'
		);

		$Route = $this->Router->getRoute(
			Notion\RequestMethod::GET,
			'monkey/1/2'
		);
	}

	public function testGetMultipleParameters()
	{
		$this->Router->get( '/:controller/:action',
			function( $parameters )
			{
				return $parameters[ 'controller' ].':'.$parameters[ 'action' ];
			}
		);

		$Route = $this->Router->getRoute(
			Notion\RequestMethod::GET,
			'test/run'
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
				'route' => 'test/run',
				'type'  => 'GET'
			]
		);

		$this->assertEquals(
			'test:run',
			$test
		);
	}

	public function testPost()
	{
		$this->Router->post( '/post', function(){ return 'post'; } );

		$Route = $this->Router->getRoute(
			Notion\RequestMethod::POST,
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
			Notion\RequestMethod::PUT,
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

	public function testDispatch()
	{
		$this->Router->delete(
			'/delete/:id',
			function( $parameters )
			{
			}
		);

		$Route = $this->Router->getRoute(
			Notion\RequestMethod::DELETE,
			'delete/1'
		);

		$this->Router->dispatch( $Route );
	}

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

	public function testRun404Fail()
	{
		$this->Router->get( '/', function(){} );

		try
		{
			$this->Router->run(
				[
					'route' => 'foo',
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
					'route' => 'foo',
					'type'  => 'GET'
				]
			);

			$this->fail( 'Should fail processing route.' );
		}
		catch( Exception $exception )
		{
		}
	}

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
}
