<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API – /wp-json/nazarener/v1/
 *
 * Public endpoints (read-only, no auth required):
 *   GET /product/{barcode}         – look up a single product
 *   GET /products                  – list all products (paged)
 *   GET /stats                     – public counts
 *
 * Protected endpoints (require nazarener_review capability or JWT/Application Password):
 *   POST /submit                   – submit a product for review
 *   GET  /submission/status/{id}   – submitter checks own submission
 */
class NazarenerScan_REST_API {

	private const NS = 'nazarener/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/product/(?P<barcode>[^/]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_product' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'barcode' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );

		register_rest_route( self::NS, '/products', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_products' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'status'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'search'   => [ 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
				'page'     => [ 'default' => 1,  'sanitize_callback' => 'absint' ],
			],
		] );

		register_rest_route( self::NS, '/stats', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/submit', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'submit_product' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/notify', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'notify_request' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/submission/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_submission_status' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id'    => [ 'required' => true, 'sanitize_callback' => 'absint' ],
				'email' => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
			],
		] );
	}

	// ── Product lookup ────────────────────────────────────────────────────────

	public function get_product( \WP_REST_Request $request ): \WP_REST_Response {
		$ip     = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'api_lookup', $ip, 60, 60 ) ) {
			return new \WP_REST_Response( [ 'error' => 'Too many requests.' ], 429 );
		}

		$barcode = urldecode( $request->get_param( 'barcode' ) );
		$product = NazarenerScan_Database::get_product_by_barcode( $barcode );

		if ( ! $product ) {
			NazarenerScan_Database::log_scan( $barcode, 'yellow' );
			return new \WP_REST_Response( [
				'status'  => 'yellow',
				'barcode' => $barcode,
				'message' => 'Produkt nicht in der Datenbank.',
			], 200 );
		}

		NazarenerScan_Database::log_scan( $barcode, $product->status );

		return new \WP_REST_Response( self::format_product( $product ), 200 );
	}

	public function list_products( \WP_REST_Request $request ): \WP_REST_Response {
		$ip       = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'api_list', $ip, 30, 60 ) ) {
			return new \WP_REST_Response( [ 'error' => 'Too many requests.' ], 429 );
		}

		$per_page = min( 100, (int) $request->get_param( 'per_page' ) );
		$args     = [
			'status'   => $request->get_param( 'status' ),
			'search'   => $request->get_param( 'search' ),
			'per_page' => $per_page,
			'paged'    => (int) $request->get_param( 'page' ),
		];

		$products = NazarenerScan_Database::get_products( $args );
		$total    = NazarenerScan_Database::count_products( $args );

		$response = new \WP_REST_Response( [
			'products'   => array_map( [ self::class, 'format_product' ], $products ),
			'total'      => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'       => (int) $request->get_param( 'page' ),
		], 200 );

		$response->header( 'X-Total-Count', $total );
		return $response;
	}

	public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$stats = NazarenerScan_Database::get_stats();
		// Only expose public-safe fields
		return new \WP_REST_Response( [
			'products_total' => $stats['products_total'],
			'products_green' => $stats['products_green'],
			'products_red'   => $stats['products_red'],
			'scans_today'    => $stats['scans_today'],
			'scans_week'     => $stats['scans_week'],
		], 200 );
	}

	// ── Submission ────────────────────────────────────────────────────────────

	public function submit_product( \WP_REST_Request $request ): \WP_REST_Response {
		$ip = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'api_submit', $ip, 3, 3600 ) ) {
			return new \WP_REST_Response( [ 'error' => 'Zu viele Einreichungen. Bitte später erneut versuchen.' ], 429 );
		}

		$settings = get_option( 'nazarener_scan_settings', [] );
		if ( ! empty( $settings['require_login'] ) && ! is_user_logged_in() ) {
			return new \WP_REST_Response( [ 'error' => 'Anmeldung erforderlich.' ], 401 );
		}

		$barcode = sanitize_text_field( $request->get_param( 'barcode' ) ?? '' );
		if ( ! $barcode ) {
			return new \WP_REST_Response( [ 'error' => 'Barcode fehlt.' ], 400 );
		}

		// Duplicate check
		$pending = NazarenerScan_Database::get_submissions( [ 'status' => 'pending' ] );
		foreach ( $pending as $sub ) {
			if ( $sub->barcode === $barcode ) {
				return new \WP_REST_Response( [ 'error' => 'Bereits eingereicht und in Prüfung.' ], 409 );
			}
		}

		$id = NazarenerScan_Database::add_submission( [
			'barcode'          => $barcode,
			'product_name'     => sanitize_text_field( $request->get_param( 'product_name' ) ?? '' ),
			'ingredients_text' => sanitize_textarea_field( $request->get_param( 'ingredients_text' ) ?? '' ),
			'submitter_name'   => sanitize_text_field( $request->get_param( 'submitter_name' ) ?? '' ),
			'submitter_email'  => sanitize_email( $request->get_param( 'submitter_email' ) ?? '' ),
			'submitter_ip'     => $ip,
		] );

		if ( ! $id ) {
			return new \WP_REST_Response( [ 'error' => 'Speicherfehler.' ], 500 );
		}

		$sub = NazarenerScan_Database::get_submission( $id );
		if ( $sub && $sub->submitter_email ) {
			NazarenerScan_Mailer::submission_received( $sub );
		}

		return new \WP_REST_Response( [ 'id' => $id, 'message' => 'Einreichung gespeichert.' ], 201 );
	}

	public function notify_request( \WP_REST_Request $request ): \WP_REST_Response {
		$ip = NazarenerScan_RateLimiter::get_client_ip();
		if ( ! NazarenerScan_RateLimiter::allow( 'api_notify', $ip, 10, 3600 ) ) {
			return new \WP_REST_Response( [ 'error' => 'Zu viele Anfragen.' ], 429 );
		}

		$barcode = sanitize_text_field( $request->get_param( 'barcode' ) ?? '' );
		$email   = sanitize_email( $request->get_param( 'email' ) ?? '' );

		if ( ! $barcode || ! is_email( $email ) ) {
			return new \WP_REST_Response( [ 'error' => 'Barcode und gültige E-Mail erforderlich.' ], 400 );
		}

		$added = NazarenerScan_Database::add_notify_request( $barcode, $email );
		return new \WP_REST_Response( [
			'success' => true,
			'message' => $added ? 'Du wirst benachrichtigt, sobald das Produkt geprüft wurde.' : 'E-Mail bereits registriert.',
		], 200 );
	}

	public function get_submission_status( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$email = sanitize_email( $request->get_param( 'email' ) );
		$sub   = NazarenerScan_Database::get_submission( $id );

		if ( ! $sub || $sub->submitter_email !== $email ) {
			return new \WP_REST_Response( [ 'error' => 'Nicht gefunden.' ], 404 );
		}

		return new \WP_REST_Response( [
			'id'          => $sub->id,
			'barcode'     => $sub->barcode,
			'status'      => $sub->status,
			'submitted'   => $sub->created_at,
			'reviewed_at' => $sub->reviewed_at,
		], 200 );
	}

	// ── Formatter ─────────────────────────────────────────────────────────────

	private static function format_product( object $p ): array {
		return [
			'barcode'               => $p->barcode,
			'name'                  => $p->name,
			'brand'                 => $p->brand,
			'status'                => $p->status,
			'forbidden_ingredients' => $p->forbidden_ingredients ?? null,
			'biblical_reference'    => $p->biblical_reference ?? null,
			'notes'                 => $p->notes ?? null,
			'reviewed_at'           => $p->reviewed_at,
		];
	}
}
