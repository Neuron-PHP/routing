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