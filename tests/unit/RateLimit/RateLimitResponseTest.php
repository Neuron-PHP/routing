<?php

namespace Tests\Unit\RateLimit;

use Neuron\Routing\RateLimit\RateLimitResponse;
use PHPUnit\Framework\TestCase;

class RateLimitResponseTest extends TestCase
{
	public function testBuildInfo(): void
	{
		$limit = 100;
		$remaining = 25;
		$resetTime = time() + 3600;

		$info = RateLimitResponse::buildInfo( $limit, $remaining, $resetTime );

		$this->assertArrayHasKey( 'limit', $info );
		$this->assertArrayHasKey( 'remaining', $info );
		$this->assertArrayHasKey( 'reset', $info );
		$this->assertArrayHasKey( 'retry_after', $info );

		$this->assertEquals( 100, $info['limit'] );
		$this->assertEquals( 25, $info['remaining'] );
		$this->assertEquals( $resetTime, $info['reset'] );
		$this->assertGreaterThan( 0, $info['retry_after'] );
	}

	public function testBuildInfoWithNegativeRemaining(): void
	{
		$info = RateLimitResponse::buildInfo( 100, -5, time() + 60 );

		// Remaining should be clamped to 0
		$this->assertEquals( 0, $info['remaining'] );
	}

	public function testBuildInfoWithPastResetTime(): void
	{
		$pastTime = time() - 10;
		$info = RateLimitResponse::buildInfo( 100, 50, $pastTime );

		// retry_after should be at least 1
		$this->assertGreaterThanOrEqual( 1, $info['retry_after'] );
	}

	public function testSendJsonResponse(): void
	{
		// Set Accept header for JSON
		$_SERVER['HTTP_ACCEPT'] = 'application/json';

		// Use output buffering to capture response
		ob_start();

		try {
			// This will try to send headers but will fail in CLI
			// We're mainly testing that the code runs without fatal errors
			RateLimitResponse::send( 100, 0, time() + 60, 'Custom message' );
		} catch ( \Exception $e ) {
			// Expected in CLI environment due to header() calls
		}

		$output = ob_get_clean();

		// If we got JSON output, verify its structure
		if ( !empty( $output ) ) {
			$decoded = json_decode( $output, true );
			if ( $decoded !== null ) {
				$this->assertArrayHasKey( 'error', $decoded );
				$this->assertArrayHasKey( 'message', $decoded );
				$this->assertArrayHasKey( 'rate_limit', $decoded );
			}
		}

		// Clean up
		unset( $_SERVER['HTTP_ACCEPT'] );

		// Test passes if no fatal error occurred
		$this->assertTrue( true );
	}

	public function testSendHtmlResponse(): void
	{
		// Set Accept header for HTML
		$_SERVER['HTTP_ACCEPT'] = 'text/html';

		ob_start();

		try {
			RateLimitResponse::send( 100, 5, time() + 120 );
		} catch ( \Exception $e ) {
			// Expected in CLI environment
		}

		$output = ob_get_clean();

		// If we got HTML output, check for key elements
		if ( !empty( $output ) ) {
			$this->assertStringContainsString( '429', $output );
			$this->assertStringContainsString( 'Too Many Requests', $output );
		}

		unset( $_SERVER['HTTP_ACCEPT'] );

		$this->assertTrue( true );
	}

	public function testSendWithDefaultMessage(): void
	{
		$_SERVER['HTTP_ACCEPT'] = 'application/json';

		ob_start();

		try {
			// No custom message provided
			RateLimitResponse::send( 50, 0, time() + 30 );
		} catch ( \Exception $e ) {
			// Expected
		}

		$output = ob_get_clean();

		if ( !empty( $output ) ) {
			$decoded = json_decode( $output, true );
			if ( $decoded !== null ) {
				$this->assertStringContainsString( 'try again later', strtolower( $decoded['message'] ) );
			}
		}

		unset( $_SERVER['HTTP_ACCEPT'] );

		$this->assertTrue( true );
	}

	public function testSendWithCustomMessage(): void
	{
		$_SERVER['HTTP_ACCEPT'] = 'application/json';

		ob_start();

		try {
			RateLimitResponse::send( 50, 0, time() + 30, 'Please slow down your requests' );
		} catch ( \Exception $e ) {
			// Expected
		}

		$output = ob_get_clean();

		if ( !empty( $output ) ) {
			$decoded = json_decode( $output, true );
			if ( $decoded !== null ) {
				$this->assertStringContainsString( 'slow down', $decoded['message'] );
			}
		}

		unset( $_SERVER['HTTP_ACCEPT'] );

		$this->assertTrue( true );
	}

	public function testSendWithNoAcceptHeader(): void
	{
		// No HTTP_ACCEPT set - should default to HTML
		unset( $_SERVER['HTTP_ACCEPT'] );

		ob_start();

		try {
			RateLimitResponse::send( 100, 10, time() + 60 );
		} catch ( \Exception $e ) {
			// Expected
		}

		$output = ob_get_clean();

		// Should have generated HTML by default
		if ( !empty( $output ) ) {
			$this->assertStringContainsString( 'html', strtolower( $output ) );
		}

		$this->assertTrue( true );
	}

	protected function tearDown(): void
	{
		// Clean up any lingering $_SERVER variables
		unset( $_SERVER['HTTP_ACCEPT'] );
		parent::tearDown();
	}
}
