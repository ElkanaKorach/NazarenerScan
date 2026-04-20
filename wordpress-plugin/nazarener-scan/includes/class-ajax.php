<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Ajax {

	public function __construct() {
		$actions = [
			'nazarener_lookup'        => [ true,  'lookup' ],
			'nazarener_submit'        => [ true,  'submit' ],
			'nazarener_analyze_hints' => [ true,  'analyze_hints' ],
			'nazarener_notify_me'     => [ true,  'notify_me' ],
			'nazarener_captcha_new'   => [ false, 'captcha_new' ],
		];

		foreach ( $actions as $action => [ $allow_guests, $method ] ) {
			add_action( "wp_ajax_$action",        [ $this, $method ] );
			if ( $allow_guests ) {
				add_action( "wp_ajax_nopriv_$action", [ $this, $method ] );
			}
		}
	}

	// ── Product lookup ────────────────────────────────────────────────────────

	public function lookup(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );

		$ip      = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'lookup', $ip, 60, 60 ) ) {
			wp_send_json_error( [ 'message' => 'Zu viele Anfragen. Bitte kurz warten.' ], 429 );
		}

		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );
		if ( ! $barcode ) {
			wp_send_json_error( [ 'message' => 'Kein Barcode angegeben.' ], 400 );
		}

		// Transient cache
		$cache_key = 'ns_product_' . md5( $barcode );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			NazarenerScan_Database::log_scan( $barcode, $cached['status'] );
			wp_send_json_success( $cached );
		}

		$product = NazarenerScan_Database::get_product_by_barcode( $barcode );

		if ( ! $product ) {
			$data = [
				'status'            => 'yellow',
				'already_submitted' => self::has_pending_submission( $barcode ),
				'notify_count'      => NazarenerScan_Database::count_notify_requests( $barcode ),
			];
			NazarenerScan_Database::log_scan( $barcode, 'yellow' );
			// Don't cache yellow (product might get added soon)
			wp_send_json_success( $data );
		}

		$data = [
			'status'                => $product->status,
			'name'                  => esc_html( $product->name ),
			'brand'                 => esc_html( $product->brand ),
			'forbidden_ingredients' => esc_html( $product->forbidden_ingredients ?? '' ),
			'biblical_reference'    => esc_html( $product->biblical_reference ?? '' ),
			'notes'                 => esc_html( $product->notes ?? '' ),
			'reviewed_at'           => $product->reviewed_at,
			'image_url'             => $product->product_image_id
				? esc_url( wp_get_attachment_image_url( $product->product_image_id, 'medium' ) )
				: '',
		];

		set_transient( $cache_key, $data, HOUR_IN_SECONDS );
		NazarenerScan_Database::log_scan( $barcode, $product->status );
		wp_send_json_success( $data );
	}

	// ── Product submission ────────────────────────────────────────────────────

	public function submit(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );

		$ip = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'submit', $ip, 3, 3600 ) ) {
			$retry = NazarenerScan_RateLimiter::retry_after( 'submit', $ip );
			wp_send_json_error( [
				'message' => sprintf( 'Zu viele Einreichungen. Bitte in %d Minuten erneut versuchen.', ceil( $retry / 60 ) ),
			], 429 );
		}

		$settings = get_option( 'nazarener_scan_settings', [] );
		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Einreichung nur für angemeldete Nutzer.' ], 403 );
		}

		// Anti-spam verification
		$spam_error = '';
		if ( ! NazarenerScan_Captcha::verify_post( $spam_error ) ) {
			wp_send_json_error( [ 'message' => $spam_error ], 422 );
		}

		// DSGVO consent required
		if ( empty( $_POST['privacy_consent'] ) ) {
			wp_send_json_error( [ 'message' => 'Bitte stimme der Datenschutzerklärung zu.' ], 422 );
		}

		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );
		if ( ! $barcode ) {
			wp_send_json_error( [ 'message' => 'Kein Barcode angegeben.' ], 400 );
		}

		// Duplicate pending check
		if ( self::has_pending_submission( $barcode ) ) {
			wp_send_json_error( [ 'message' => 'Dieses Produkt wurde bereits eingereicht und wird geprüft.' ], 409 );
		}

		$product_photo_url     = '';
		$ingredients_photo_url = '';

		if ( ! empty( $_FILES['product_photo']['name'] ) ) {
			$url = self::handle_upload( 'product_photo', $settings );
			if ( is_wp_error( $url ) ) {
				wp_send_json_error( [ 'message' => $url->get_error_message() ], 422 );
			}
			$product_photo_url = $url;
		}

		if ( ! empty( $_FILES['ingredients_photo']['name'] ) ) {
			$url = self::handle_upload( 'ingredients_photo', $settings );
			if ( is_wp_error( $url ) ) {
				wp_send_json_error( [ 'message' => $url->get_error_message() ], 422 );
			}
			$ingredients_photo_url = $url;
		}

		$submission_id = NazarenerScan_Database::add_submission( [
			'barcode'               => $barcode,
			'product_name'          => sanitize_text_field( $_POST['product_name'] ?? '' ),
			'product_photo_url'     => $product_photo_url,
			'ingredients_photo_url' => $ingredients_photo_url,
			'ingredients_text'      => sanitize_textarea_field( $_POST['ingredients_text'] ?? '' ),
			'submitter_name'        => sanitize_text_field( $_POST['submitter_name'] ?? '' ),
			'submitter_email'       => sanitize_email( $_POST['submitter_email'] ?? '' ),
			'submitter_ip'          => $ip,
		] );

		if ( ! $submission_id ) {
			wp_send_json_error( [ 'message' => 'Einreichung konnte nicht gespeichert werden.' ], 500 );
		}

		$sub = NazarenerScan_Database::get_submission( $submission_id );

		// Email to submitter
		if ( $sub && $sub->submitter_email ) {
			NazarenerScan_Mailer::submission_received( $sub );
		}

		// Email to admin
		if ( $sub && ! empty( $settings['notify_email'] ) ) {
			NazarenerScan_Mailer::admin_new_submission( $settings['notify_email'], $sub );
		}

		wp_send_json_success( [
			'message'       => 'Danke! Das Produkt wurde zur Prüfung eingereicht.',
			'submission_id' => $submission_id,
		] );
	}

	// ── Ingredient hints ──────────────────────────────────────────────────────

	public function analyze_hints(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );

		$settings = get_option( 'nazarener_scan_settings', [] );
		if ( empty( $settings['show_hints'] ) ) {
			wp_send_json_success( [ 'hints' => [] ] );
		}

		$ip = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'hints', $ip, 120, 60 ) ) {
			wp_send_json_success( [ 'hints' => [] ] ); // Fail silently for hints
		}

		$text  = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
		$hints = NazarenerScan_Hints::analyze( $text );
		wp_send_json_success( [ 'hints' => $hints ] );
	}

	// ── Notify me ─────────────────────────────────────────────────────────────

	public function notify_me(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );

		$ip = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'notify_me', $ip, 10, 3600 ) ) {
			wp_send_json_error( [ 'message' => 'Zu viele Anfragen.' ], 429 );
		}

		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( ! $barcode || ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => 'Barcode und gültige E-Mail erforderlich.' ], 400 );
		}

		$added = NazarenerScan_Database::add_notify_request( $barcode, $email );
		wp_send_json_success( [
			'message' => $added
				? 'Du wirst benachrichtigt, sobald das Produkt geprüft wurde.'
				: 'Diese E-Mail ist bereits für dieses Produkt registriert.',
		] );
	}

	// ── Fresh captcha ─────────────────────────────────────────────────────────

	public function captcha_new(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );
		wp_send_json_success( NazarenerScan_Captcha::generate() );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function has_pending_submission( string $barcode ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}nazarener_submissions WHERE barcode = %s AND status = 'pending' LIMIT 1",
			$barcode
		) );
	}

	private static function handle_upload( string $field, array $settings ): string|\WP_Error {
		$max_mb   = (int) ( $settings['max_upload_mb'] ?? 5 );
		$max_size = $max_mb * 1024 * 1024;

		if ( $_FILES[ $field ]['size'] > $max_size ) {
			return new \WP_Error( 'file_too_large', "Datei zu groß (max. {$max_mb} MB)." );
		}

		// Use WP's own file type check (not deprecated mime_content_type)
		$check = wp_check_filetype_and_ext(
			$_FILES[ $field ]['tmp_name'],
			$_FILES[ $field ]['name']
		);

		$allowed = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
		if ( ! in_array( $check['type'], $allowed, true ) ) {
			return new \WP_Error( 'invalid_type', 'Nur Bilder (JPG, PNG, WebP) erlaubt.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( $field, 0 );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return wp_get_attachment_url( $attachment_id ) ?: '';
	}
}
