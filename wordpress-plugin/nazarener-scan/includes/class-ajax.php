<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Ajax {

	public function __construct() {
		// Both logged-in and guest users can look up and submit
		add_action( 'wp_ajax_nazarener_lookup',           [ $this, 'lookup' ] );
		add_action( 'wp_ajax_nopriv_nazarener_lookup',    [ $this, 'lookup' ] );
		add_action( 'wp_ajax_nazarener_submit',           [ $this, 'submit' ] );
		add_action( 'wp_ajax_nopriv_nazarener_submit',    [ $this, 'submit' ] );
		add_action( 'wp_ajax_nazarener_analyze_hints',        [ $this, 'analyze_hints' ] );
		add_action( 'wp_ajax_nopriv_nazarener_analyze_hints', [ $this, 'analyze_hints' ] );
	}

	public function lookup(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );

		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );
		if ( ! $barcode ) {
			wp_send_json_error( [ 'message' => 'Kein Barcode angegeben.' ], 400 );
		}

		$product = NazarenerScan_Database::get_product_by_barcode( $barcode );

		if ( ! $product ) {
			// Yellow: not in system
			$already_submitted = (bool) NazarenerScan_Database::count_submissions( [
				'search' => $barcode,
				'status' => 'pending',
			] );

			wp_send_json_success( [
				'status'            => 'yellow',
				'already_submitted' => $already_submitted,
			] );
			return;
		}

		wp_send_json_success( [
			'status'                 => $product->status,
			'name'                   => esc_html( $product->name ),
			'brand'                  => esc_html( $product->brand ),
			'forbidden_ingredients'  => esc_html( $product->forbidden_ingredients ?? '' ),
			'notes'                  => esc_html( $product->notes ?? '' ),
			'reviewed_at'            => $product->reviewed_at,
		] );
	}

	public function submit(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );

		$settings = get_option( 'nazarener_scan_settings', [] );

		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Einreichung nur für angemeldete Nutzer.' ], 403 );
		}

		$barcode = sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) );
		if ( ! $barcode ) {
			wp_send_json_error( [ 'message' => 'Kein Barcode angegeben.' ], 400 );
		}

		// Prevent duplicate pending submissions for same barcode
		$pending = NazarenerScan_Database::get_submissions( [
			'status' => 'pending',
			'search' => $barcode,
		] );
		foreach ( $pending as $sub ) {
			if ( $sub->barcode === $barcode ) {
				wp_send_json_error( [ 'message' => 'Dieses Produkt wurde bereits eingereicht und wird geprüft.' ], 409 );
			}
		}

		$product_photo_url     = '';
		$ingredients_photo_url = '';

		// Handle product photo upload
		if ( ! empty( $_FILES['product_photo']['name'] ) ) {
			$url = self::handle_upload( 'product_photo', $settings );
			if ( is_wp_error( $url ) ) {
				wp_send_json_error( [ 'message' => $url->get_error_message() ], 422 );
			}
			$product_photo_url = $url;
		}

		// Handle ingredients photo upload
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
			'submitter_ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
		] );

		if ( ! $submission_id ) {
			wp_send_json_error( [ 'message' => 'Einreichung konnte nicht gespeichert werden.' ], 500 );
		}

		// Notify admin
		if ( ! empty( $settings['notify_email'] ) ) {
			$admin_url = admin_url( 'admin.php?page=nazarener-submissions&action=review&id=' . $submission_id );
			wp_mail(
				$settings['notify_email'],
				'[NazarenerScan] Neues Produkt eingereicht',
				"Barcode: $barcode\n\nZur Prüfung: $admin_url"
			);
		}

		wp_send_json_success( [ 'message' => 'Danke! Das Produkt wurde zur Prüfung eingereicht.' ] );
	}

	public function analyze_hints(): void {
		check_ajax_referer( 'nazarener_frontend', 'nonce' );
		$text  = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
		$hints = NazarenerScan_Hints::analyze( $text );
		wp_send_json_success( [ 'hints' => $hints ] );
	}

	private static function handle_upload( string $field, array $settings ): string|\WP_Error {
		$max_mb   = (int) ( $settings['max_upload_mb'] ?? 5 );
		$max_size = $max_mb * 1024 * 1024;

		if ( $_FILES[ $field ]['size'] > $max_size ) {
			return new \WP_Error( 'file_too_large', "Datei zu groß (max. {$max_mb} MB)." );
		}

		$mime = mime_content_type( $_FILES[ $field ]['tmp_name'] );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ], true ) ) {
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
