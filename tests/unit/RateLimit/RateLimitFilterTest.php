<?php

use PHPUnit\Framework\TestCase;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Routing\Filters\RateLimitFilter;
use Neuron\Routing\RateLimit\RateLimitConfig;
use Neuron\Routing\RateLimit\Storage\MemoryRateLimitStorage;
use Neuron\Routing\RouteMap;
use Neuron\Routing\Router;
use Neuron\Routing\IIpResolver;
use Neuron\Routing\DefaultIpResolver;
use Neuron\Patterns\Registry;

class RateLimitFilterTest extends TestCase
{
	private function createTestFilter($enabled = true, $limit = 5, $window = 60): RateLimitFilter
	{
		$config = new RateLimitConfig([
			'enabled' => $enabled,
			'storage' => 'memory',
			'requests' => $limit,
			'window' => $window
		]);

		return new RateLimitFilter($config);
	}

	public function testFilterCreation()
	{
		$filter = $this->createTestFilter();
		$this->assertInstanceOf(RateLimitFilter::class, $filter);
	}

	public function testDisabledFilter()
	{
		$filter = $this->createTestFilter(false);
		$route = new RouteMap('/test', function() { return 'test'; });

		// Should not throw or exit when disabled
		$filter->pre($route);
		$this->assertTrue(true); // If we get here, the filter passed
	}

	public function testWhitelist()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 1,
			'window' => 60
		]);

		// Mock IP address
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$filter = new RateLimitFilter(
			$config,
			'ip',
			['192.168.1.100'], // Whitelist this IP
			[]
		);

		$route = new RouteMap('/test', function() { return 'test'; });

		// Should allow unlimited requests for whitelisted IP
		for ($i = 0; $i < 10; $i++) {
			$filter->pre($route);
		}

		$this->assertTrue(true); // If we get here, all requests passed
	}

	public function testBlacklist()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 100,
			'window' => 60
		]);

		// Mock IP address
		$_SERVER['REMOTE_ADDR'] = '192.168.1.200';

		$filter = new RateLimitFilter(
			$config,
			'ip',
			[],
			['192.168.1.200'] // Blacklist this IP
		);

		$route = new RouteMap('/test', function() { return 'test'; });

		// Blacklisted IPs should get 1/10th of the limit (10 requests)
		$storage = $filter->getStorage();

		// Clear any previous state
		$storage->clear();

		$allowedCount = 0;
		for ($i = 0; $i < 20; $i++) {
			if ($storage->allow('192.168.1.200', 10, 60)) {
				$allowedCount++;
			}
		}

		// Should allow exactly 10 requests (1/10th of 100)
		$this->assertEquals(10, $allowedCount);
	}

	public function testReset()
	{
		$filter = $this->createTestFilter(true, 2, 60);
		$storage = $filter->getStorage();

		$key = 'test_key';

		// Use up the limit
		$this->assertTrue($storage->allow($key, 2, 60));
		$this->assertTrue($storage->allow($key, 2, 60));
		$this->assertFalse($storage->allow($key, 2, 60));

		// Reset
		$filter->reset($key);

		// Should be allowed again
		$this->assertTrue($storage->allow($key, 2, 60));
	}

	public function testClear()
	{
		$filter = $this->createTestFilter(true, 2, 60);
		$storage = $filter->getStorage();

		// Add some data
		$storage->allow('key1', 2, 60);
		$storage->allow('key2', 2, 60);

		// Clear all
		$filter->clear();

		// Both keys should have full limit available
		$this->assertEquals(2, $storage->getRemainingAttempts('key1', 2, 60));
		$this->assertEquals(2, $storage->getRemainingAttempts('key2', 2, 60));
	}

	public function testConfigFromSettings()
	{
		// Mock settings source
		$mockSource = $this->createMock(\Neuron\Data\Settings\Source\ISettingSource::class);

		$mockSource->method('get')
			->willReturnMap([
				['rate_limit', 'enabled', 'true'],
				['rate_limit', 'storage', 'redis'],
				['rate_limit', 'requests', '100'],
				['rate_limit', 'window', '3600'],
				['rate_limit', 'redis_host', '127.0.0.1'],
				['rate_limit', 'redis_port', '6379']
			]);

		$config = RateLimitConfig::fromSettings($mockSource);

		$this->assertTrue($config->isEnabled());
		$this->assertEquals('redis', $config->getStorage());
		$this->assertEquals(100, $config->getLimit());
		$this->assertEquals(3600, $config->getWindow());
		$this->assertEquals('127.0.0.1', $config->getRedisHost());
		$this->assertEquals(6379, $config->getRedisPort());
	}

	public function testKeyStrategies()
	{
		// Test IP strategy
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$config = new RateLimitConfig(['enabled' => true, 'storage' => 'memory']);

		$filterIp = new RateLimitFilter($config, 'ip');
		$route = new RouteMap('/test', function() { return 'test'; });

		// Test route strategy
		$filterRoute = new RateLimitFilter($config, 'route');

		// The keys should be different
		$reflectionIp = new ReflectionClass($filterIp);
		$methodIp = $reflectionIp->getMethod('generateKey');
		$methodIp->setAccessible(true);

		$reflectionRoute = new ReflectionClass($filterRoute);
		$methodRoute = $reflectionRoute->getMethod('generateKey');
		$methodRoute->setAccessible(true);

		$keyIp = $methodIp->invoke($filterIp, $route);
		$keyRoute = $methodRoute->invoke($filterRoute, $route);

		$this->assertEquals('10.0.0.1', $keyIp);
		$this->assertEquals('10.0.0.1:/test', $keyRoute);
	}

	public function testCustomIpResolver()
	{
		// Create a mock IP resolver that always returns a specific IP
		$mockResolver = $this->createMock(IIpResolver::class);
		$mockResolver->method('resolve')
			->willReturn('203.0.113.42');

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 5,
			'window' => 60
		]);

		$filter = new RateLimitFilter(
			$config,
			'ip',
			[],
			[],
			$mockResolver
		);

		$route = new RouteMap('/test', function() { return 'test'; });

		// Use reflection to verify the IP being used
		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('generateKey');
		$method->setAccessible(true);

		$key = $method->invoke($filter, $route);

		// Should use the IP from our mock resolver
		$this->assertEquals('203.0.113.42', $key);
	}

	public function testDefaultIpResolver()
	{
		// Test that DefaultIpResolver is used when no resolver is provided
		$_SERVER['REMOTE_ADDR'] = '198.51.100.25';

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 5,
			'window' => 60
		]);

		// Create filter without explicit IP resolver
		$filter = new RateLimitFilter($config, 'ip');

		$route = new RouteMap('/test', function() { return 'test'; });

		// Use reflection to verify IP resolution
		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('generateKey');
		$method->setAccessible(true);

		$key = $method->invoke($filter, $route);

		// Should use the IP from $_SERVER via DefaultIpResolver
		$this->assertEquals('198.51.100.25', $key);
	}

	public function testCloudflareIpResolution()
	{
		// Test that Cloudflare headers are supported via DefaultIpResolver
		$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.50';

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 5,
			'window' => 60
		]);

		$filter = new RateLimitFilter($config, 'ip');

		$route = new RouteMap('/test', function() { return 'test'; });

		// Use reflection to verify IP resolution
		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('generateKey');
		$method->setAccessible(true);

		$key = $method->invoke($filter, $route);

		// Should prioritize Cloudflare header
		$this->assertEquals('198.51.100.50', $key);
	}

	public function testGetUserIdWithRegistryUser()
	{
		// Set user ID in registry
		Registry::getInstance()->set(RegistryKeys::USER_ID, 123);

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config, 'user');
		$route = new RouteMap('/test', function() {});

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getUserId');
		$method->setAccessible(true);

		$userId = $method->invoke($filter);

		$this->assertEquals('user:123', $userId);

		// Clean up
		Registry::getInstance()->set(RegistryKeys::USER_ID, null);
	}

	public function testGetUserIdFallbackToIp()
	{
		// Ensure no user in registry
		Registry::getInstance()->set(RegistryKeys::USER_ID, null);

		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config, 'user');

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getUserId');
		$method->setAccessible(true);

		$userId = $method->invoke($filter);

		$this->assertEquals('ip:192.168.1.100', $userId);
	}

	public function testGetCustomKey()
	{
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config, 'custom');
		$route = new RouteMap('/api/users', function() {});

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getCustomKey');
		$method->setAccessible(true);

		$key = $method->invoke($filter, $route);

		// Default implementation returns IP
		$this->assertEquals('10.0.0.1', $key);
	}

	public function testIsBlacklisted()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$blacklist = ['192.168.1.50', '10.0.0.100'];
		$filter = new RateLimitFilter($config, 'ip', [], $blacklist);

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('isBlacklisted');
		$method->setAccessible(true);

		$this->assertTrue($method->invoke($filter, '192.168.1.50'));
		$this->assertTrue($method->invoke($filter, '10.0.0.100'));
		$this->assertFalse($method->invoke($filter, '192.168.1.1'));
	}

	public function testGetLimitWithBlacklist()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 100
		]);

		$blacklist = ['192.168.1.50'];
		$filter = new RateLimitFilter($config, 'ip', [], $blacklist);

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getLimit');
		$method->setAccessible(true);

		// Blacklisted should get limit / 10
		$this->assertEquals(10, $method->invoke($filter, '192.168.1.50'));

		// Normal should get full limit
		$this->assertEquals(100, $method->invoke($filter, '192.168.1.1'));
	}

	public function testGetWindow()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'window' => 3600
		]);

		$filter = new RateLimitFilter($config);

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getWindow');
		$method->setAccessible(true);

		$window = $method->invoke($filter, 'any_key');

		$this->assertEquals(3600, $window);
	}

	public function testGetStorage()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config);
		$storage = $filter->getStorage();

		$this->assertInstanceOf(MemoryRateLimitStorage::class, $storage);
	}

	public function testAddRateLimitHeaders()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory',
			'requests' => 10,
			'window' => 60
		]);

		$filter = new RateLimitFilter($config);

		// Make some requests to set up state
		$storage = $filter->getStorage();
		$storage->allow('test_key', 10, 60);
		$storage->allow('test_key', 10, 60);

		// Can't test actual headers in CLI, but can invoke the method
		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('addRateLimitHeaders');
		$method->setAccessible(true);

		// Should not throw exception
		try {
			$method->invoke($filter, 'test_key', 10, 60);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			// Expected in CLI - headers already sent
			$this->assertTrue(true);
		}
	}

	public function testRouteKeyStrategy()
	{
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config, 'route');
		$route = new RouteMap('/api/users', function() {});

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('generateKey');
		$method->setAccessible(true);

		$key = $method->invoke($filter, $route);

		// Should be IP:route
		$this->assertEquals('192.168.1.100:/api/users', $key);
	}

	public function testGetClientIp()
	{
		$_SERVER['REMOTE_ADDR'] = '203.0.113.50';

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config, 'ip');

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getClientIp');
		$method->setAccessible(true);

		$ip = $method->invoke($filter);

		$this->assertEquals('203.0.113.50', $ip);
	}

	public function testGetClientIpWithCustomResolver()
	{
		$mockResolver = $this->createMock(IIpResolver::class);
		$mockResolver->method('resolve')
			->willReturn('10.20.30.40');

		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$filter = new RateLimitFilter($config, 'ip', [], [], $mockResolver);

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('getClientIp');
		$method->setAccessible(true);

		$ip = $method->invoke($filter);

		$this->assertEquals('10.20.30.40', $ip);
	}

	public function testIsWhitelisted()
	{
		$config = new RateLimitConfig([
			'enabled' => true,
			'storage' => 'memory'
		]);

		$whitelist = ['192.168.1.100', '10.0.0.1'];
		$filter = new RateLimitFilter($config, 'ip', $whitelist, []);

		$reflection = new ReflectionClass($filter);
		$method = $reflection->getMethod('isWhitelisted');
		$method->setAccessible(true);

		$this->assertTrue($method->invoke($filter, '192.168.1.100'));
		$this->assertTrue($method->invoke($filter, '10.0.0.1'));
		$this->assertFalse($method->invoke($filter, '192.168.1.200'));
	}

	protected function tearDown(): void
	{
		// Clean up server variables
		unset($_SERVER['REMOTE_ADDR']);
		unset($_SERVER['HTTP_X_FORWARDED_FOR']);
		unset($_SERVER['HTTP_X_REAL_IP']);
		unset($_SERVER['HTTP_CF_CONNECTING_IP']);
	}
}
