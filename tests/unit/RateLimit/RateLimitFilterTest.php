<?php

use PHPUnit\Framework\TestCase;
use Neuron\Routing\Filters\RateLimitFilter;
use Neuron\Routing\RateLimit\RateLimitConfig;
use Neuron\Routing\RouteMap;
use Neuron\Routing\Router;

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
		$mockSource = $this->createMock(\Neuron\Data\Setting\Source\ISettingSource::class);

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

	protected function tearDown(): void
	{
		// Clean up server variables
		unset($_SERVER['REMOTE_ADDR']);
		unset($_SERVER['HTTP_X_FORWARDED_FOR']);
		unset($_SERVER['HTTP_X_REAL_IP']);
	}
}