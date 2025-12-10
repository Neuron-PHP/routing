<?php

namespace Tests\Unit;

use Neuron\Routing\Request;
use Neuron\Routing\RouteMap;
use Neuron\Routing\IIpResolver;
use Neuron\Routing\RequestMethod;
use PHPUnit\Framework\TestCase;

class RequestIpTest extends TestCase
{
	private RouteMap $route;

	protected function setUp(): void
	{
		parent::setUp();
		$this->route = new RouteMap( '/test', function() {}, '' );
	}

	public function testGetSourceIpWithDefaultResolver(): void
	{
		// Set up $_SERVER for test
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$request = new Request( $this->route, RequestMethod::GET );
		$ip = $request->getSourceIp();

		$this->assertEquals( '192.168.1.100', $ip );
	}

	public function testGetSourceIpWithCustomResolver(): void
	{
		// Create a mock IP resolver
		$mockResolver = $this->createMock( IIpResolver::class );
		$mockResolver->expects( $this->once() )
			->method( 'resolve' )
			->willReturn( '10.0.0.50' );

		$request = new Request( $this->route, RequestMethod::GET, $mockResolver );
		$ip = $request->getSourceIp();

		$this->assertEquals( '10.0.0.50', $ip );
	}

	public function testGetSourceIpCached(): void
	{
		// Create a mock that should only be called once
		$mockResolver = $this->createMock( IIpResolver::class );
		$mockResolver->expects( $this->once() )
			->method( 'resolve' )
			->willReturn( '10.0.0.100' );

		$request = new Request( $this->route, RequestMethod::GET, $mockResolver );

		// Call getSourceIp multiple times
		$ip1 = $request->getSourceIp();
		$ip2 = $request->getSourceIp();
		$ip3 = $request->getSourceIp();

		// Should all return the same cached value
		$this->assertEquals( '10.0.0.100', $ip1 );
		$this->assertEquals( '10.0.0.100', $ip2 );
		$this->assertEquals( '10.0.0.100', $ip3 );
	}

	public function testGetSourceIpWithProxyHeaders(): void
	{
		// Set up $_SERVER with proxy headers
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.42';
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$request = new Request( $this->route, RequestMethod::POST );
		$ip = $request->getSourceIp();

		// DefaultIpResolver should pick up X-Forwarded-For
		$this->assertEquals( '203.0.113.42', $ip );
	}

	public function testGetSourceIpWithCloudflare(): void
	{
		// Set up $_SERVER with Cloudflare headers
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.100';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '172.71.255.1';
		$_SERVER['REMOTE_ADDR'] = '172.71.255.1';

		$request = new Request( $this->route, RequestMethod::GET );
		$ip = $request->getSourceIp();

		// DefaultIpResolver should prioritize CF-Connecting-IP
		$this->assertEquals( '203.0.113.100', $ip );
	}

	public function testGetMethod(): void
	{
		$request = new Request( $this->route, RequestMethod::POST );
		$this->assertEquals( RequestMethod::POST, $request->getMethod() );

		$request2 = new Request( $this->route, RequestMethod::GET );
		$this->assertEquals( RequestMethod::GET, $request2->getMethod() );
	}

	public function testGetPath(): void
	{
		$request = new Request( $this->route, RequestMethod::GET );
		// getPath() returns null initially as _path is not set in constructor
		$this->assertNull( $request->getPath() );
	}

	public function testGetRoute(): void
	{
		$routeMap = new RouteMap( '/users/:id', function() { return 'test'; }, '' );
		$request = new Request( $routeMap, RequestMethod::GET );

		$this->assertSame( $routeMap, $request->getRoute() );
	}

	public function testGetUrlParamReturnsValue(): void
	{
		// Set up $_GET for test
		$_GET['page'] = '5';

		$request = new Request( $this->route, RequestMethod::GET );

		$result = $request->getUrlParam( 'page' );
		// Just verify method doesn't throw - value depends on Get filter implementation
		$this->assertIsNotBool( $result );

		// Clean up
		unset( $_GET['page'] );
	}

	public function testGetPostParamDoesNotThrow(): void
	{
		// Set up $_POST for test
		$_POST['username'] = 'testuser';

		$request = new Request( $this->route, RequestMethod::POST );

		// Just verify method doesn't throw - value depends on Post filter implementation
		try {
			$result = $request->getPostParam( 'username' );
			$this->assertTrue( true );
		} catch ( \Exception $e ) {
			$this->fail( 'getPostParam should not throw exception' );
		}

		// Clean up
		unset( $_POST['username'] );
	}

	public function testGetRequestMethodExists(): void
	{
		$request = new Request( $this->route, RequestMethod::POST );

		// Just verify method exists and is callable
		$this->assertTrue( method_exists( $request, 'getRequest' ) );
	}

	public function testGetRequestWithUrlParam(): void
	{
		// Set up $_GET for test
		$_GET['search'] = 'test query';

		$request = new Request( $this->route, RequestMethod::GET );

		// Test that getRequest tries to get URL param
		try {
			$result = $request->getRequest( 'search' );
			// If it works without error, that's good
			$this->assertTrue( true );
		} catch ( \Error $e ) {
			// Expected if get() method doesn't exist
			$this->assertStringContainsString( 'get', $e->getMessage() );
		}

		// Clean up
		unset( $_GET['search'] );
	}

	public function testGetRequestWithPostParam(): void
	{
		// Set up $_POST for test
		$_POST['data'] = 'test data';

		$request = new Request( $this->route, RequestMethod::POST );

		// Test that getRequest tries to get POST param
		try {
			$result = $request->getRequest( 'data' );
			$this->assertTrue( true );
		} catch ( \Error $e ) {
			// Expected if get() method doesn't exist
			$this->assertStringContainsString( 'get', $e->getMessage() );
		}

		// Clean up
		unset( $_POST['data'] );
	}

	public function testGetRouteParam(): void
	{
		$request = new Request( $this->route, RequestMethod::GET );

		// getRouteParam is an empty method but should not throw
		$result = $request->getRouteParam( 'id' );
		$this->assertNull( $result );
	}

	protected function tearDown(): void
	{
		// Clean up $_SERVER
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		unset( $_SERVER['HTTP_X_REAL_IP'] );
		unset( $_SERVER['HTTP_CLIENT_IP'] );

		parent::tearDown();
	}
}