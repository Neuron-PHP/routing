<?php

namespace Neuron\Routing\RateLimit;

/**
 * Rate limit response formatter.
 *
 * Handles HTTP 429 responses with appropriate headers.
 *
 * @package Neuron\Routing\RateLimit
 */
class RateLimitResponse
{
	/**
	 * Send rate limit exceeded response.
	 *
	 * @param int $limit Maximum requests allowed
	 * @param int $remaining Remaining requests
	 * @param int $resetTime Unix timestamp when limit resets
	 * @param string $message Custom error message
	 * @return void
	 */
	public static function send( int $limit, int $remaining, int $resetTime, string $message = '' ): void
	{
		// Set HTTP status
		http_response_code( 429 );

		// Set rate limit headers
		header( 'X-RateLimit-Limit: ' . $limit );
		header( 'X-RateLimit-Remaining: ' . max( 0, $remaining ) );
		header( 'X-RateLimit-Reset: ' . $resetTime );

		// Calculate retry after
		$retryAfter = max( 1, $resetTime - time() );
		header( 'Retry-After: ' . $retryAfter );

		// Set content type
		$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? 'text/html';

		if( strpos( $acceptHeader, 'application/json' ) !== false )
		{
			self::sendJsonResponse( $limit, $remaining, $resetTime, $message );
		}
		else
		{
			self::sendHtmlResponse( $limit, $remaining, $resetTime, $message );
		}
	}

	/**
	 * Send JSON error response.
	 *
	 * @param int $limit
	 * @param int $remaining
	 * @param int $resetTime
	 * @param string $message
	 * @return void
	 */
	private static function sendJsonResponse( int $limit, int $remaining, int $resetTime, string $message ): void
	{
		header( 'Content-Type: application/json' );

		$response = [
			'error' => 'rate_limit_exceeded',
			'message' => $message ?: 'Too many requests. Please try again later.',
			'rate_limit' => [
				'limit' => $limit,
				'remaining' => max( 0, $remaining ),
				'reset' => $resetTime,
				'retry_after' => max( 1, $resetTime - time() )
			]
		];

		echo json_encode( $response, JSON_PRETTY_PRINT );
	}

	/**
	 * Send HTML error response.
	 *
	 * @param int $limit
	 * @param int $remaining
	 * @param int $resetTime
	 * @param string $message
	 * @return void
	 */
	private static function sendHtmlResponse( int $limit, int $remaining, int $resetTime, string $message ): void
	{
		header( 'Content-Type: text/html; charset=utf-8' );

		$retryAfter = max( 1, $resetTime - time() );
		$resetTimeFormatted = date( 'Y-m-d H:i:s', $resetTime );
		$customMessage = $message ?: 'Too many requests. Please try again later.';

		echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 Too Many Requests</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 1rem;
        }
        .details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
            text-align: left;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0;
        }
        .detail-label {
            font-weight: 600;
        }
        .retry-message {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>429 - Too Many Requests</h1>
        <p>{$customMessage}</p>

        <div class="details">
            <div class="detail-item">
                <span class="detail-label">Rate Limit:</span>
                <span>{$limit} requests per window</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Remaining:</span>
                <span>{$remaining}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Reset Time:</span>
                <span>{$resetTimeFormatted}</span>
            </div>
        </div>

        <div class="retry-message">
            Please wait {$retryAfter} seconds before trying again.
        </div>
    </div>
</body>
</html>
HTML;
	}

	/**
	 * Build rate limit info array.
	 *
	 * @param int $limit
	 * @param int $remaining
	 * @param int $resetTime
	 * @return array
	 */
	public static function buildInfo( int $limit, int $remaining, int $resetTime ): array
	{
		return [
			'limit' => $limit,
			'remaining' => max( 0, $remaining ),
			'reset' => $resetTime,
			'retry_after' => max( 1, $resetTime - time() )
		];
	}
}