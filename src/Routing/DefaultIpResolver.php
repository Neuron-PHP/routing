<?php

namespace Neuron\Routing;

/**
 * Default IP resolver implementation that checks common proxy headers.
 *
 * This resolver checks standard proxy headers in order of preference,
 * validating and extracting the client IP address. It handles:
 * - Cloudflare (CF-Connecting-IP)
 * - Standard proxies (X-Forwarded-For)
 * - Nginx (X-Real-IP)
 * - Other proxies (Client-IP)
 *
 * Falls back to REMOTE_ADDR if no valid IP is found in headers.
 *
 * @package Neuron\Routing
 */
class DefaultIpResolver implements IIpResolver
{
	/**
	 * Resolve the client IP address by checking common proxy headers.
	 *
	 * @param array $server The $_SERVER array
	 * @return string The resolved IP address
	 */
	public function resolve( array $server ): string
	{
		// Check common proxy headers in order of preference
		$headers = [
			'HTTP_CF_CONNECTING_IP',	// Cloudflare
			'HTTP_X_FORWARDED_FOR',		// Standard proxy
			'HTTP_X_REAL_IP',				// Nginx
			'HTTP_CLIENT_IP',				// Some proxies
		];

		foreach( $headers as $header )
		{
			if( !empty( $server[ $header ] ) )
			{
				// Handle comma-separated list (proxy chain) - take first IP
				$ip = $this->extractFirstIp( $server[ $header ] );

				// Validate IP format
				if( filter_var( $ip, FILTER_VALIDATE_IP ) )
				{
					return $ip;
				}
			}
		}

		// Fallback to direct connection
		return $server[ 'REMOTE_ADDR' ] ?? '0.0.0.0';
	}

	/**
	 * Extract the first IP from a potentially comma-separated list.
	 *
	 * @param string $ipString The IP string (may contain multiple IPs)
	 * @return string The first IP address
	 */
	protected function extractFirstIp( string $ipString ): string
	{
		if( str_contains( $ipString, ',' ) )
		{
			$ips = explode( ',', $ipString );
			return trim( $ips[0] );
		}

		return trim( $ipString );
	}
}
