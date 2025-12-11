<?php

use Neuron\Core\System\FrozenClock;
use PHPUnit\Framework\TestCase;
use Neuron\Routing\RateLimit\Storage\MemoryRateLimitStorage;

class MemoryRateLimitStorageTest extends TestCase
{
	private MemoryRateLimitStorage $storage;
	private FrozenClock $clock;

	protected function setUp(): void
	{
		$this->clock = new FrozenClock(1000000); // Fixed time for deterministic tests
		$this->storage = new MemoryRateLimitStorage(['prefix' => 'test_'], $this->clock);
	}

	public function testAllowWithinLimit()
	{
		$key = 'test_key';
		$limit = 5;
		$window = 60;

		// First 5 requests should be allowed
		for ($i = 0; $i < $limit; $i++) {
			$this->assertTrue(
				$this->storage->allow($key, $limit, $window),
				"Request $i should be allowed"
			);
		}

		// 6th request should be denied
		$this->assertFalse(
			$this->storage->allow($key, $limit, $window),
			"Request exceeding limit should be denied"
		);
	}

	public function testGetRemainingAttempts()
	{
		$key = 'test_key';
		$limit = 5;
		$window = 60;

		// Initially should have all attempts
		$this->assertEquals($limit, $this->storage->getRemainingAttempts($key, $limit, $window));

		// Use 3 attempts
		for ($i = 0; $i < 3; $i++) {
			$this->storage->allow($key, $limit, $window);
		}

		// Should have 2 remaining
		$this->assertEquals(2, $this->storage->getRemainingAttempts($key, $limit, $window));
	}

	public function testWindowExpiration()
	{
		$key = 'test_key';
		$limit = 2;
		$window = 1; // 1 second window

		// Use up the limit
		$this->assertTrue($this->storage->allow($key, $limit, $window));
		$this->assertTrue($this->storage->allow($key, $limit, $window));
		$this->assertFalse($this->storage->allow($key, $limit, $window));

		// Advance time past window expiration (instant, no actual sleeping!)
		$this->clock->advance(2);

		// Should be allowed again
		$this->assertTrue($this->storage->allow($key, $limit, $window));
	}

	public function testReset()
	{
		$key = 'test_key';
		$limit = 2;
		$window = 60;

		// Use up the limit
		$this->storage->allow($key, $limit, $window);
		$this->storage->allow($key, $limit, $window);
		$this->assertFalse($this->storage->allow($key, $limit, $window));

		// Reset the key
		$this->storage->reset($key);

		// Should be allowed again
		$this->assertTrue($this->storage->allow($key, $limit, $window));
	}

	public function testClear()
	{
		$limit = 2;
		$window = 60;

		// Set up multiple keys
		$this->storage->allow('key1', $limit, $window);
		$this->storage->allow('key2', $limit, $window);

		// Verify data exists
		$this->assertNotEmpty($this->storage->getStorage());

		// Clear all
		$this->storage->clear();

		// Should be empty
		$this->assertEmpty($this->storage->getStorage());
	}

	public function testGetResetTime()
	{
		$key = 'test_key';
		$limit = 5;
		$window = 60;

		$beforeTime = $this->clock->time();
		$this->storage->allow($key, $limit, $window);
		$resetTime = $this->storage->getResetTime($key, $window);

		// Reset time should be exactly window seconds from when request was made
		$this->assertEquals($beforeTime + $window, $resetTime);
	}

	public function testMultipleKeys()
	{
		$limit = 2;
		$window = 60;

		// Different keys should have independent limits
		$this->assertTrue($this->storage->allow('key1', $limit, $window));
		$this->assertTrue($this->storage->allow('key1', $limit, $window));
		$this->assertFalse($this->storage->allow('key1', $limit, $window));

		// key2 should still be allowed
		$this->assertTrue($this->storage->allow('key2', $limit, $window));
		$this->assertTrue($this->storage->allow('key2', $limit, $window));
		$this->assertFalse($this->storage->allow('key2', $limit, $window));
	}

	public function testPrefixIsolation()
	{
		$clock = new FrozenClock(1000000);
		$storage1 = new MemoryRateLimitStorage(['prefix' => 'app1_'], $clock);
		$storage2 = new MemoryRateLimitStorage(['prefix' => 'app2_'], $clock);

		$key = 'same_key';
		$limit = 1;
		$window = 60;

		// Use up limit in storage1
		$this->assertTrue($storage1->allow($key, $limit, $window));
		$this->assertFalse($storage1->allow($key, $limit, $window));

		// storage2 should still allow the same key
		$this->assertTrue($storage2->allow($key, $limit, $window));
	}

	public function testGetResetTimeForNewKey()
	{
		$key = 'new_key';
		$window = 120;

		$beforeTime = $this->clock->time();
		$resetTime = $this->storage->getResetTime($key, $window);

		// For a new key with no attempts, should return exactly time + window
		$this->assertEquals($beforeTime + $window, $resetTime);
	}
}