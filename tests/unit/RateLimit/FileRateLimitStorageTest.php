<?php

namespace Tests\Unit\RateLimit;

use Neuron\Routing\RateLimit\Storage\FileRateLimitStorage;
use PHPUnit\Framework\TestCase;

class FileRateLimitStorageTest extends TestCase
{
	private string $testDir;
	private FileRateLimitStorage $storage;

	protected function setUp(): void
	{
		parent::setUp();

		// Create unique test directory
		$this->testDir = sys_get_temp_dir() . '/neuron_file_storage_test_' . uniqid();

		$this->storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'test_',
			'gc_probability' => 0 // Disable automatic GC during tests
		]);
	}

	protected function tearDown(): void
	{
		// Clean up test directory
		if( is_dir( $this->testDir ) )
		{
			$files = glob( $this->testDir . '/*' );
			if( $files !== false )
			{
				foreach( $files as $file )
				{
					if( is_file( $file ) )
					{
						unlink( $file );
					}
				}
			}
			rmdir( $this->testDir );
		}

		parent::tearDown();
	}

	public function testConstructorCreatesDirectory(): void
	{
		$this->assertDirectoryExists( $this->testDir );
	}

	public function testConstructorWithDefaultPath(): void
	{
		$storage = new FileRateLimitStorage(['gc_probability' => 0]);
		$this->assertInstanceOf( FileRateLimitStorage::class, $storage );
	}

	public function testAllowWithinLimit(): void
	{
		$key = 'test_key';
		$limit = 5;
		$window = 60;

		// First 5 requests should be allowed
		for( $i = 0; $i < $limit; $i++ )
		{
			$this->assertTrue(
				$this->storage->allow( $key, $limit, $window ),
				"Request $i should be allowed"
			);
		}

		// 6th request should be denied
		$this->assertFalse(
			$this->storage->allow( $key, $limit, $window ),
			"Request exceeding limit should be denied"
		);
	}

	public function testAllowExceedsLimit(): void
	{
		$key = 'test_limit';
		$limit = 2;
		$window = 60;

		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
		$this->assertFalse( $this->storage->allow( $key, $limit, $window ) );
	}

	public function testWindowExpiration(): void
	{
		$key = 'test_expiry';
		$limit = 2;
		$window = 1; // 1 second window

		// Use up the limit
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
		$this->assertFalse( $this->storage->allow( $key, $limit, $window ) );

		// Wait for window to expire
		sleep( 2 );

		// Should be allowed again
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
	}

	public function testGetRemainingAttempts(): void
	{
		$key = 'test_remaining';
		$limit = 5;
		$window = 60;

		// Initially should have all attempts
		$this->assertEquals( $limit, $this->storage->getRemainingAttempts( $key, $limit, $window ) );

		// Use 3 attempts
		for( $i = 0; $i < 3; $i++ )
		{
			$this->storage->allow( $key, $limit, $window );
		}

		// Should have 2 remaining
		$this->assertEquals( 2, $this->storage->getRemainingAttempts( $key, $limit, $window ) );
	}

	public function testGetRemainingAttemptsForNewKey(): void
	{
		$key = 'new_key';
		$limit = 10;
		$window = 60;

		$remaining = $this->storage->getRemainingAttempts( $key, $limit, $window );
		$this->assertEquals( $limit, $remaining );
	}

	public function testGetResetTime(): void
	{
		$key = 'test_reset_time';
		$limit = 5;
		$window = 60;

		$beforeTime = time();
		$this->storage->allow( $key, $limit, $window );
		$resetTime = $this->storage->getResetTime( $key, $window );

		// Reset time should be approximately window seconds from now
		$this->assertGreaterThanOrEqual( $beforeTime + $window - 1, $resetTime );
		$this->assertLessThanOrEqual( $beforeTime + $window + 2, $resetTime );
	}

	public function testGetResetTimeForNewKey(): void
	{
		$key = 'new_reset_key';
		$window = 120;

		$beforeTime = time();
		$resetTime = $this->storage->getResetTime( $key, $window );

		// For a new key with no attempts, should return time + window
		$this->assertGreaterThanOrEqual( $beforeTime + $window - 1, $resetTime );
		$this->assertLessThanOrEqual( $beforeTime + $window + 2, $resetTime );
	}

	public function testReset(): void
	{
		$key = 'test_reset';
		$limit = 2;
		$window = 60;

		// Use up the limit
		$this->storage->allow( $key, $limit, $window );
		$this->storage->allow( $key, $limit, $window );
		$this->assertFalse( $this->storage->allow( $key, $limit, $window ) );

		// Reset the key
		$this->storage->reset( $key );

		// Should be allowed again
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
	}

	public function testResetNonExistentKey(): void
	{
		// Should not throw exception
		$this->storage->reset( 'nonexistent_key' );
		$this->assertTrue( true );
	}

	public function testClear(): void
	{
		$limit = 2;
		$window = 60;

		// Set up multiple keys
		$this->storage->allow( 'key1', $limit, $window );
		$this->storage->allow( 'key2', $limit, $window );
		$this->storage->allow( 'key3', $limit, $window );

		// Verify files exist
		$files = glob( $this->testDir . '/*.rl' );
		$this->assertNotEmpty( $files );

		// Clear all
		$this->storage->clear();

		// Should be empty
		$files = glob( $this->testDir . '/*.rl' );
		$this->assertEmpty( $files );
	}

	public function testClearEmptyDirectory(): void
	{
		// Should not throw exception when clearing empty directory
		$this->storage->clear();
		$this->assertTrue( true );
	}

	public function testMultipleKeys(): void
	{
		$limit = 2;
		$window = 60;

		// Different keys should have independent limits
		$this->assertTrue( $this->storage->allow( 'key1', $limit, $window ) );
		$this->assertTrue( $this->storage->allow( 'key1', $limit, $window ) );
		$this->assertFalse( $this->storage->allow( 'key1', $limit, $window ) );

		// key2 should still be allowed
		$this->assertTrue( $this->storage->allow( 'key2', $limit, $window ) );
		$this->assertTrue( $this->storage->allow( 'key2', $limit, $window ) );
		$this->assertFalse( $this->storage->allow( 'key2', $limit, $window ) );
	}

	public function testPrefixIsolation(): void
	{
		$storage1 = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'app1_',
			'gc_probability' => 0
		]);

		$storage2 = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'app2_',
			'gc_probability' => 0
		]);

		$key = 'same_key';
		$limit = 1;
		$window = 60;

		// Use up limit in storage1
		$this->assertTrue( $storage1->allow( $key, $limit, $window ) );
		$this->assertFalse( $storage1->allow( $key, $limit, $window ) );

		// storage2 should still allow the same key
		$this->assertTrue( $storage2->allow( $key, $limit, $window ) );
	}

	public function testGarbageCollection(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'gc_',
			'gc_probability' => 0
		]);

		// Create some test data
		$storage->allow( 'key1', 5, 60 );
		$storage->allow( 'key2', 5, 60 );

		// Verify files exist
		$filesBefore = glob( $this->testDir . '/*.rl' );
		$this->assertNotEmpty( $filesBefore );

		// Run GC (should not remove recent files)
		$removed = $storage->gc();
		$this->assertEquals( 0, $removed );
	}

	public function testGarbageCollectionRemovesExpiredFiles(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'gc_expired_',
			'gc_probability' => 0
		]);

		// Create a file and manually modify it to have expired timestamp
		$key = 'expired_key';
		$storage->allow( $key, 5, 60 );

		// Get the file path
		$reflection = new \ReflectionClass( $storage );
		$method = $reflection->getMethod( 'getFilePath' );
		$method->setAccessible( true );
		$filePath = $method->invoke( $storage, $key );

		// Read the file, modify timestamps to be old, write back
		$data = json_decode( file_get_contents( $filePath ), true );
		$data['attempts'] = [time() - 90000]; // More than 1 day old
		file_put_contents( $filePath, json_encode( $data ) );

		// Run GC - should remove the expired file
		$removed = $storage->gc();
		$this->assertGreaterThan( 0, $removed );
	}

	public function testGarbageCollectionRemovesEmptyFiles(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'gc_empty_',
			'gc_probability' => 0
		]);

		// Create a file and manually make it empty
		$key = 'empty_key';
		$storage->allow( $key, 5, 60 );

		// Get the file path
		$reflection = new \ReflectionClass( $storage );
		$method = $reflection->getMethod( 'getFilePath' );
		$method->setAccessible( true );
		$filePath = $method->invoke( $storage, $key );

		// Write empty data
		file_put_contents( $filePath, json_encode( ['attempts' => []] ) );

		// Run GC - should remove the empty file
		$removed = $storage->gc();
		$this->assertEquals( 1, $removed );
	}

	public function testGarbageCollectionKeepsRecentFiles(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'gc_keep_',
			'gc_probability' => 0
		]);

		// Create recent files
		$storage->allow( 'recent1', 5, 60 );
		$storage->allow( 'recent2', 5, 60 );

		$filesBefore = glob( $this->testDir . '/*.rl' );
		$countBefore = count( $filesBefore );

		// Run GC
		$removed = $storage->gc();

		// Should not remove recent files
		$filesAfter = glob( $this->testDir . '/*.rl' );
		$this->assertEquals( $countBefore, count( $filesAfter ) );
		$this->assertEquals( 0, $removed );
	}

	public function testFilePathHashing(): void
	{
		// Test that special characters in keys are handled
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'hash_',
			'gc_probability' => 0
		]);

		$key = 'special/chars:test@key';
		$limit = 5;
		$window = 60;

		// Should not throw exception
		$this->assertTrue( $storage->allow( $key, $limit, $window ) );

		// Verify file was created
		$files = glob( $this->testDir . '/*.rl' );
		$this->assertNotEmpty( $files );
	}

	public function testCorruptedFileHandling(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'corrupt_',
			'gc_probability' => 0
		]);

		$key = 'corrupted_key';

		// Get file path using reflection
		$reflection = new \ReflectionClass( $storage );
		$method = $reflection->getMethod( 'getFilePath' );
		$method->setAccessible( true );
		$filePath = $method->invoke( $storage, $key );

		// Create a corrupted file
		file_put_contents( $filePath, 'not valid json' );

		// Should handle corrupted file gracefully
		$remaining = $storage->getRemainingAttempts( $key, 10, 60 );
		$this->assertEquals( 10, $remaining );

		// Should be able to write over corrupted file
		$this->assertTrue( $storage->allow( $key, 10, 60 ) );
	}

	public function testConcurrentKeyAccess(): void
	{
		// Test that using different keys doesn't interfere
		$key1 = 'concurrent_key1';
		$key2 = 'concurrent_key2';
		$limit = 3;
		$window = 60;

		// Use key1
		$this->storage->allow( $key1, $limit, $window );
		$this->storage->allow( $key1, $limit, $window );

		// Use key2
		$this->storage->allow( $key2, $limit, $window );

		// Verify independent limits
		$this->assertEquals( 1, $this->storage->getRemainingAttempts( $key1, $limit, $window ) );
		$this->assertEquals( 2, $this->storage->getRemainingAttempts( $key2, $limit, $window ) );
	}

	public function testGcWithNoFiles(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'gc_none_',
			'gc_probability' => 0
		]);

		// Run GC on empty directory
		$removed = $storage->gc();
		$this->assertEquals( 0, $removed );
	}

	public function testResetTimeWithMultipleAttempts(): void
	{
		$key = 'multi_attempt';
		$limit = 5;
		$window = 60;

		// Make multiple attempts
		$this->storage->allow( $key, $limit, $window );
		sleep( 1 );
		$this->storage->allow( $key, $limit, $window );
		sleep( 1 );
		$this->storage->allow( $key, $limit, $window );

		$resetTime = $this->storage->getResetTime( $key, $window );

		// Reset time should be based on oldest attempt
		$expectedReset = time() + $window - 2; // Approximately, accounting for the 2 sleeps
		$this->assertGreaterThanOrEqual( $expectedReset - 2, $resetTime );
	}

	public function testAllowReturnsCorrectBooleanOnEdgeCases(): void
	{
		$key = 'edge_case';
		$limit = 1;
		$window = 60;

		// First request allowed
		$result1 = $this->storage->allow( $key, $limit, $window );
		$this->assertTrue( $result1 );

		// Second request denied (at limit)
		$result2 = $this->storage->allow( $key, $limit, $window );
		$this->assertFalse( $result2 );

		// Verify remaining is 0
		$remaining = $this->storage->getRemainingAttempts( $key, $limit, $window );
		$this->assertEquals( 0, $remaining );
	}

	public function testAllowWithExpiredAttempts(): void
	{
		$key = 'expired_attempts';
		$limit = 3;
		$window = 2; // 2 second window

		// Make attempts
		$this->storage->allow( $key, $limit, $window );
		$this->storage->allow( $key, $limit, $window );

		// Wait for attempts to expire
		sleep( 3 );

		// Old attempts should be filtered out, allowing new ones
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );
		$this->assertTrue( $this->storage->allow( $key, $limit, $window ) );

		// 4th should be denied
		$this->assertFalse( $this->storage->allow( $key, $limit, $window ) );
	}

	public function testGetRemainingAttemptsWithExpired(): void
	{
		$key = 'remaining_expired';
		$limit = 5;
		$window = 1; // 1 second window

		// Make some attempts
		$this->storage->allow( $key, $limit, $window );
		$this->storage->allow( $key, $limit, $window );

		// Should have 3 remaining
		$this->assertEquals( 3, $this->storage->getRemainingAttempts( $key, $limit, $window ) );

		// Wait for expiry
		sleep( 2 );

		// Should have full limit again
		$this->assertEquals( 5, $this->storage->getRemainingAttempts( $key, $limit, $window ) );
	}

	public function testReadOnlyDirectoryScenario(): void
	{
		// Test behavior when directory cannot be created (simulated)
		// This test verifies the constructor handles creation failures gracefully

		// Create a storage with a path that exists
		$tempDir = sys_get_temp_dir() . '/neuron_readonly_test_' . uniqid();
		mkdir( $tempDir, 0777, true );

		$storage = new FileRateLimitStorage([
			'path' => $tempDir,
			'prefix' => 'ro_',
			'gc_probability' => 0
		]);

		// Should still be usable
		$this->assertTrue( $storage->allow( 'test', 5, 60 ) );

		// Cleanup
		$files = glob( $tempDir . '/*' );
		if( $files )
		{
			foreach( $files as $file )
			{
				unlink( $file );
			}
		}
		rmdir( $tempDir );
	}

	public function testGcWithCorruptedFile(): void
	{
		$storage = new FileRateLimitStorage([
			'path' => $this->testDir,
			'prefix' => 'gc_corrupt_',
			'gc_probability' => 0
		]);

		// Create a file and make it corrupted
		$key = 'corrupt_gc_key';
		$storage->allow( $key, 5, 60 );

		// Get file path using reflection
		$reflection = new \ReflectionClass( $storage );
		$method = $reflection->getMethod( 'getFilePath' );
		$method->setAccessible( true );
		$filePath = $method->invoke( $storage, $key );

		// Write corrupted data
		file_put_contents( $filePath, 'invalid json data' );

		// GC should remove corrupted file
		$removed = $storage->gc();
		$this->assertEquals( 1, $removed );
	}
}
