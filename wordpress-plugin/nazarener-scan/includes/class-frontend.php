<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Frontend {

	public function __construct() {
		add_shortcode( 'nazarener_scanner',     [ $this, 'render_scanner' ] );
		add_shortcode( 'nazarener_produktliste', [ $this, 'render_product_list' ] );
		add_shortcode( 'nazarener_stats',        [ $this, 'render_stats' ] );
		add_shortcode( 'nazarener_status',       [ $this, 'render_submission_status' ] );
		add_action( 'wp_enqueue_scripts',        [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets(): void {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) ) return;

		$has_scanner  = has_shortcode( $post->post_content, 'nazarener_scanner' );
		$has_list     = has_shortcode( $post->post_content, 'nazarener_produktliste' );
		$has_status   = has_shortcode( $post->post_content, 'nazarener_status' );

		wp_enqueue_style(
			'nazarener-scan-frontend',
			NAZARENER_SCAN_URL . 'assets/css/frontend.css',
			[], NAZARENER_SCAN_VERSION
		);

		if ( $has_scanner ) {
			wp_enqueue_script(
				'html5-qrcode',
				'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
				[], '2.3.8', true
			);
		}

		if ( $has_scanner || $has_list || $has_status ) {
			wp_enqueue_script(
				'nazarener-scan-frontend',
				NAZARENER_SCAN_URL . 'assets/js/frontend.js',
				[ 'jquery' ], NAZARENER_SCAN_VERSION, true
			);
			$this->localize_script( $has_scanner );
		}
	}

	private function localize_script( bool $with_scanner ): void {
		$settings = get_option( 'nazarener_scan_settings', [] );
		wp_localize_script( 'nazarener-scan-frontend', 'NazarenerScan', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'nazarener_frontend' ),
			'isLoggedIn'    => is_user_logged_in(),
			'requireLogin'  => ! empty( $settings['require_login'] ),
			'maxUploadMb'   => (int) ( $settings['max_upload_mb'] ?? 5 ),
			'showHints'     => ! empty( $settings['show_hints'] ),
			'privacyUrl'    => esc_url( get_privacy_policy_url() ),
			'i18n'          => [
				'errorCamera'      => 'Kein Kamerazugriff. Bitte Barcode manuell eingeben.',
				'searching'        => 'Suche in Datenbank…',
				'submitSuccess'    => 'Danke! Einreichung wird geprüft.',
				'submitError'      => 'Fehler beim Einreichen. Bitte erneut versuchen.',
				'alreadySubmitted' => 'Dieses Produkt wurde bereits eingereicht.',
				'fillRequired'     => 'Bitte mindestens ein Foto oder den Produktnamen angeben.',
				'offline'          => 'Keine Internetverbindung. Bitte Verbindung prüfen und erneut versuchen.',
				'rateLimited'      => 'Zu viele Anfragen. Bitte kurz warten.',
				'notifySuccess'    => 'Du wirst benachrichtigt, sobald das Produkt geprüft wurde.',
				'notifyError'      => 'Benachrichtigung konnte nicht gespeichert werden.',
				'captchaWrong'     => 'Falsche Antwort. Bitte die Sicherheitsfrage erneut beantworten.',
				'privacyRequired'  => 'Bitte stimme der Datenschutzerklärung zu.',
			],
		] );
	}

	// ── [nazarener_scanner] ───────────────────────────────────────────────────

	public function render_scanner( array $atts ): string {
		$atts = shortcode_atts( [ 'height' => '420px' ], $atts );
		ob_start();
		include NAZARENER_SCAN_PATH . 'templates/scanner.php';
		return ob_get_clean();
	}

	// ── [nazarener_produktliste] ──────────────────────────────────────────────

	public function render_product_list( array $atts ): string {
		$atts = shortcode_atts( [
			'status'   => '',
			'per_page' => 24,
			'search'   => '',
		], $atts );

		$paged    = max( 1, (int) ( $_GET['ns_page'] ?? 1 ) );
		$search   = sanitize_text_field( $_GET['ns_search'] ?? $atts['search'] );
		$status   = sanitize_text_field( $_GET['ns_status'] ?? $atts['status'] );
		$per_page = max( 1, min( 100, (int) $atts['per_page'] ) );

		$args     = [ 'status' => $status, 'search' => $search, 'per_page' => $per_page, 'paged' => $paged ];
		$products = NazarenerScan_Database::get_products( $args );
		$total    = NazarenerScan_Database::count_products( $args );
		$pages    = (int) ceil( $total / $per_page );

		ob_start();
		include NAZARENER_SCAN_PATH . 'templates/product-list.php';
		return ob_get_clean();
	}

	// ── [nazarener_stats] ─────────────────────────────────────────────────────

	public function render_stats( array $atts ): string {
		$stats = NazarenerScan_Database::get_stats();
		ob_start();
		?>
		<div class="ns-stats-widget">
			<div class="ns-stat-pill ns-stat-pill--green">
				<span class="ns-stat-pill__num"><?= esc_html( $stats['products_green'] ) ?></span>
				<span class="ns-stat-pill__label">🟢 Erlaubt</span>
			</div>
			<div class="ns-stat-pill ns-stat-pill--red">
				<span class="ns-stat-pill__num"><?= esc_html( $stats['products_red'] ) ?></span>
				<span class="ns-stat-pill__label">🔴 Nicht erlaubt</span>
			</div>
			<div class="ns-stat-pill">
				<span class="ns-stat-pill__num"><?= esc_html( $stats['products_total'] ) ?></span>
				<span class="ns-stat-pill__label">Produkte geprüft</span>
			</div>
			<div class="ns-stat-pill">
				<span class="ns-stat-pill__num"><?= esc_html( $stats['scans_week'] ) ?></span>
				<span class="ns-stat-pill__label">Scans diese Woche</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── [nazarener_status] ────────────────────────────────────────────────────

	public function render_submission_status( array $atts ): string {
		ob_start();
		include NAZARENER_SCAN_PATH . 'templates/submission-status.php';
		return ob_get_clean();
	}
}
