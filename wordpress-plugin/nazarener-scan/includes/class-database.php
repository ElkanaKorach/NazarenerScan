<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Database {

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$tables = [
			"CREATE TABLE {$wpdb->prefix}nazarener_products (
				id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				barcode             VARCHAR(100)        NOT NULL,
				name                VARCHAR(255)        NOT NULL,
				brand               VARCHAR(255)        NOT NULL DEFAULT '',
				status              ENUM('green','red') NOT NULL DEFAULT 'green',
				forbidden_ingredients TEXT              NULL,
				biblical_reference  VARCHAR(500)        NULL,
				notes               TEXT                NULL,
				product_image_id    BIGINT(20) UNSIGNED NULL,
				reviewed_by         BIGINT(20) UNSIGNED NULL,
				reviewed_at         DATETIME            NULL,
				created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY   barcode (barcode),
				KEY          status  (status)
			) $charset",

			"CREATE TABLE {$wpdb->prefix}nazarener_product_barcodes (
				id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				product_id  BIGINT(20) UNSIGNED NOT NULL,
				barcode     VARCHAR(100)        NOT NULL,
				label       VARCHAR(255)        NOT NULL DEFAULT '',
				PRIMARY KEY (id),
				UNIQUE KEY  barcode    (barcode),
				KEY         product_id (product_id)
			) $charset",

			"CREATE TABLE {$wpdb->prefix}nazarener_submissions (
				id                    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				barcode               VARCHAR(100)        NOT NULL,
				product_name          VARCHAR(255)        NOT NULL DEFAULT '',
				product_photo_url     VARCHAR(600)        NULL,
				ingredients_photo_url VARCHAR(600)        NULL,
				ingredients_text      TEXT                NULL,
				submitter_name        VARCHAR(255)        NOT NULL DEFAULT '',
				submitter_email       VARCHAR(255)        NOT NULL DEFAULT '',
				submitter_ip          VARCHAR(45)         NULL,
				status                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
				reviewer_id           BIGINT(20) UNSIGNED NULL,
				reviewer_notes        TEXT                NULL,
				reviewed_at           DATETIME            NULL,
				created_at            DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY barcode    (barcode),
				KEY status     (status),
				KEY created_at (created_at)
			) $charset",

			"CREATE TABLE {$wpdb->prefix}nazarener_changelog (
				id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				object_type VARCHAR(50)         NOT NULL,
				object_id   BIGINT(20) UNSIGNED NOT NULL,
				action      VARCHAR(80)         NOT NULL,
				user_id     BIGINT(20) UNSIGNED NULL,
				user_name   VARCHAR(255)        NULL,
				old_data    LONGTEXT            NULL,
				new_data    LONGTEXT            NULL,
				notes       TEXT                NULL,
				created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY object   (object_type, object_id),
				KEY created_at (created_at)
			) $charset",

			"CREATE TABLE {$wpdb->prefix}nazarener_notify_requests (
				id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				barcode      VARCHAR(100)        NOT NULL,
				email        VARCHAR(255)        NOT NULL,
				created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
				notified_at  DATETIME            NULL,
				PRIMARY KEY (id),
				UNIQUE KEY barcode_email (barcode, email),
				KEY barcode (barcode)
			) $charset",

			"CREATE TABLE {$wpdb->prefix}nazarener_scan_log (
				id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				barcode    VARCHAR(100)        NOT NULL,
				status     ENUM('green','red','yellow') NOT NULL,
				scanned_at DATE                NOT NULL,
				PRIMARY KEY (id),
				KEY barcode    (barcode),
				KEY scanned_at (scanned_at)
			) $charset",
		];

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'nazarener_scan_db_version', NAZARENER_SCAN_DB_VERSION );

		self::register_roles();
	}

	public static function deactivate(): void {}

	public static function register_roles(): void {
		add_role( 'nazarener_reviewer', 'NazarenerScan Prüfer', [
			'read'              => true,
			'nazarener_review'  => true,
		] );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'nazarener_review' );
			$admin->add_cap( 'nazarener_manage' );
		}
	}

	// ── Products ──────────────────────────────────────────────────────────────

	public static function get_product_by_barcode( string $barcode ): ?object {
		global $wpdb;
		// Check primary table
		$product = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nazarener_products WHERE barcode = %s LIMIT 1",
			$barcode
		) );
		if ( $product ) {
			return $product;
		}
		// Check additional barcodes table
		$extra = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.* FROM {$wpdb->prefix}nazarener_products p
			 INNER JOIN {$wpdb->prefix}nazarener_product_barcodes pb ON p.id = pb.product_id
			 WHERE pb.barcode = %s LIMIT 1",
			$barcode
		) );
		return $extra ?: null;
	}

	public static function get_product( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nazarener_products WHERE id = %d",
			$id
		) ) ?: null;
	}

	/** @return object[] */
	public static function get_products( array $args = [] ): array {
		global $wpdb;
		$args = wp_parse_args( $args, [
			'status'  => '', 'search'  => '',
			'orderby' => 'created_at', 'order' => 'DESC',
			'per_page' => 20, 'paged'   => 1,
		] );

		[ $where_sql, $values ] = self::build_product_where( $args );

		$orderby = in_array( $args['orderby'], [ 'name','brand','status','barcode','created_at','updated_at' ], true )
			? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset  = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];

		$sql = "SELECT * FROM {$wpdb->prefix}nazarener_products $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		array_push( $values, (int) $args['per_page'], $offset );

		return $wpdb->get_results( $values ? $wpdb->prepare( $sql, ...$values ) : $sql ) ?: [];
	}

	public static function count_products( array $args = [] ): int {
		global $wpdb;
		[ $where_sql, $values ] = self::build_product_where( $args );
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}nazarener_products $where_sql";
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) : $wpdb->get_var( $sql ) );
	}

	private static function build_product_where( array $args ): array {
		global $wpdb;
		$where = []; $values = [];
		if ( ! empty( $args['status'] ) && in_array( $args['status'], [ 'green','red' ], true ) ) {
			$where[] = 'status = %s'; $values[] = $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(name LIKE %s OR brand LIKE %s OR barcode LIKE %s)';
			array_push( $values, $like, $like, $like );
		}
		return [ $where ? 'WHERE ' . implode( ' AND ', $where ) : '', $values ];
	}

	public static function upsert_product( array $data, bool $log = true ): int|false {
		global $wpdb;
		$existing = ! empty( $data['id'] )
			? self::get_product( (int) $data['id'] )
			: self::get_product_by_barcode( $data['barcode'] ?? '' );

		$fields = [
			'barcode'               => sanitize_text_field( $data['barcode'] ?? '' ),
			'name'                  => sanitize_text_field( $data['name'] ?? '' ),
			'brand'                 => sanitize_text_field( $data['brand'] ?? '' ),
			'status'                => in_array( $data['status'] ?? '', [ 'green','red' ], true ) ? $data['status'] : 'green',
			'forbidden_ingredients' => sanitize_textarea_field( $data['forbidden_ingredients'] ?? '' ) ?: null,
			'biblical_reference'    => sanitize_text_field( $data['biblical_reference'] ?? '' ) ?: null,
			'notes'                 => sanitize_textarea_field( $data['notes'] ?? '' ) ?: null,
			'product_image_id'      => ! empty( $data['product_image_id'] ) ? (int) $data['product_image_id'] : null,
			'reviewed_by'           => get_current_user_id() ?: null,
			'reviewed_at'           => current_time( 'mysql' ),
		];

		if ( $existing ) {
			$old_data = (array) $existing;
			$wpdb->update( "{$wpdb->prefix}nazarener_products", $fields, [ 'id' => $existing->id ] );
			if ( $log ) {
				self::log( 'product', $existing->id, 'updated', $old_data, $fields );
			}
			// Update additional barcodes
			if ( isset( $data['extra_barcodes'] ) ) {
				self::sync_extra_barcodes( $existing->id, $data['extra_barcodes'] );
			}
			delete_transient( 'ns_product_' . md5( $existing->barcode ) );
			return $existing->id;
		}

		$wpdb->insert( "{$wpdb->prefix}nazarener_products", $fields );
		$id = $wpdb->insert_id;
		if ( $id && $log ) {
			self::log( 'product', $id, 'created', null, $fields );
		}
		if ( $id && isset( $data['extra_barcodes'] ) ) {
			self::sync_extra_barcodes( $id, $data['extra_barcodes'] );
		}
		return $id ?: false;
	}

	public static function delete_product( int $id ): bool {
		global $wpdb;
		$product = self::get_product( $id );
		if ( $product ) {
			self::log( 'product', $id, 'deleted', (array) $product, null );
			delete_transient( 'ns_product_' . md5( $product->barcode ) );
		}
		$wpdb->delete( "{$wpdb->prefix}nazarener_product_barcodes", [ 'product_id' => $id ] );
		return (bool) $wpdb->delete( "{$wpdb->prefix}nazarener_products", [ 'id' => $id ] );
	}

	// ── Additional barcodes ───────────────────────────────────────────────────

	public static function get_extra_barcodes( int $product_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nazarener_product_barcodes WHERE product_id = %d ORDER BY id ASC",
			$product_id
		) ) ?: [];
	}

	public static function sync_extra_barcodes( int $product_id, array $barcodes ): void {
		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}nazarener_product_barcodes", [ 'product_id' => $product_id ] );
		foreach ( $barcodes as $item ) {
			$bc = sanitize_text_field( is_array( $item ) ? ( $item['barcode'] ?? '' ) : $item );
			if ( ! $bc ) continue;
			$wpdb->insert( "{$wpdb->prefix}nazarener_product_barcodes", [
				'product_id' => $product_id,
				'barcode'    => $bc,
				'label'      => sanitize_text_field( is_array( $item ) ? ( $item['label'] ?? '' ) : '' ),
			] );
			delete_transient( 'ns_product_' . md5( $bc ) );
		}
	}

	// ── Submissions ───────────────────────────────────────────────────────────

	public static function get_submission( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nazarener_submissions WHERE id = %d",
			$id
		) ) ?: null;
	}

	/** @return object[] */
	public static function get_submissions( array $args = [] ): array {
		global $wpdb;
		$args = wp_parse_args( $args, [
			'status' => '', 'search' => '',
			'orderby' => 'created_at', 'order' => 'DESC',
			'per_page' => 20, 'paged' => 1,
		] );
		[ $where_sql, $values ] = self::build_submission_where( $args );
		$orderby = in_array( $args['orderby'], [ 'barcode','status','created_at','submitter_email' ], true )
			? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset  = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
		$sql = "SELECT * FROM {$wpdb->prefix}nazarener_submissions $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		array_push( $values, (int) $args['per_page'], $offset );
		return $wpdb->get_results( $values ? $wpdb->prepare( $sql, ...$values ) : $sql ) ?: [];
	}

	public static function count_submissions( array $args = [] ): int {
		global $wpdb;
		[ $where_sql, $values ] = self::build_submission_where( $args );
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}nazarener_submissions $where_sql";
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) : $wpdb->get_var( $sql ) );
	}

	private static function build_submission_where( array $args ): array {
		global $wpdb;
		$where = []; $values = [];
		if ( ! empty( $args['status'] ) && in_array( $args['status'], [ 'pending','approved','rejected' ], true ) ) {
			$where[] = 'status = %s'; $values[] = $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(barcode LIKE %s OR product_name LIKE %s OR submitter_email LIKE %s)';
			array_push( $values, $like, $like, $like );
		}
		if ( ! empty( $args['email'] ) ) {
			$where[] = 'submitter_email = %s'; $values[] = sanitize_email( $args['email'] );
		}
		return [ $where ? 'WHERE ' . implode( ' AND ', $where ) : '', $values ];
	}

	public static function add_submission( array $data ): int|false {
		global $wpdb;
		$fields = [
			'barcode'               => sanitize_text_field( $data['barcode'] ),
			'product_name'          => sanitize_text_field( $data['product_name'] ?? '' ),
			'product_photo_url'     => esc_url_raw( $data['product_photo_url'] ?? '' ) ?: null,
			'ingredients_photo_url' => esc_url_raw( $data['ingredients_photo_url'] ?? '' ) ?: null,
			'ingredients_text'      => sanitize_textarea_field( $data['ingredients_text'] ?? '' ) ?: null,
			'submitter_name'        => sanitize_text_field( $data['submitter_name'] ?? '' ),
			'submitter_email'       => sanitize_email( $data['submitter_email'] ?? '' ),
			'submitter_ip'          => self::maybe_anonymize_ip( $data['submitter_ip'] ?? '' ),
			'status'                => 'pending',
		];
		$wpdb->insert( "{$wpdb->prefix}nazarener_submissions", $fields );
		$id = $wpdb->insert_id ?: false;
		if ( $id ) {
			self::log( 'submission', $id, 'created', null, $fields );
		}
		return $id;
	}

	public static function review_submission( int $id, string $status, string $reviewer_notes = '' ): bool {
		global $wpdb;
		$old = self::get_submission( $id );
		$new = [
			'status'         => in_array( $status, [ 'approved','rejected' ], true ) ? $status : 'rejected',
			'reviewer_id'    => get_current_user_id(),
			'reviewer_notes' => sanitize_textarea_field( $reviewer_notes ),
			'reviewed_at'    => current_time( 'mysql' ),
		];
		$result = (bool) $wpdb->update( "{$wpdb->prefix}nazarener_submissions", $new, [ 'id' => $id ] );
		if ( $result ) {
			self::log( 'submission', $id, "reviewed_$status", (array) $old, $new, $reviewer_notes );
		}
		return $result;
	}

	public static function delete_submission( int $id ): bool {
		global $wpdb;
		$sub = self::get_submission( $id );
		if ( $sub ) {
			self::log( 'submission', $id, 'deleted', (array) $sub, null );
			// Clean up attached media
			foreach ( [ $sub->product_photo_url, $sub->ingredients_photo_url ] as $url ) {
				if ( $url ) {
					$att_id = attachment_url_to_postid( $url );
					if ( $att_id ) wp_delete_attachment( $att_id, true );
				}
			}
		}
		return (bool) $wpdb->delete( "{$wpdb->prefix}nazarener_submissions", [ 'id' => $id ] );
	}

	// ── Notify requests ───────────────────────────────────────────────────────

	public static function add_notify_request( string $barcode, string $email ): bool {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->prefix}nazarener_notify_requests (barcode, email) VALUES (%s, %s)",
			$barcode, sanitize_email( $email )
		) );
		return $wpdb->rows_affected > 0;
	}

	/** @return object[] */
	public static function get_notify_requests_for_barcode( string $barcode ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nazarener_notify_requests WHERE barcode = %s AND notified_at IS NULL",
			$barcode
		) ) ?: [];
	}

	public static function mark_notify_requests_sent( string $barcode ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}nazarener_notify_requests SET notified_at = %s WHERE barcode = %s AND notified_at IS NULL",
			current_time( 'mysql' ), $barcode
		) );
	}

	public static function count_notify_requests( string $barcode ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nazarener_notify_requests WHERE barcode = %s AND notified_at IS NULL",
			$barcode
		) );
	}

	// ── Scan log & analytics ──────────────────────────────────────────────────

	public static function log_scan( string $barcode, string $status ): void {
		global $wpdb;
		$settings = get_option( 'nazarener_scan_settings', [] );
		if ( ! empty( $settings['disable_scan_log'] ) ) return;

		$today = current_time( 'Y-m-d' );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}nazarener_scan_log (barcode, status, scanned_at)
			 VALUES (%s, %s, %s)
			 ON DUPLICATE KEY UPDATE barcode = barcode",
			$barcode, $status, $today
		) );
		// Actually we want multiple rows per day (count), so let's just insert:
		$wpdb->insert( "{$wpdb->prefix}nazarener_scan_log", [
			'barcode'    => $barcode,
			'status'     => $status,
			'scanned_at' => $today,
		] );
	}

	public static function get_scan_stats_by_day( int $days = 30 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT scanned_at, status, COUNT(*) as count
			 FROM {$wpdb->prefix}nazarener_scan_log
			 WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			 GROUP BY scanned_at, status
			 ORDER BY scanned_at ASC",
			$days
		) ) ?: [];
	}

	public static function get_top_scanned( int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT barcode, COUNT(*) as total_scans
			 FROM {$wpdb->prefix}nazarener_scan_log
			 WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			 GROUP BY barcode ORDER BY total_scans DESC LIMIT %d",
			$limit
		) ) ?: [];
	}

	public static function get_top_yellow( int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT barcode, COUNT(*) as total_scans
			 FROM {$wpdb->prefix}nazarener_scan_log
			 WHERE status = 'yellow' AND scanned_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			 GROUP BY barcode ORDER BY total_scans DESC LIMIT %d",
			$limit
		) ) ?: [];
	}

	// ── Changelog ─────────────────────────────────────────────────────────────

	public static function log( string $object_type, int $object_id, string $action, ?array $old, ?array $new, string $notes = '' ): void {
		global $wpdb;
		$user = wp_get_current_user();
		$wpdb->insert( "{$wpdb->prefix}nazarener_changelog", [
			'object_type' => $object_type,
			'object_id'   => $object_id,
			'action'      => $action,
			'user_id'     => $user->ID ?: null,
			'user_name'   => $user->display_name ?: null,
			'old_data'    => $old ? wp_json_encode( $old ) : null,
			'new_data'    => $new ? wp_json_encode( $new ) : null,
			'notes'       => sanitize_textarea_field( $notes ),
		] );
	}

	/** @return object[] */
	public static function get_changelog( array $args = [] ): array {
		global $wpdb;
		$args = wp_parse_args( $args, [
			'object_type' => '', 'object_id' => 0,
			'per_page'    => 30, 'paged'     => 1,
		] );
		$where = []; $values = [];
		if ( $args['object_type'] ) { $where[] = 'object_type = %s'; $values[] = $args['object_type']; }
		if ( $args['object_id'] )   { $where[] = 'object_id = %d';   $values[] = (int) $args['object_id']; }
		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
		$sql = "SELECT * FROM {$wpdb->prefix}nazarener_changelog $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
		array_push( $values, (int) $args['per_page'], $offset );
		return $wpdb->get_results( $values ? $wpdb->prepare( $sql, ...$values ) : $sql ) ?: [];
	}

	public static function count_changelog( array $args = [] ): int {
		global $wpdb;
		$where = []; $values = [];
		if ( ! empty( $args['object_type'] ) ) { $where[] = 'object_type = %s'; $values[] = $args['object_type']; }
		if ( ! empty( $args['object_id'] ) )   { $where[] = 'object_id = %d';   $values[] = (int) $args['object_id']; }
		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}nazarener_changelog $where_sql";
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) : $wpdb->get_var( $sql ) );
	}

	// ── Stats ─────────────────────────────────────────────────────────────────

	public static function get_stats(): array {
		global $wpdb;
		$p = $wpdb->prefix;
		return [
			'products_total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_products" ),
			'products_green'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_products WHERE status='green'" ),
			'products_red'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_products WHERE status='red'" ),
			'submissions_pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_submissions WHERE status='pending'" ),
			'submissions_total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_submissions" ),
			'submissions_week'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
			'scans_today'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_scan_log WHERE scanned_at = CURDATE()" ),
			'scans_week'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_scan_log WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" ),
			'notify_requests'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}nazarener_notify_requests WHERE notified_at IS NULL" ),
		];
	}

	// ── Cleanup (cron) ────────────────────────────────────────────────────────

	public static function cleanup_old_submissions(): int {
		global $wpdb;
		$settings      = get_option( 'nazarener_scan_settings', [] );
		$days          = (int) ( $settings['cleanup_after_days'] ?? 0 );
		if ( $days < 1 ) return 0;

		$old = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}nazarener_submissions
			 WHERE status IN ('approved','rejected')
			 AND reviewed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );

		foreach ( $old as $row ) {
			self::delete_submission( (int) $row->id );
		}
		return count( $old );
	}

	// ── CSV Export (batched) ──────────────────────────────────────────────────

	public static function export_products_csv(): void {
		global $wpdb;
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nazarener-produkte-' . date( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Barcode', 'Name', 'Marke', 'Status', 'Verbotene Zutaten', 'Bibl. Referenz', 'Notizen', 'Geprüft am' ], ';' );

		$offset = 0;
		$batch  = 200;
		do {
			$products = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}nazarener_products ORDER BY name ASC LIMIT %d OFFSET %d",
				$batch, $offset
			) );
			foreach ( $products as $p ) {
				fputcsv( $out, [
					$p->barcode, $p->name, $p->brand, $p->status,
					$p->forbidden_ingredients ?? '',
					$p->biblical_reference ?? '',
					$p->notes ?? '',
					$p->reviewed_at ?? '',
				], ';' );
			}
			$offset += $batch;
		} while ( count( $products ) === $batch );

		fclose( $out );
		exit;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function maybe_anonymize_ip( string $ip ): string {
		$settings = get_option( 'nazarener_scan_settings', [] );
		if ( empty( $settings['anonymize_ip'] ) ) return $ip;
		// Zero last octet for IPv4, last 80 bits for IPv6
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return substr( $ip, 0, strrpos( $ip, ':' ) ) . ':0';
		}
		return $ip;
	}
}
