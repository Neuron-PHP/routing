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

class TestRestfulController
{
	#[Get('/posts')]
	public function index() {}

	#[Get('/posts/:id')]
	public function show() {}

	#[Post('/posts')]
	public function store() {}

	#[Put('/posts/:id', filters: ['auth'])]
	public function update() {}

	#[Delete('/posts/:id', name: 'posts.delete', filters: ['auth', 'csrf'])]
	public function destroy() {}
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

	public function testScanRestfulController()
	{
		$routes = $this->scanner->scanClass( TestRestfulController::class );

		$this->assertCount( 5, $routes );

		// Test GET routes
		$this->assertEquals( '/posts', $routes[0]->path );
		$this->assertEquals( 'GET', $routes[0]->method );

		$this->assertEquals( '/posts/:id', $routes[1]->path );
		$this->assertEquals( 'GET', $routes[1]->method );

		// Test POST route
		$this->assertEquals( '/posts', $routes[2]->path );
		$this->assertEquals( 'POST', $routes[2]->method );

		// Test PUT route
		$this->assertEquals( '/posts/:id', $routes[3]->path );
		$this->assertEquals( 'PUT', $routes[3]->method );
		$this->assertEquals( ['auth'], $routes[3]->filters );

		// Test DELETE route
		$this->assertEquals( '/posts/:id', $routes[4]->path );
		$this->assertEquals( 'DELETE', $routes[4]->method );
		$this->assertEquals( 'posts.delete', $routes[4]->name );
		$this->assertEquals( ['auth', 'csrf'], $routes[4]->filters );
	}

	public function testRouteGroupApplyPrefix()
	{
		$group = new RouteGroup( prefix: '/admin' );

		$this->assertEquals( '/admin/users', $group->applyPrefix( '/users' ) );
		$this->assertEquals( '/admin/users', $group->applyPrefix( 'users' ) );
	}

	public function testRouteGroupApplyPrefixWithoutPrefix()
	{
		$group = new RouteGroup();

		$this->assertEquals( '/users', $group->applyPrefix( '/users' ) );
	}

	public function testRouteGroupMergeFilters()
	{
		$group = new RouteGroup( prefix: '/admin', filters: ['auth', 'admin'] );

		$merged = $group->mergeFilters( ['csrf'] );

		$this->assertEquals( ['auth', 'admin', 'csrf'], $merged );
	}

	public function testRouteGroupGetters()
	{
		$group = new RouteGroup( prefix: '/api', filters: ['api-key'] );

		$this->assertEquals( '/api', $group->getPrefix() );
		$this->assertEquals( ['api-key'], $group->getFilters() );
	}

	public function testScanNonExistentDirectory()
	{
		$routes = $this->scanner->scanDirectory( '/nonexistent/path', 'App\\Controllers' );
		$this->assertEmpty( $routes );
	}

	public function testScanDirectoryWithControllers()
	{
		// Create a temporary directory with a test controller
		$tempDir = sys_get_temp_dir() . '/neuron_scanner_test_' . uniqid();
		mkdir( $tempDir, 0777, true );

		// Create a test controller file
		$controllerCode = <<<'PHP'
<?php
namespace TempTest;

use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

class TempController
{
	#[Get('/temp/index')]
	public function index() {}

	#[Post('/temp/store')]
	public function store() {}
}
PHP;

		file_put_contents( $tempDir . '/TempController.php', $controllerCode );

		// Load the class
		require_once $tempDir . '/TempController.php';

		// Scan the directory
		$routes = $this->scanner->scanDirectory( $tempDir, 'TempTest' );

		$this->assertCount( 2, $routes );
		$this->assertEquals( '/temp/index', $routes[0]->path );
		$this->assertEquals( 'GET', $routes[0]->method );
		$this->assertEquals( '/temp/store', $routes[1]->path );
		$this->assertEquals( 'POST', $routes[1]->method );

		// Clean up
		unlink( $tempDir . '/TempController.php' );
		rmdir( $tempDir );
	}

	public function testScanDirectoryWithNonControllerFiles()
	{
		// Create a temporary directory with non-controller PHP files
		$tempDir = sys_get_temp_dir() . '/neuron_scanner_noncontroller_' . uniqid();
		mkdir( $tempDir, 0777, true );

		// Create a PHP file without a class
		file_put_contents( $tempDir . '/config.php', '<?php return ["key" => "value"];' );

		// Create a non-PHP file
		file_put_contents( $tempDir . '/readme.txt', 'This is a readme file' );

		// Scan the directory - should return empty array
		$routes = $this->scanner->scanDirectory( $tempDir, 'TempTest' );

		$this->assertEmpty( $routes );

		// Clean up
		unlink( $tempDir . '/config.php' );
		unlink( $tempDir . '/readme.txt' );
		rmdir( $tempDir );
	}

	public function testScanDirectoryWithNestedStructure()
	{
		// Create a temporary directory with nested controllers
		$tempDir = sys_get_temp_dir() . '/neuron_scanner_nested_' . uniqid();
		mkdir( $tempDir . '/Admin', 0777, true );

		// Create a controller in a subdirectory
		$controllerCode = <<<'PHP'
<?php
namespace TempNested\Admin;

use Neuron\Routing\Attributes\Get;

class AdminController
{
	#[Get('/admin/dashboard')]
	public function dashboard() {}
}
PHP;

		file_put_contents( $tempDir . '/Admin/AdminController.php', $controllerCode );

		// Load the class
		require_once $tempDir . '/Admin/AdminController.php';

		// Scan the directory
		$routes = $this->scanner->scanDirectory( $tempDir, 'TempNested' );

		$this->assertCount( 1, $routes );
		$this->assertEquals( '/admin/dashboard', $routes[0]->path );
		$this->assertEquals( 'TempNested\Admin\AdminController', $routes[0]->controller );

		// Clean up
		unlink( $tempDir . '/Admin/AdminController.php' );
		rmdir( $tempDir . '/Admin' );
		rmdir( $tempDir );
	}

	public function testFindClassesInDirectorySkipsNonExistentClasses()
	{
		// Create a temporary directory with a PHP file that doesn't define the expected class
		$tempDir = sys_get_temp_dir() . '/neuron_scanner_invalid_' . uniqid();
		mkdir( $tempDir, 0777, true );

		// Create a PHP file with a class that doesn't match the namespace pattern
		$invalidCode = <<<'PHP'
<?php
namespace WrongNamespace;

class WrongClass {}
PHP;

		file_put_contents( $tempDir . '/TestFile.php', $invalidCode );

		// Load the file
		require_once $tempDir . '/TestFile.php';

		// Scan - should return empty because the class namespace doesn't match
		$routes = $this->scanner->scanDirectory( $tempDir, 'ExpectedNamespace' );

		$this->assertEmpty( $routes );

		// Clean up
		unlink( $tempDir . '/TestFile.php' );
		rmdir( $tempDir );
	}

	public function testScanClassWithNoRouteAttributes()
	{
		// Create a class with no route attributes
		eval('
			class NoRoutesController {
				public function index() {}
			}
		');

		$routes = $this->scanner->scanClass( 'NoRoutesController' );
		$this->assertEmpty( $routes );
	}

	public function testRouteGroupWithEmptyPrefix()
	{
		$group = new RouteGroup( prefix: '', filters: [] );

		$path = $group->applyPrefix( '/users' );
		$this->assertEquals( '/users', $path );
	}

	public function testRouteGroupPrefixNormalization()
	{
		// Test that prefixes are normalized correctly
		$group = new RouteGroup( prefix: 'admin/' ); // Has trailing slash

		$this->assertEquals( '/admin/users', $group->applyPrefix( '/users' ) );
		$this->assertEquals( '/admin/users', $group->applyPrefix( 'users' ) );
	}
}
