<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Frontend {

	public function __construct() {
		add_shortcode( 'nazarener_scanner', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets(): void {
		if ( ! $this->page_has_shortcode() ) {
			return;
		}

		// html5-qrcode barcode scanning library (MIT license)
		wp_enqueue_script(
			'html5-qrcode',
			'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
			[],
			'2.3.8',
			true
		);

		wp_enqueue_style(
			'nazarener-scan-frontend',
			NAZARENER_SCAN_URL . 'assets/css/frontend.css',
			[],
			NAZARENER_SCAN_VERSION
		);

		wp_enqueue_script(
			'nazarener-scan-frontend',
			NAZARENER_SCAN_URL . 'assets/js/frontend.js',
			[ 'html5-qrcode', 'jquery' ],
			NAZARENER_SCAN_VERSION,
			true
		);

		$settings = get_option( 'nazarener_scan_settings', [] );

		wp_localize_script( 'nazarener-scan-frontend', 'NazarenerScan', [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'nazarener_frontend' ),
			'isLoggedIn'   => is_user_logged_in(),
			'requireLogin' => ! empty( $settings['require_login'] ),
			'maxUploadMb'  => (int) ( $settings['max_upload_mb'] ?? 5 ),
			'i18n'         => [
				'scanning'         => 'Kamera wird gestartet…',
				'scanned'          => 'Barcode erkannt!',
				'searching'        => 'Suche in Datenbank…',
				'notFound'         => 'Produkt nicht im System.',
				'errorCamera'      => 'Kein Kamerazugriff. Bitte Barcode manuell eingeben.',
				'submitSuccess'    => 'Danke! Einreichung wird geprüft.',
				'submitError'      => 'Fehler beim Einreichen. Bitte erneut versuchen.',
				'alreadySubmitted' => 'Dieses Produkt wurde bereits eingereicht und wird geprüft.',
				'fillRequired'     => 'Bitte Barcode bestätigen und mindestens ein Foto oder den Produktnamen angeben.',
			],
		] );
	}

	public function render_shortcode( array $atts ): string {
		$atts = shortcode_atts( [
			'height' => '600px',
			'width'  => '100%',
		], $atts );

		ob_start();
		include NAZARENER_SCAN_PATH . 'templates/scanner.php';
		return ob_get_clean();
	}

	private function page_has_shortcode(): bool {
		global $post;
		return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'nazarener_scanner' );
	}
}
