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
		$cap     = current_user_can( 'manage_options' ) ? 'manage_options' : 'nazarener_review';
		$pending = NazarenerScan_Database::count_submissions( [ 'status' => 'pending' ] );
		$bubble  = $pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '';

		add_menu_page( 'NazarenerScan', 'NazarenerScan', $cap, 'nazarener-scan',
			[ $this, 'page_dashboard' ], 'dashicons-visibility', 56 );

		add_submenu_page( 'nazarener-scan', 'Dashboard',   'Dashboard',   $cap, 'nazarener-scan',        [ $this, 'page_dashboard' ] );
		add_submenu_page( 'nazarener-scan', 'Produkte',    'Produkte',    $cap, 'nazarener-products',     [ $this, 'page_products' ] );

		$sub_page = add_submenu_page( 'nazarener-scan', 'Einreichungen', 'Einreichungen' . $bubble, $cap, 'nazarener-submissions', [ $this, 'page_submissions' ] );
		add_action( "load-$sub_page", [ $this, 'submissions_screen_options' ] );

		add_submenu_page( 'nazarener-scan', 'Aktivitätslog', 'Aktivitätslog', $cap, 'nazarener-log',      [ $this, 'page_log' ] );
		add_submenu_page( 'nazarener-scan', 'Analysen',      'Analysen',      $cap, 'nazarener-analytics', [ $this, 'page_analytics' ] );

		if ( current_user_can( 'manage_options' ) ) {
			add_submenu_page( 'nazarener-scan', 'Einstellungen', 'Einstellungen', 'manage_options', 'nazarener-settings', [ $this, 'page_settings' ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'nazarener' ) === false ) return;

		wp_enqueue_style(  'nazarener-admin', NAZARENER_SCAN_URL . 'assets/css/admin.css',  [], NAZARENER_SCAN_VERSION );
		wp_enqueue_script( 'nazarener-admin', NAZARENER_SCAN_URL . 'assets/js/admin.js', [ 'jquery' ], NAZARENER_SCAN_VERSION, true );

		// Media uploader for product images
		if ( strpos( $hook, 'nazarener-products' ) !== false ) {
			wp_enqueue_media();
		}

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
		return $option === 'nazarener_submissions_per_page' ? (int) $value : $status;
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function page_dashboard():   void { include NAZARENER_SCAN_PATH . 'admin/views/dashboard.php'; }
	public function page_log():         void { include NAZARENER_SCAN_PATH . 'admin/views/activity-log.php'; }
	public function page_analytics():   void { include NAZARENER_SCAN_PATH . 'admin/views/analytics.php'; }

	public function page_products(): void {
		$action = sanitize_text_field( $_GET['action'] ?? $_POST['action'] ?? '' );
		include NAZARENER_SCAN_PATH . 'admin/views/' . ( in_array( $action, [ 'new','edit' ], true ) ? 'product-edit' : 'products' ) . '.php';
	}

	public function page_submissions(): void {
		$action = sanitize_text_field( $_GET['action'] ?? '' );
		include NAZARENER_SCAN_PATH . 'admin/views/' . ( $action === 'review' ? 'submission-review' : 'submissions' ) . '.php';
	}

	public function page_settings(): void { include NAZARENER_SCAN_PATH . 'admin/views/settings.php'; }

	// ── Action dispatcher ─────────────────────────────────────────────────────

	public function handle_actions(): void {
		$page = sanitize_text_field( $_GET['page'] ?? $_POST['page'] ?? '' );
		if ( ! str_starts_with( $page, 'nazarener' ) ) return;

		match ( true ) {
			isset( $_POST['nazarener_save_product'] )         => $this->save_product(),
			isset( $_POST['nazarener_delete_product'] )        => $this->delete_product(),
			isset( $_POST['nazarener_review_submission'] )     => $this->review_submission(),
			isset( $_POST['nazarener_delete_submission'] )     => $this->delete_submission(),
			isset( $_POST['nazarener_bulk_review'] )           => $this->bulk_review(),
			isset( $_POST['nazarener_import_csv'] )            => $this->import_csv(),
			isset( $_POST['nazarener_save_settings'] )         => $this->save_settings(),
			isset( $_GET['export'] ) && $_GET['export'] === 'csv' && $page === 'nazarener-products'
				=> $this->export_csv(),
			default => null,
		};

		if ( isset( $_POST['bulk_action_top'] ) || isset( $_POST['bulk_action_bottom'] ) ) {
			$bulk = sanitize_text_field( $_POST['bulk_action_top'] ?? $_POST['bulk_action_bottom'] ?? '' );
			$this->handle_bulk_products( $bulk );
		}
	}

	// ── Products ──────────────────────────────────────────────────────────────

	private function save_product(): void {
		check_admin_referer( 'nazarener_product_nonce' );
		$this->require_cap( 'nazarener_review' );

		// Parse extra barcodes
		$extra = [];
		$raw   = sanitize_textarea_field( $_POST['extra_barcodes'] ?? '' );
		foreach ( preg_split( '/[\r\n,]+/', $raw ) as $line ) {
			$line = trim( $line );
			if ( $line ) $extra[] = $line;
		}

		$id = NazarenerScan_Database::upsert_product( [
			'id'                    => (int) ( $_POST['product_id'] ?? 0 ),
			'barcode'               => $_POST['barcode']               ?? '',
			'name'                  => $_POST['name']                  ?? '',
			'brand'                 => $_POST['brand']                 ?? '',
			'status'                => $_POST['status']                ?? 'green',
			'forbidden_ingredients' => $_POST['forbidden_ingredients'] ?? '',
			'biblical_reference'    => $_POST['biblical_reference']    ?? '',
			'notes'                 => $_POST['notes']                 ?? '',
			'product_image_id'      => (int) ( $_POST['product_image_id'] ?? 0 ),
			'extra_barcodes'        => $extra,
		] );

		wp_redirect( admin_url( 'admin.php?page=nazarener-products&saved=1' . ( $id ? '&product_id=' . $id : '' ) ) );
		exit;
	}

	private function delete_product(): void {
		check_admin_referer( 'nazarener_delete_product' );
		$this->require_cap( 'nazarener_review' );
		NazarenerScan_Database::delete_product( (int) ( $_POST['product_id'] ?? 0 ) );
		wp_redirect( admin_url( 'admin.php?page=nazarener-products&deleted=1' ) );
		exit;
	}

	private function handle_bulk_products( string $action ): void {
		if ( ! in_array( $action, [ 'delete','set_green','set_red' ], true ) ) return;
		check_admin_referer( 'bulk-products' );
		$this->require_cap( 'nazarener_review' );
		$ids = array_map( 'intval', (array) ( $_POST['product_ids'] ?? [] ) );
		foreach ( $ids as $id ) {
			$product = NazarenerScan_Database::get_product( $id );
			if ( ! $product ) continue;
			if ( $action === 'delete' ) {
				NazarenerScan_Database::delete_product( $id );
			} else {
				NazarenerScan_Database::upsert_product( [
					'id'     => $id,
					'barcode' => $product->barcode,
					'name'   => $product->name,
					'brand'  => $product->brand,
					'status' => $action === 'set_green' ? 'green' : 'red',
				] );
			}
		}
		wp_redirect( admin_url( 'admin.php?page=nazarener-products&bulk_done=1' ) );
		exit;
	}

	private function export_csv(): void {
		check_admin_referer( 'nazarener_export_csv' );
		$this->require_cap( 'nazarener_review' );
		NazarenerScan_Database::export_products_csv();
	}

	private function import_csv(): void {
		check_admin_referer( 'nazarener_import_csv' );
		$this->require_cap( 'manage_options' );
		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_redirect( admin_url( 'admin.php?page=nazarener-products&import_error=1' ) );
			exit;
		}
		$handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
		fgetcsv( $handle, 0, ';' ); // skip header
		$count = 0;
		while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
			if ( count( $row ) < 4 ) continue;
			NazarenerScan_Database::upsert_product( [
				'barcode'               => $row[0] ?? '',
				'name'                  => $row[1] ?? '',
				'brand'                 => $row[2] ?? '',
				'status'                => $row[3] ?? 'green',
				'forbidden_ingredients' => $row[4] ?? '',
				'biblical_reference'    => $row[5] ?? '',
				'notes'                 => $row[6] ?? '',
			] );
			$count++;
		}
		fclose( $handle );
		wp_redirect( admin_url( "admin.php?page=nazarener-products&imported=$count" ) );
		exit;
	}

	// ── Submissions ───────────────────────────────────────────────────────────

	private function review_submission(): void {
		check_admin_referer( 'nazarener_review_submission' );
		$this->require_cap( 'nazarener_review' );

		$id     = (int) ( $_POST['submission_id'] ?? 0 );
		$action = sanitize_text_field( $_POST['review_action'] ?? '' );
		$notes  = sanitize_textarea_field( $_POST['reviewer_notes'] ?? '' );
		$sub    = NazarenerScan_Database::get_submission( $id );

		if ( ! $sub ) {
			wp_redirect( admin_url( 'admin.php?page=nazarener-submissions&error=1' ) );
			exit;
		}

		$db_status = str_contains( $action, 'reject' ) ? 'rejected' : 'approved';
		NazarenerScan_Database::review_submission( $id, $db_status, $notes );

		// Auto-create / update product on approval
		if ( $db_status === 'approved' ) {
			$product_status = str_contains( $action, 'red' ) ? 'red' : 'green';
			NazarenerScan_Database::upsert_product( [
				'barcode'               => $sub->barcode,
				'name'                  => $sub->product_name ?: $sub->barcode,
				'brand'                 => '',
				'status'                => $product_status,
				'forbidden_ingredients' => '',
				'notes'                 => $notes,
			] );

			// Notify "notify me" subscribers
			$notify_list = NazarenerScan_Database::get_notify_requests_for_barcode( $sub->barcode );
			if ( $notify_list ) {
				$product = NazarenerScan_Database::get_product_by_barcode( $sub->barcode );
				foreach ( $notify_list as $req ) {
					NazarenerScan_Mailer::notify_me( $req->email, $sub->barcode, $product );
				}
				NazarenerScan_Database::mark_notify_requests_sent( $sub->barcode );
			}

			// Email submitter
			$updated_sub = NazarenerScan_Database::get_submission( $id );
			if ( $updated_sub && $updated_sub->submitter_email ) {
				if ( $product_status === 'green' ) {
					NazarenerScan_Mailer::submission_approved_green( $updated_sub, $notes );
				} else {
					NazarenerScan_Mailer::submission_approved_red( $updated_sub, $notes );
				}
			}
		} else {
			// Rejected – email submitter
			if ( $sub->submitter_email ) {
				NazarenerScan_Mailer::submission_rejected( $sub, $notes );
			}
		}

		wp_redirect( admin_url( 'admin.php?page=nazarener-submissions&reviewed=1' ) );
		exit;
	}

	private function delete_submission(): void {
		check_admin_referer( 'nazarener_delete_submission' );
		$this->require_cap( 'nazarener_review' );
		NazarenerScan_Database::delete_submission( (int) ( $_POST['submission_id'] ?? 0 ) );
		wp_redirect( admin_url( 'admin.php?page=nazarener-submissions&deleted=1' ) );
		exit;
	}

	private function bulk_review(): void {
		check_admin_referer( 'nazarener_bulk_review' );
		$this->require_cap( 'nazarener_review' );
		$ids    = array_map( 'intval', (array) ( $_POST['submission_ids'] ?? [] ) );
		$action = sanitize_text_field( $_POST['bulk_review_action'] ?? '' );
		foreach ( $ids as $id ) {
			$sub = NazarenerScan_Database::get_submission( $id );
			if ( ! $sub || $sub->status !== 'pending' ) continue;
			if ( $action === 'bulk_approve_green' || $action === 'bulk_approve_red' ) {
				$ps = $action === 'bulk_approve_red' ? 'red' : 'green';
				NazarenerScan_Database::review_submission( $id, 'approved' );
				NazarenerScan_Database::upsert_product( [
					'barcode' => $sub->barcode,
					'name'    => $sub->product_name ?: $sub->barcode,
					'status'  => $ps,
				] );
				if ( $sub->submitter_email ) {
					$ps === 'green'
						? NazarenerScan_Mailer::submission_approved_green( $sub )
						: NazarenerScan_Mailer::submission_approved_red( $sub );
				}
			} elseif ( $action === 'bulk_reject' ) {
				NazarenerScan_Database::review_submission( $id, 'rejected' );
				if ( $sub->submitter_email ) NazarenerScan_Mailer::submission_rejected( $sub );
			}
		}
		wp_redirect( admin_url( 'admin.php?page=nazarener-submissions&bulk_reviewed=' . count( $ids ) ) );
		exit;
	}

	// ── Settings ──────────────────────────────────────────────────────────────

	private function save_settings(): void {
		check_admin_referer( 'nazarener_settings' );
		$this->require_cap( 'manage_options' );

		update_option( 'nazarener_scan_settings', [
			'require_login'      => ! empty( $_POST['require_login'] ),
			'notify_email'       => sanitize_email( $_POST['notify_email']    ?? '' ),
			'mail_from'          => sanitize_email( $_POST['mail_from']       ?? '' ),
			'mail_from_name'     => sanitize_text_field( $_POST['mail_from_name'] ?? '' ),
			'max_upload_mb'      => max( 1, min( 20, (int) ( $_POST['max_upload_mb'] ?? 5 ) ) ),
			'show_hints'         => ! empty( $_POST['show_hints'] ),
			'scanner_height'     => sanitize_text_field( $_POST['scanner_height'] ?? '420px' ),
			'anonymize_ip'       => ! empty( $_POST['anonymize_ip'] ),
			'disable_scan_log'   => ! empty( $_POST['disable_scan_log'] ),
			'cleanup_after_days' => max( 0, (int) ( $_POST['cleanup_after_days'] ?? 0 ) ),
		] );

		wp_redirect( admin_url( 'admin.php?page=nazarener-settings&saved=1' ) );
		exit;
	}

	// ── Helper ───────────────────────────────────────────────────────────────

	private function require_cap( string $cap ): void {
		if ( ! current_user_can( $cap ) ) wp_die( 'Keine Berechtigung.' );
	}
}
