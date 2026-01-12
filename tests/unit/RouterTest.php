<?php

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
		catch( \Neuron\Core\Exceptions\RouteParam $exception )
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

	public function testWildcardRoute()
	{
		$this->Router->get( '/md/*page',
			function( $parameters )
			{
				return $parameters[ 'page' ];
			}
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/md/authentication'
		);

		$this->assertNotNull( $Route );
		$this->assertEquals( '/md/*page', $Route->Path );

		$test = $this->Router->run(
			[
				'route' => '/md/authentication',
				'type'  => 'GET'
			]
		);

		$this->assertEquals( 'authentication', $test );
	}

	public function testWildcardRouteMultipleSegments()
	{
		$this->Router->get( '/md/*page',
			function( $parameters )
			{
				return $parameters[ 'page' ];
			}
		);

		$test = $this->Router->run(
			[
				'route' => '/md/cms/guides/authentication',
				'type'  => 'GET'
			]
		);

		$this->assertEquals( 'cms/guides/authentication', $test );
	}

	public function testWildcardRouteSingleSegment()
	{
		$this->Router->get( '/md/*page',
			function( $parameters )
			{
				return $parameters[ 'page' ];
			}
		);

		$test = $this->Router->run(
			[
				'route' => '/md/index',
				'type'  => 'GET'
			]
		);

		$this->assertEquals( 'index', $test );
	}

	public function testWildcardWithStaticPrefix()
	{
		$this->Router->get( '/api/v1/*endpoint',
			function( $parameters )
			{
				return 'v1:' . $parameters[ 'endpoint' ];
			}
		);

		$test = $this->Router->run(
			[
				'route' => '/api/v1/users/123/posts',
				'type'  => 'GET'
			]
		);

		$this->assertEquals( 'v1:users/123/posts', $test );
	}

	public function testWildcardRouteDeepPath()
	{
		$this->Router->get( '/docs/*path',
			function( $parameters )
			{
				return $parameters[ 'path' ];
			}
		);

		$test = $this->Router->run(
			[
				'route' => '/docs/cms/reference/events/cache-hit',
				'type'  => 'GET'
			]
		);

		$this->assertEquals( 'cms/reference/events/cache-hit', $test );
	}

	public function testSetIpResolver()
	{
		$resolver = new Routing\DefaultIpResolver();
		$this->Router->setIpResolver( $resolver );

		// Test that IP resolver is used by making a request
		$this->Router->get( '/test', function(){ return 'ok'; } );

		$result = $this->Router->run([
			'route' => '/test',
			'type' => 'GET'
		]);

		$this->assertEquals( 'ok', $result );
	}

	public function testRegisterAndGetFilter()
	{
		$filter = new Routing\Filter( function() { return true; }, null );
		$this->Router->registerFilter( 'test-filter', $filter );

		$retrieved = $this->Router->getFilter( 'test-filter' );
		$this->assertSame( $filter, $retrieved );
	}

	public function testGetFilterNotFound()
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Filter nonexistent not registered' );

		$this->Router->getFilter( 'nonexistent' );
	}

	public function testAddFilter()
	{
		// addFilter adds to the router's filter array
		$this->Router->addFilter( 'auth' );
		$this->Router->addFilter( 'rate-limit' );

		// Just verify it doesn't throw - internal array not accessible
		$this->assertTrue( true );
	}

	public function testGetRouteByName()
	{
		$route = $this->Router->get( '/users/:id', function() {} );
		$route->setName( 'user.show' );

		$found = $this->Router->getRouteByName( 'user.show' );

		$this->assertNotNull( $found );
		$this->assertSame( $route, $found );
	}

	public function testGetRouteByNameNotFound()
	{
		$route = $this->Router->getRouteByName( 'nonexistent' );
		$this->assertNull( $route );
	}

	public function testGetAllNamedRoutes()
	{
		$route1 = $this->Router->get( '/users', function() {} );
		$route1->setName( 'users.index' );

		$route2 = $this->Router->get( '/posts', function() {} );
		$route2->setName( 'posts.index' );

		// Add an unnamed route
		$this->Router->get( '/about', function() {} );

		$namedRoutes = $this->Router->getAllNamedRoutes();

		$this->assertCount( 2, $namedRoutes );

		// getAllNamedRoutes returns array of arrays with 'name', 'method', 'path'
		$names = array_column( $namedRoutes, 'name' );
		$this->assertContains( 'users.index', $names );
		$this->assertContains( 'posts.index', $names );

		// Verify structure
		$this->assertArrayHasKey( 'name', $namedRoutes[0] );
		$this->assertArrayHasKey( 'method', $namedRoutes[0] );
		$this->assertArrayHasKey( 'path', $namedRoutes[0] );
	}

	public function testGenerateUrl()
	{
		$route = $this->Router->get( '/users/:id', function() {} );
		$route->setName( 'user.show' );

		$url = $this->Router->generateUrl( 'user.show', ['id' => 123] );

		$this->assertEquals( '/users/123', $url );
	}

	public function testGenerateUrlNotFound()
	{
		$url = $this->Router->generateUrl( 'nonexistent', [] );
		$this->assertNull( $url );
	}

	public function testDuplicateRoutePath()
	{
		$this->expectException( Routing\Exceptions\DuplicateRouteException::class );

		// First route
		$this->Router->get( '/users/:id', function() { return 'first'; } );

		// Second route with same path and method
		$this->Router->get( '/users/:id', function() { return 'second'; } );
	}

	public function testDuplicateRouteNameViaFluentApi()
	{
		$this->expectException( Routing\Exceptions\DuplicateRouteException::class );
		$this->expectExceptionMessage( 'user.show' );

		// First route with name
		$this->Router->get( '/users/:id', function() { return 'first'; } )
			->setName( 'user.show' );

		// Second route with same name (via fluent API)
		$this->Router->get( '/users/:slug', function() { return 'second'; } )
			->setName( 'user.show' );
	}

	public function testDuplicateRouteNameViaParameter()
	{
		$this->expectException( Routing\Exceptions\DuplicateRouteException::class );
		$this->expectExceptionMessage( 'user.show' );

		// First route with name via parameter
		$this->Router->get( '/users/:id', function() { return 'first'; }, null, 'user.show' );

		// Second route with same name via parameter
		$this->Router->get( '/users/:slug', function() { return 'second'; }, null, 'user.show' );
	}

	public function testDuplicateRouteNameAcrossMethods()
	{
		$this->expectException( Routing\Exceptions\DuplicateRouteException::class );
		$this->expectExceptionMessage( 'user.action' );

		// First route: GET /users
		$this->Router->get( '/users', function() { return 'get'; } )
			->setName( 'user.action' );

		// Second route: POST /users with same name
		$this->Router->post( '/users', function() { return 'post'; } )
			->setName( 'user.action' );
	}

	public function testStrictModeDisabled()
	{
		// Disable strict mode
		$this->Router->setStrictMode( false );

		// These should NOT throw exceptions now
		$this->Router->get( '/users/:id', function() { return 'first'; } );
		$this->Router->get( '/users/:id', function() { return 'second'; } );

		$this->Router->get( '/posts/:id', function() { return 'first'; } )
			->setName( 'post.show' );
		$this->Router->get( '/posts/:slug', function() { return 'second'; } )
			->setName( 'post.show' );

		// Just verify no exception was thrown
		$this->assertTrue( true );
	}
}
