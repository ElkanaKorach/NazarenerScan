<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Admin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',            [ $this, 'handle_actions' ] );
		add_filter( 'set-screen-option',     [ $this, 'save_screen_options' ], 10, 3 );
	}

	public function register_menus(): void {
		$pending = NazarenerScan_Database::count_submissions( [ 'status' => 'pending' ] );
		$bubble  = $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';

		add_menu_page(
			'NazarenerScan',
			'NazarenerScan',
			'manage_options',
			'nazarener-scan',
			[ $this, 'page_dashboard' ],
			'dashicons-visibility',
			56
		);

		add_submenu_page(
			'nazarener-scan',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'nazarener-scan',
			[ $this, 'page_dashboard' ]
		);

		add_submenu_page(
			'nazarener-scan',
			'Produkte',
			'Produkte',
			'manage_options',
			'nazarener-products',
			[ $this, 'page_products' ]
		);

		$sub_page = add_submenu_page(
			'nazarener-scan',
			'Einreichungen',
			'Einreichungen' . $bubble,
			'manage_options',
			'nazarener-submissions',
			[ $this, 'page_submissions' ]
		);

		add_action( "load-$sub_page", [ $this, 'submissions_screen_options' ] );

		add_submenu_page(
			'nazarener-scan',
			'Einstellungen',
			'Einstellungen',
			'manage_options',
			'nazarener-settings',
			[ $this, 'page_settings' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'nazarener' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'nazarener-admin',
			NAZARENER_SCAN_URL . 'assets/css/admin.css',
			[],
			NAZARENER_SCAN_VERSION
		);

		wp_enqueue_script(
			'nazarener-admin',
			NAZARENER_SCAN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			NAZARENER_SCAN_VERSION,
			true
		);

		wp_localize_script( 'nazarener-admin', 'NazarenerAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'nazarener_admin' ),
		] );
	}

	public function submissions_screen_options(): void {
		add_screen_option( 'per_page', [
			'label'   => 'Einreichungen pro Seite',
			'default' => 20,
			'option'  => 'nazarener_submissions_per_page',
		] );
	}

	public function save_screen_options( mixed $status, string $option, mixed $value ): mixed {
		if ( $option === 'nazarener_submissions_per_page' ) {
			return (int) $value;
		}
		return $status;
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function page_dashboard(): void {
		include NAZARENER_SCAN_PATH . 'admin/views/dashboard.php';
	}

	public function page_products(): void {
		$action = sanitize_text_field( $_GET['action'] ?? $_POST['action'] ?? '' );
		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			include NAZARENER_SCAN_PATH . 'admin/views/product-edit.php';
			return;
		}
		include NAZARENER_SCAN_PATH . 'admin/views/products.php';
	}

	public function page_submissions(): void {
		$action = sanitize_text_field( $_GET['action'] ?? '' );
		if ( $action === 'review' ) {
			include NAZARENER_SCAN_PATH . 'admin/views/submission-review.php';
			return;
		}
		include NAZARENER_SCAN_PATH . 'admin/views/submissions.php';
	}

	public function page_settings(): void {
		include NAZARENER_SCAN_PATH . 'admin/views/settings.php';
	}

	// ── Action handlers ───────────────────────────────────────────────────────

	public function handle_actions(): void {
		$page = sanitize_text_field( $_GET['page'] ?? $_POST['page'] ?? '' );
		if ( ! str_starts_with( $page, 'nazarener' ) ) {
			return;
		}

		// ── Products ──
		if ( isset( $_POST['nazarener_save_product'] ) ) {
			$this->save_product();
		}

		if ( isset( $_POST['nazarener_delete_product'] ) ) {
			$this->delete_product();
		}

		if ( isset( $_POST['bulk_action_top'] ) || isset( $_POST['bulk_action_bottom'] ) ) {
			$bulk = sanitize_text_field( $_POST['bulk_action_top'] ?? $_POST['bulk_action_bottom'] ?? '' );
			$this->handle_bulk_products( $bulk );
		}

		// ── CSV Export ──
		if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' && $page === 'nazarener-products' ) {
			check_admin_referer( 'nazarener_export_csv' );
			NazarenerScan_Database::export_products_csv();
		}

		// ── CSV Import ──
		if ( isset( $_POST['nazarener_import_csv'] ) ) {
			$this->import_csv();
		}

		// ── Submissions ──
		if ( isset( $_POST['nazarener_review_submission'] ) ) {
			$this->review_submission();
		}

		if ( isset( $_POST['nazarener_delete_submission'] ) ) {
			$this->delete_submission();
		}

		// ── Settings ──
		if ( isset( $_POST['nazarener_save_settings'] ) ) {
			$this->save_settings();
		}
	}

	// ── Product CRUD ──────────────────────────────────────────────────────────

	private function save_product(): void {
		check_admin_referer( 'nazarener_product_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$id = NazarenerScan_Database::upsert_product( [
			'barcode'               => $_POST['barcode']               ?? '',
			'name'                  => $_POST['name']                  ?? '',
			'brand'                 => $_POST['brand']                 ?? '',
			'status'                => $_POST['status']                ?? 'green',
			'forbidden_ingredients' => $_POST['forbidden_ingredients'] ?? '',
			'notes'                 => $_POST['notes']                 ?? '',
		] );

		$redirect = admin_url( 'admin.php?page=nazarener-products&saved=1' );
		if ( $id ) {
			$redirect = add_query_arg( 'product_id', $id, $redirect );
		}
		wp_redirect( $redirect );
		exit;
	}

	private function delete_product(): void {
		check_admin_referer( 'nazarener_delete_product' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		NazarenerScan_Database::delete_product( (int) ( $_POST['product_id'] ?? 0 ) );
		wp_redirect( admin_url( 'admin.php?page=nazarener-products&deleted=1' ) );
		exit;
	}

	private function handle_bulk_products( string $action ): void {
		if ( ! in_array( $action, [ 'delete', 'set_green', 'set_red' ], true ) ) {
			return;
		}
		check_admin_referer( 'bulk-products' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$ids = array_map( 'intval', (array) ( $_POST['product_ids'] ?? [] ) );
		if ( ! $ids ) {
			return;
		}

		foreach ( $ids as $id ) {
			$product = NazarenerScan_Database::get_product( $id );
			if ( ! $product ) {
				continue;
			}
			if ( $action === 'delete' ) {
				NazarenerScan_Database::delete_product( $id );
			} else {
				NazarenerScan_Database::upsert_product( [
					'barcode' => $product->barcode,
					'name'    => $product->name,
					'brand'   => $product->brand,
					'status'  => $action === 'set_green' ? 'green' : 'red',
				] );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=nazarener-products&bulk_done=1' ) );
		exit;
	}

	private function import_csv(): void {
		check_admin_referer( 'nazarener_import_csv' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_redirect( admin_url( 'admin.php?page=nazarener-products&import_error=1' ) );
			exit;
		}

		$handle  = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
		$header  = fgetcsv( $handle, 0, ';' );
		$count   = 0;

		while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
			if ( count( $row ) < 4 ) {
				continue;
			}
			NazarenerScan_Database::upsert_product( [
				'barcode'               => $row[0] ?? '',
				'name'                  => $row[1] ?? '',
				'brand'                 => $row[2] ?? '',
				'status'                => $row[3] ?? 'green',
				'forbidden_ingredients' => $row[4] ?? '',
				'notes'                 => $row[5] ?? '',
			] );
			$count++;
		}
		fclose( $handle );

		wp_redirect( admin_url( "admin.php?page=nazarener-products&imported=$count" ) );
		exit;
	}

	// ── Submission review ─────────────────────────────────────────────────────

	private function review_submission(): void {
		check_admin_referer( 'nazarener_review_submission' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$id     = (int) ( $_POST['submission_id'] ?? 0 );
		$action = sanitize_text_field( $_POST['review_action'] ?? '' );
		$notes  = sanitize_textarea_field( $_POST['reviewer_notes'] ?? '' );

		NazarenerScan_Database::review_submission( $id, $action, $notes );

		// If approved, auto-create the product
		if ( $action === 'approved' || $action === 'approved_green' || $action === 'approved_red' ) {
			$sub = NazarenerScan_Database::get_submission( $id );
			if ( $sub ) {
				NazarenerScan_Database::upsert_product( [
					'barcode' => $sub->barcode,
					'name'    => $sub->product_name ?: $sub->barcode,
					'brand'   => '',
					'status'  => str_contains( $action, 'red' ) ? 'red' : 'green',
					'forbidden_ingredients' => '',
					'notes'   => $notes,
				] );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=nazarener-submissions&reviewed=1' ) );
		exit;
	}

	private function delete_submission(): void {
		check_admin_referer( 'nazarener_delete_submission' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		NazarenerScan_Database::delete_submission( (int) ( $_POST['submission_id'] ?? 0 ) );
		wp_redirect( admin_url( 'admin.php?page=nazarener-submissions&deleted=1' ) );
		exit;
	}

	// ── Settings ──────────────────────────────────────────────────────────────

	private function save_settings(): void {
		check_admin_referer( 'nazarener_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		update_option( 'nazarener_scan_settings', [
			'require_login'   => ! empty( $_POST['require_login'] ),
			'notify_email'    => sanitize_email( $_POST['notify_email'] ?? '' ),
			'max_upload_mb'   => max( 1, min( 20, (int) ( $_POST['max_upload_mb'] ?? 5 ) ) ),
			'show_hints'      => ! empty( $_POST['show_hints'] ),
			'scanner_height'  => sanitize_text_field( $_POST['scanner_height'] ?? '400px' ),
		] );

		wp_redirect( admin_url( 'admin.php?page=nazarener-settings&saved=1' ) );
		exit;
	}
}
