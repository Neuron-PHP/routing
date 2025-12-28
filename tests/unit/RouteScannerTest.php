<?php

use PHPUnit\Framework\TestCase;
use Neuron\Routing\RouteScanner;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

// Test controller classes
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class TestAdminController
{
	#[Get('/users')]
	public function index() {}

	#[Post('/users', filters: ['csrf'])]
	public function store() {}

	#[Get('/users/:id', name: 'admin.users.show')]
	public function show() {}
}

class TestSimpleController
{
	#[Get('/home')]
	public function home() {}

	#[Post('/contact', filters: ['csrf'])]
	public function contact() {}
}

class TestMultiRouteController
{
	#[Get('/api/v1/users')]
	#[Get('/api/v2/users')]
	public function users() {}
}

class RouteScannerTest extends TestCase
{
	private RouteScanner $scanner;

	protected function setUp(): void
	{
		$this->scanner = new RouteScanner();
	}

	public function testScanClassWithRouteGroup()
	{
		$routes = $this->scanner->scanClass( TestAdminController::class );

		$this->assertCount( 3, $routes );

		// Test first route
		$this->assertEquals( '/admin/users', $routes[0]->path );
		$this->assertEquals( 'GET', $routes[0]->method );
		$this->assertEquals( TestAdminController::class, $routes[0]->controller );
		$this->assertEquals( 'index', $routes[0]->action );
		$this->assertEquals( ['auth'], $routes[0]->filters );

		// Test second route - filters should merge
		$this->assertEquals( '/admin/users', $routes[1]->path );
		$this->assertEquals( 'POST', $routes[1]->method );
		$this->assertEquals( ['auth', 'csrf'], $routes[1]->filters );

		// Test third route - with name
		$this->assertEquals( '/admin/users/:id', $routes[2]->path );
		$this->assertEquals( 'admin.users.show', $routes[2]->name );
	}

	public function testScanClassWithoutRouteGroup()
	{
		$routes = $this->scanner->scanClass( TestSimpleController::class );

		$this->assertCount( 2, $routes );

		$this->assertEquals( '/home', $routes[0]->path );
		$this->assertEquals( 'GET', $routes[0]->method );
		$this->assertEquals( [], $routes[0]->filters );

		$this->assertEquals( '/contact', $routes[1]->path );
		$this->assertEquals( 'POST', $routes[1]->method );
		$this->assertEquals( ['csrf'], $routes[1]->filters );
	}

	public function testScanClassWithMultipleRoutesOnSameMethod()
	{
		$routes = $this->scanner->scanClass( TestMultiRouteController::class );

		$this->assertCount( 2, $routes );

		$this->assertEquals( '/api/v1/users', $routes[0]->path );
		$this->assertEquals( '/api/v2/users', $routes[1]->path );
		$this->assertEquals( 'users', $routes[0]->action );
		$this->assertEquals( 'users', $routes[1]->action );
	}

	public function testScanNonExistentClass()
	{
		$routes = $this->scanner->scanClass( 'NonExistentClass' );
		$this->assertEmpty( $routes );
	}

	public function testScanClassesCachesResults()
	{
		// First scan
		$routes1 = $this->scanner->scanClass( TestSimpleController::class );

		// Second scan should return cached results
		$routes2 = $this->scanner->scanClass( TestSimpleController::class );

		$this->assertSame( $routes1, $routes2 );
	}

	public function testClearCache()
	{
		$this->scanner->scanClass( TestSimpleController::class );
		$this->scanner->clearCache();

		// After clearing cache, scanning again should create new instances
		$routes = $this->scanner->scanClass( TestSimpleController::class );
		$this->assertCount( 2, $routes );
	}

	public function testGetControllerMethod()
	{
		$routes = $this->scanner->scanClass( TestSimpleController::class );
		$this->assertEquals( TestSimpleController::class . '@home', $routes[0]->getControllerMethod() );
	}

	public function testScanMultipleClasses()
	{
		$routes = $this->scanner->scanClasses([
			TestAdminController::class,
			TestSimpleController::class
		]);

		$this->assertCount( 5, $routes ); // 3 from admin + 2 from simple
	}
}
