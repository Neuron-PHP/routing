<?php

use PHPUnit\Framework\TestCase;
use Neuron\Routing;

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
			$Route = new Routing\RouteMap( 'method', function() { return 'test';} );

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

	public function testGetPath()
	{
		$route = new Routing\RouteMap( '/users/:id', function() { return 'test'; } );
		$this->assertEquals( '/users/:id', $route->getPath() );
	}

	public function testSetPath()
	{
		$route = new Routing\RouteMap( '/users', function() {} );
		$result = $route->setPath( '/posts/:id' );

		$this->assertSame( $route, $result ); // Fluent interface
		$this->assertEquals( '/posts/:id', $route->Path );
	}

	public function testGetFunction()
	{
		$func = function() { return 'test'; };
		$route = new Routing\RouteMap( '/test', $func );

		$this->assertSame( $func, $route->getFunction() );
	}

	public function testSetFunction()
	{
		$route = new Routing\RouteMap( '/test', function() { return 'old'; } );
		$newFunc = function() { return 'new'; };

		$result = $route->setFunction( $newFunc );

		$this->assertSame( $route, $result );
		$this->assertSame( $newFunc, $route->Function );
	}

	public function testGetParameters()
	{
		$route = new Routing\RouteMap( '/test', function() {} );
		$this->assertEquals( [], $route->getParameters() );
	}

	public function testSetParameters()
	{
		$route = new Routing\RouteMap( '/test', function() {} );
		$params = [ 'id' => 123, 'slug' => 'test-post' ];

		$result = $route->setParameters( $params );

		$this->assertSame( $route, $result );
		$this->assertEquals( $params, $route->Parameters );
	}

	public function testGetFilter()
	{
		$route = new Routing\RouteMap( '/test', function() {}, 'auth' );
		$this->assertEquals( 'auth', $route->getFilter() );
	}

	public function testSetFilter()
	{
		$route = new Routing\RouteMap( '/test', function() {} );
		$result = $route->setFilter( 'rate-limit' );

		$this->assertSame( $route, $result );
		$this->assertEquals( 'rate-limit', $route->Filter );
	}

	public function testGetName()
	{
		$route = new Routing\RouteMap( '/test', function() {} );
		$route->Name = 'test.route';

		$this->assertEquals( 'test.route', $route->getName() );
	}

	public function testSetName()
	{
		$route = new Routing\RouteMap( '/test', function() {} );
		$result = $route->setName( 'user.profile' );

		$this->assertSame( $route, $result );
		$this->assertEquals( 'user.profile', $route->Name );
	}

	public function testParseParamsWithWildcard()
	{
		$route = new Routing\RouteMap( '/docs/*path', function() {} );
		$params = $route->parseParams();

		$this->assertCount( 2, $params );
		$this->assertEquals( 'docs', $params[0]['action'] );
		$this->assertEquals( 'path', $params[1]['param'] );
		$this->assertTrue( $params[1]['wildcard'] );
	}

	public function testParseParamsWithMultipleParams()
	{
		$route = new Routing\RouteMap( '/users/:userId/posts/:postId', function() {} );
		$params = $route->parseParams();

		$this->assertCount( 4, $params );
		$this->assertEquals( 'users', $params[0]['action'] );
		$this->assertEquals( 'userId', $params[1]['param'] );
		$this->assertEquals( 'posts', $params[2]['action'] );
		$this->assertEquals( 'postId', $params[3]['param'] );
	}

	public function testCheckForDuplicateParams()
	{
		$this->expectException( Neuron\Core\Exceptions\RouteParam::class );
		$this->expectExceptionMessage( "Duplicate parameter 'id' found" );

		$route = new Routing\RouteMap( '/users/:id/posts/:id', function() {} );
		$route->parseParams(); // Should throw exception
	}

	public function testExecuteWithFilter()
	{
		$preCalled = false;
		$postCalled = false;
		$functionCalled = false;

		$filter = new Routing\Filter(
			function() use ( &$preCalled ) { $preCalled = true; },
			function() use ( &$postCalled ) { $postCalled = true; }
		);

		$router = new Routing\Router();
		$router->registerFilter( 'test', $filter );

		$route = new Routing\RouteMap(
			'/test',
			function() use ( &$functionCalled ) {
				$functionCalled = true;
				return 'executed';
			},
			'test'
		);

		$result = $route->execute( $router );

		$this->assertTrue( $preCalled );
		$this->assertTrue( $functionCalled );
		$this->assertTrue( $postCalled );
		$this->assertEquals( 'executed', $result );
	}

	public function testExecuteWithoutFilter()
	{
		$router = new Routing\Router();

		$route = new Routing\RouteMap(
			'/test',
			function() { return 'no-filter'; }
		);

		$result = $route->execute( $router );
		$this->assertEquals( 'no-filter', $result );
	}

	public function testPayloadInitialization()
	{
		$route = new Routing\RouteMap( '/test', function() {} );

		// Payload should be initialized as empty array
		$this->assertIsArray( $route->Payload );
		$this->assertEmpty( $route->Payload );
	}

	public function testPayloadCanBeSet()
	{
		$route = new Routing\RouteMap( '/test', function() {} );

		$route->Payload = ['key' => 'value', 'number' => 42];

		$this->assertArrayHasKey( 'key', $route->Payload );
		$this->assertEquals( 'value', $route->Payload['key'] );
		$this->assertEquals( 42, $route->Payload['number'] );
	}

	public function testNameInitialization()
	{
		$route = new Routing\RouteMap( '/test', function() {} );

		// Name should be initialized as empty string
		$this->assertEquals( '', $route->Name );
	}

	public function testFilterInitialization()
	{
		$route = new Routing\RouteMap( '/test', function() {} );

		// Filter should be empty string when not provided
		$this->assertEquals( '', $route->Filter );
	}

	public function testFilterInitializationWithValue()
	{
		$route = new Routing\RouteMap( '/test', function() {}, 'auth' );

		// Filter should be set to provided value
		$this->assertEquals( 'auth', $route->Filter );
	}
}
