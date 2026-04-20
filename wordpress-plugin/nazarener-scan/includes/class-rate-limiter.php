<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP-based rate limiting via WordPress transients (no external service).
 */
class NazarenerScan_RateLimiter {

	/**
	 * Check whether a request is within the allowed rate.
	 *
	 * @param string $action    Unique action identifier (e.g. 'lookup', 'submit').
	 * @param string $identifier IP address or user ID.
	 * @param int    $limit     Max number of requests in the window.
	 * @param int    $window    Time window in seconds.
	 * @return bool true = allowed, false = rate-limited.
	 */
	public static function allow( string $action, string $identifier, int $limit, int $window ): bool {
		$key   = 'ns_rl_' . md5( $action . $identifier );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		// First hit: set with expiry; subsequent hits: increment
		if ( $count === 0 ) {
			set_transient( $key, 1, $window );
		} else {
			// Preserve remaining TTL by re-setting; WP doesn't expose TTL,
			// so we use a separate TTL-tracking key.
			$ttl_key      = $key . '_exp';
			$expires_at   = (int) get_transient( $ttl_key );
			$remaining_ttl = max( 1, $expires_at - time() );

			if ( ! $expires_at ) {
				set_transient( $key, $count + 1, $window );
				set_transient( $ttl_key, time() + $window, $window );
			} else {
				set_transient( $key, $count + 1, $remaining_ttl );
			}
		}

		if ( $count === 0 ) {
			set_transient( $key . '_exp', time() + $window, $window );
		}

		return true;
	}

	/**
	 * Return remaining seconds until the rate window resets.
	 */
	public static function retry_after( string $action, string $identifier ): int {
		$ttl_key    = 'ns_rl_' . md5( $action . $identifier ) . '_exp';
		$expires_at = (int) get_transient( $ttl_key );
		return $expires_at ? max( 0, $expires_at - time() ) : 0;
	}

	public static function get_client_ip(): string {
		$keys = [
			'HTTP_CF_CONNECTING_IP',   // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}
