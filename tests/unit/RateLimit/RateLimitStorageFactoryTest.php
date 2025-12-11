<?php

namespace Tests\Unit\RateLimit;

use Neuron\Routing\RateLimit\Storage\RateLimitStorageFactory;
use Neuron\Routing\RateLimit\Storage\MemoryRateLimitStorage;
use Neuron\Routing\RateLimit\Storage\FileRateLimitStorage;
use Neuron\Routing\RateLimit\Storage\RedisRateLimitStorage;
use Neuron\Routing\RateLimit\RateLimitConfig;
use PHPUnit\Framework\TestCase;

class RateLimitStorageFactoryTest extends TestCase
{
	public function testCreateMemoryStorage(): void
	{
		$config = new RateLimitConfig([
			'storage' => 'memory',
			'key_prefix' => 'test_'
		]);

		$storage = RateLimitStorageFactory::create( $config );

		$this->assertInstanceOf( MemoryRateLimitStorage::class, $storage );
	}

	public function testCreateFileStorage(): void
	{
		$tmpDir = sys_get_temp_dir() . '/neuron_test_' . uniqid();

		$config = new RateLimitConfig([
			'storage' => 'file',
			'file_path' => 'cache/limits',
			'key_prefix' => 'rl_'
		]);

		$storage = RateLimitStorageFactory::create( $config, $tmpDir );

		$this->assertInstanceOf( FileRateLimitStorage::class, $storage );

		// Clean up
		if (is_dir($tmpDir)) {
			rmdir($tmpDir . '/cache/limits');
			rmdir($tmpDir . '/cache');
			rmdir($tmpDir);
		}
	}

	public function testCreateFileStorageWithAbsolutePath(): void
	{
		$tmpDir = sys_get_temp_dir() . '/neuron_abs_' . uniqid();

		$config = new RateLimitConfig([
			'storage' => 'file',
			'file_path' => $tmpDir,
			'key_prefix' => 'rl_'
		]);

		$storage = RateLimitStorageFactory::create( $config, '/base' );

		$this->assertInstanceOf( FileRateLimitStorage::class, $storage );

		// Clean up
		if (is_dir($tmpDir)) {
			rmdir($tmpDir);
		}
	}

	public function testCreateFileStorageWithoutBasePath(): void
	{
		$tmpDir = sys_get_temp_dir() . '/neuron_nobase_' . uniqid();
		mkdir($tmpDir, 0777, true);

		$config = new RateLimitConfig([
			'storage' => 'file',
			'file_path' => $tmpDir
		]);

		$storage = RateLimitStorageFactory::create( $config );

		$this->assertInstanceOf( FileRateLimitStorage::class, $storage );

		// Clean up
		if (is_dir($tmpDir)) {
			rmdir($tmpDir);
		}
	}

	public function testCreateRedisStorage(): void
	{
		$config = new RateLimitConfig([
			'storage' => 'redis',
			'redis_host' => '127.0.0.1',
			'redis_port' => 6379
		]);

		$storage = RateLimitStorageFactory::create( $config );

		$this->assertInstanceOf( RedisRateLimitStorage::class, $storage );
	}

	public function testCreateThrowsExceptionForUnknownType(): void
	{
		$config = new RateLimitConfig([
			'storage' => 'invalid_type'
		]);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Unknown rate limit storage type: invalid_type' );

		RateLimitStorageFactory::create( $config );
	}

	public function testCreateMemoryStorageUsesPrefix(): void
	{
		$config = new RateLimitConfig([
			'storage' => 'memory',
			'key_prefix' => 'custom_prefix_'
		]);

		$storage = RateLimitStorageFactory::create( $config );

		// Test that the storage works with the prefix
		$this->assertTrue( $storage->allow( 'test_key', 10, 60 ) );
	}

	public function testCreateRedisStorageUsesConfig(): void
	{
		$config = new RateLimitConfig([
			'storage' => 'redis',
			'redis_host' => '10.0.0.1',
			'redis_port' => 6380,
			'redis_database' => 2,
			'redis_prefix' => 'app_',
			'redis_timeout' => 3.0,
			'redis_auth' => 'password'
		]);

		$storage = RateLimitStorageFactory::create( $config );

		$this->assertInstanceOf( RedisRateLimitStorage::class, $storage );
	}
}
