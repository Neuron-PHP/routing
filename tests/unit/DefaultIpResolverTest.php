<?php

namespace Tests\Unit;

use Neuron\Routing\DefaultIpResolver;
use PHPUnit\Framework\TestCase;

class DefaultIpResolverTest extends TestCase
{
	private DefaultIpResolver $resolver;

	protected function setUp(): void
	{
		parent::setUp();
		$this->resolver = new DefaultIpResolver();
	}

	public function testResolveDirectConnection(): void
	{
		$server = [
			'REMOTE_ADDR' => '192.168.1.100'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '192.168.1.100', $ip );
	}

	public function testResolveCloudflareHeader(): void
	{
		$server = [
			'HTTP_CF_CONNECTING_IP' => '203.0.113.42',
			'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
			'REMOTE_ADDR' => '172.71.255.1'  // Cloudflare IP
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '203.0.113.42', $ip );
	}

	public function testResolveXForwardedFor(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => '203.0.113.42',
			'REMOTE_ADDR' => '10.0.0.1'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '203.0.113.42', $ip );
	}

	public function testResolveXForwardedForWithProxyChain(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => '203.0.113.42, 10.0.0.1, 172.16.0.1',
			'REMOTE_ADDR' => '192.168.1.1'
		];

		$ip = $this->resolver->resolve( $server );

		// Should return the first IP (original client)
		$this->assertEquals( '203.0.113.42', $ip );
	}

	public function testResolveXRealIp(): void
	{
		$server = [
			'HTTP_X_REAL_IP' => '203.0.113.42',
			'REMOTE_ADDR' => '10.0.0.1'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '203.0.113.42', $ip );
	}

	public function testResolveClientIp(): void
	{
		$server = [
			'HTTP_CLIENT_IP' => '203.0.113.42',
			'REMOTE_ADDR' => '10.0.0.1'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '203.0.113.42', $ip );
	}

	public function testResolveInvalidIpFallsBackToRemoteAddr(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
			'HTTP_X_REAL_IP' => 'invalid',
			'REMOTE_ADDR' => '192.168.1.100'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '192.168.1.100', $ip );
	}

	public function testResolveMissingRemoteAddr(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => 'invalid'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '0.0.0.0', $ip );
	}

	public function testResolveEmptyServer(): void
	{
		$server = [];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '0.0.0.0', $ip );
	}

	public function testResolveHeaderPriority(): void
	{
		// All headers present - should use CF-Connecting-IP first
		$server = [
			'HTTP_CF_CONNECTING_IP' => '1.1.1.1',
			'HTTP_X_FORWARDED_FOR' => '2.2.2.2',
			'HTTP_X_REAL_IP' => '3.3.3.3',
			'HTTP_CLIENT_IP' => '4.4.4.4',
			'REMOTE_ADDR' => '5.5.5.5'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '1.1.1.1', $ip );
	}

	public function testResolveIpv6(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
			'REMOTE_ADDR' => '192.168.1.1'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '2001:0db8:85a3:0000:0000:8a2e:0370:7334', $ip );
	}

	public function testResolveIpv6Compressed(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => '2001:db8::8a2e:370:7334',
			'REMOTE_ADDR' => '192.168.1.1'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '2001:db8::8a2e:370:7334', $ip );
	}

	public function testResolveTrimmedIp(): void
	{
		$server = [
			'HTTP_X_FORWARDED_FOR' => '  203.0.113.42  ',
			'REMOTE_ADDR' => '192.168.1.1'
		];

		$ip = $this->resolver->resolve( $server );

		$this->assertEquals( '203.0.113.42', $ip );
	}
}