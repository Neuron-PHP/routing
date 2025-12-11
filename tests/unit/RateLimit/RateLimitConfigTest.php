<?php

namespace Tests\Unit\RateLimit;

use Neuron\Routing\RateLimit\RateLimitConfig;
use Neuron\Data\Settings\Source\ISettingSource;
use PHPUnit\Framework\TestCase;

class RateLimitConfigTest extends TestCase
{
	public function testConstructorWithDefaults(): void
	{
		$config = new RateLimitConfig();

		$this->assertFalse( $config->isEnabled() );
		$this->assertEquals( 'memory', $config->getStorage() );
		$this->assertEquals( 100, $config->getLimit() );
		$this->assertEquals( 3600, $config->getWindow() );
		$this->assertEquals( 0, $config->getBurstSize() );
		$this->assertEquals( 'rl_', $config->getKeyPrefix() );
	}

	public function testConstructorWithCustomSettings(): void
	{
		$settings = [
			'enabled' => true,
			'storage' => 'redis',
			'requests' => 50,
			'window' => 1800,
			'burst_size' => 10,
			'key_prefix' => 'custom_'
		];

		$config = new RateLimitConfig( $settings );

		$this->assertTrue( $config->isEnabled() );
		$this->assertEquals( 'redis', $config->getStorage() );
		$this->assertEquals( 50, $config->getLimit() );
		$this->assertEquals( 1800, $config->getWindow() );
		$this->assertEquals( 10, $config->getBurstSize() );
		$this->assertEquals( 'custom_', $config->getKeyPrefix() );
	}

	public function testGetRedisHost(): void
	{
		$config = new RateLimitConfig([ 'redis_host' => '192.168.1.100' ]);
		$this->assertEquals( '192.168.1.100', $config->getRedisHost() );
	}

	public function testGetRedisPort(): void
	{
		$config = new RateLimitConfig([ 'redis_port' => 6380 ]);
		$this->assertEquals( 6380, $config->getRedisPort() );
	}

	public function testGetRedisDatabase(): void
	{
		$config = new RateLimitConfig([ 'redis_database' => 3 ]);
		$this->assertEquals( 3, $config->getRedisDatabase() );
	}

	public function testGetRedisPrefix(): void
	{
		$config = new RateLimitConfig([ 'redis_prefix' => 'myapp_rl_' ]);
		$this->assertEquals( 'myapp_rl_', $config->getRedisPrefix() );
	}

	public function testGetRedisTimeout(): void
	{
		$config = new RateLimitConfig([ 'redis_timeout' => 5.0 ]);
		$this->assertEquals( 5.0, $config->getRedisTimeout() );
	}

	public function testGetRedisAuth(): void
	{
		$config = new RateLimitConfig([ 'redis_auth' => 'secret123' ]);
		$this->assertEquals( 'secret123', $config->getRedisAuth() );
	}

	public function testGetRedisAuthNull(): void
	{
		$config = new RateLimitConfig();
		$this->assertNull( $config->getRedisAuth() );
	}

	public function testGetRedisPersistent(): void
	{
		$config = new RateLimitConfig([ 'redis_persistent' => true ]);
		$this->assertTrue( $config->getRedisPersistent() );

		$config2 = new RateLimitConfig([ 'redis_persistent' => false ]);
		$this->assertFalse( $config2->getRedisPersistent() );
	}

	public function testGetFilePath(): void
	{
		$config = new RateLimitConfig([ 'file_path' => '/var/cache/limits' ]);
		$this->assertEquals( '/var/cache/limits', $config->getFilePath() );
	}

	public function testGetRedisConfig(): void
	{
		$config = new RateLimitConfig([
			'redis_host' => '10.0.0.5',
			'redis_port' => 6380,
			'redis_database' => 2,
			'redis_prefix' => 'app_',
			'redis_timeout' => 3.5,
			'redis_auth' => 'password',
			'redis_persistent' => true
		]);

		$redisConfig = $config->getRedisConfig();

		$this->assertArrayHasKey( 'host', $redisConfig );
		$this->assertArrayHasKey( 'port', $redisConfig );
		$this->assertArrayHasKey( 'database', $redisConfig );
		$this->assertArrayHasKey( 'prefix', $redisConfig );
		$this->assertArrayHasKey( 'timeout', $redisConfig );
		$this->assertArrayHasKey( 'auth', $redisConfig );
		$this->assertArrayHasKey( 'persistent', $redisConfig );

		$this->assertEquals( '10.0.0.5', $redisConfig['host'] );
		$this->assertEquals( 6380, $redisConfig['port'] );
		$this->assertEquals( 2, $redisConfig['database'] );
		$this->assertEquals( 'app_', $redisConfig['prefix'] );
		$this->assertEquals( 3.5, $redisConfig['timeout'] );
		$this->assertEquals( 'password', $redisConfig['auth'] );
		$this->assertTrue( $redisConfig['persistent'] );
	}

	public function testToArray(): void
	{
		$settings = [
			'enabled' => true,
			'storage' => 'file',
			'requests' => 200,
			'window' => 7200
		];

		$config = new RateLimitConfig( $settings );
		$array = $config->toArray();

		$this->assertIsArray( $array );
		$this->assertArrayHasKey( 'enabled', $array );
		$this->assertArrayHasKey( 'storage', $array );
		$this->assertArrayHasKey( 'requests', $array );
		$this->assertArrayHasKey( 'window', $array );

		$this->assertTrue( $array['enabled'] );
		$this->assertEquals( 'file', $array['storage'] );
		$this->assertEquals( 200, $array['requests'] );
		$this->assertEquals( 7200, $array['window'] );
	}

	public function testFromSettings(): void
	{
		// Create a mock settings source
		$mockSource = $this->createMock( ISettingSource::class );

		// Set up expectations for get() calls
		$mockSource->method( 'get' )
			->willReturnCallback( function( $category, $key ) {
				$settings = [
					'rate_limit' => [
						'enabled' => true,
						'storage' => 'redis',
						'requests' => 75,
						'window' => 900,
						'redis_host' => '192.168.1.50',
						'redis_port' => 6380,
						'redis_database' => 1,
						'redis_prefix' => 'test_',
						'redis_timeout' => 3.0,
						'redis_auth' => 'testpass',
						'redis_persistent' => true,
						'file_path' => '/tmp/limits',
						'key_prefix' => 'test_rl_',
						'burst_size' => 15
					]
				];

				return $settings[$category][$key] ?? null;
			});

		$config = RateLimitConfig::fromSettings( $mockSource );

		$this->assertTrue( $config->isEnabled() );
		$this->assertEquals( 'redis', $config->getStorage() );
		$this->assertEquals( 75, $config->getLimit() );
		$this->assertEquals( 900, $config->getWindow() );
		$this->assertEquals( '192.168.1.50', $config->getRedisHost() );
		$this->assertEquals( 6380, $config->getRedisPort() );
		$this->assertEquals( 1, $config->getRedisDatabase() );
		$this->assertEquals( 'test_', $config->getRedisPrefix() );
		$this->assertEquals( 3.0, $config->getRedisTimeout() );
		$this->assertEquals( 'testpass', $config->getRedisAuth() );
		$this->assertTrue( $config->getRedisPersistent() );
		$this->assertEquals( '/tmp/limits', $config->getFilePath() );
		$this->assertEquals( 'test_rl_', $config->getKeyPrefix() );
		$this->assertEquals( 15, $config->getBurstSize() );
	}

	public function testFromSettingsWithDefaults(): void
	{
		// Mock source that returns null for all keys
		$mockSource = $this->createMock( ISettingSource::class );
		$mockSource->method( 'get' )->willReturn( null );

		$config = RateLimitConfig::fromSettings( $mockSource );

		// Should use defaults
		$this->assertFalse( $config->isEnabled() );
		$this->assertEquals( 'memory', $config->getStorage() );
		$this->assertEquals( 100, $config->getLimit() );
		$this->assertEquals( 3600, $config->getWindow() );
	}

	public function testFromSettingsWithCustomCategory(): void
	{
		$mockSource = $this->createMock( ISettingSource::class );

		$mockSource->method( 'get' )
			->willReturnCallback( function( $category, $key ) {
				if ( $category === 'api_limits' && $key === 'enabled' ) {
					return true;
				}
				if ( $category === 'api_limits' && $key === 'requests' ) {
					return 500;
				}
				return null;
			});

		$config = RateLimitConfig::fromSettings( $mockSource, 'api_limits' );

		$this->assertTrue( $config->isEnabled() );
		$this->assertEquals( 500, $config->getLimit() );
	}
}
