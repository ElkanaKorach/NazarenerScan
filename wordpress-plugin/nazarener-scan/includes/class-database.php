<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Database {

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$products_sql = "CREATE TABLE {$wpdb->prefix}nazarener_products (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			barcode       VARCHAR(100)        NOT NULL,
			name          VARCHAR(255)        NOT NULL,
			brand         VARCHAR(255)        NOT NULL DEFAULT '',
			status        ENUM('green','red') NOT NULL DEFAULT 'green',
			forbidden_ingredients TEXT         NULL,
			notes         TEXT                NULL,
			reviewed_by   BIGINT(20) UNSIGNED NULL,
			reviewed_at   DATETIME            NULL,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY   barcode (barcode),
			KEY          status  (status)
		) $charset;";

		$submissions_sql = "CREATE TABLE {$wpdb->prefix}nazarener_submissions (
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
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $products_sql );
		dbDelta( $submissions_sql );

		update_option( 'nazarener_scan_db_version', NAZARENER_SCAN_DB_VERSION );
	}

	public static function deactivate(): void {}

	// ── Products ──────────────────────────────────────────────────────────────

	public static function get_product_by_barcode( string $barcode ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}nazarener_products WHERE barcode = %s LIMIT 1",
				$barcode
			)
		) ?: null;
	}

	public static function get_product( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nazarener_products WHERE id = %d", $id )
		) ?: null;
	}

	/**
	 * @return object[]
	 */
	public static function get_products( array $args = [] ): array {
		global $wpdb;
		$defaults = [
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		if ( $args['status'] && in_array( $args['status'], [ 'green', 'red' ], true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['search'] ) {
			$where[]  = '(name LIKE %s OR brand LIKE %s OR barcode LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$orderby   = in_array( $args['orderby'], [ 'name', 'brand', 'status', 'created_at', 'updated_at' ], true )
			? $args['orderby'] : 'created_at';
		$order     = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];

		$sql = "SELECT * FROM {$wpdb->prefix}nazarener_products $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		array_push( $values, (int) $args['per_page'], $offset );

		return $wpdb->get_results( $values ? $wpdb->prepare( $sql, ...$values ) : $sql ) ?: [];
	}

	public static function count_products( array $args = [] ): int {
		global $wpdb;
		$defaults = [ 'status' => '', 'search' => '' ];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		if ( $args['status'] && in_array( $args['status'], [ 'green', 'red' ], true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['search'] ) {
			$where[]  = '(name LIKE %s OR brand LIKE %s OR barcode LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql       = "SELECT COUNT(*) FROM {$wpdb->prefix}nazarener_products $where_sql";

		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) : $wpdb->get_var( $sql ) );
	}

	public static function upsert_product( array $data ): int|false {
		global $wpdb;
		$existing = self::get_product_by_barcode( $data['barcode'] );

		$fields = [
			'barcode'               => sanitize_text_field( $data['barcode'] ),
			'name'                  => sanitize_text_field( $data['name'] ),
			'brand'                 => sanitize_text_field( $data['brand'] ?? '' ),
			'status'                => in_array( $data['status'] ?? '', [ 'green', 'red' ], true ) ? $data['status'] : 'green',
			'forbidden_ingredients' => sanitize_textarea_field( $data['forbidden_ingredients'] ?? '' ) ?: null,
			'notes'                 => sanitize_textarea_field( $data['notes'] ?? '' ) ?: null,
			'reviewed_by'           => get_current_user_id() ?: null,
			'reviewed_at'           => current_time( 'mysql' ),
		];

		if ( $existing ) {
			$wpdb->update( "{$wpdb->prefix}nazarener_products", $fields, [ 'id' => $existing->id ] );
			return $existing->id;
		}

		$wpdb->insert( "{$wpdb->prefix}nazarener_products", $fields );
		return $wpdb->insert_id ?: false;
	}

	public static function delete_product( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( "{$wpdb->prefix}nazarener_products", [ 'id' => $id ] );
	}

	// ── Submissions ───────────────────────────────────────────────────────────

	public static function get_submission( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nazarener_submissions WHERE id = %d", $id )
		) ?: null;
	}

	/**
	 * @return object[]
	 */
	public static function get_submissions( array $args = [] ): array {
		global $wpdb;
		$defaults = [
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		if ( $args['status'] && in_array( $args['status'], [ 'pending', 'approved', 'rejected' ], true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['search'] ) {
			$where[]  = '(barcode LIKE %s OR product_name LIKE %s OR submitter_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$orderby   = in_array( $args['orderby'], [ 'barcode', 'status', 'created_at', 'submitter_email' ], true )
			? $args['orderby'] : 'created_at';
		$order     = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];

		$sql = "SELECT * FROM {$wpdb->prefix}nazarener_submissions $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		array_push( $values, (int) $args['per_page'], $offset );

		return $wpdb->get_results( $values ? $wpdb->prepare( $sql, ...$values ) : $sql ) ?: [];
	}

	public static function count_submissions( array $args = [] ): int {
		global $wpdb;
		$defaults = [ 'status' => '', 'search' => '' ];
		$args = wp_parse_args( $args, $defaults );

		$where  = [];
		$values = [];

		if ( $args['status'] && in_array( $args['status'], [ 'pending', 'approved', 'rejected' ], true ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( $args['search'] ) {
			$where[]  = '(barcode LIKE %s OR product_name LIKE %s OR submitter_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql       = "SELECT COUNT(*) FROM {$wpdb->prefix}nazarener_submissions $where_sql";

		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) : $wpdb->get_var( $sql ) );
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
			'submitter_ip'          => sanitize_text_field( $data['submitter_ip'] ?? '' ),
			'status'                => 'pending',
		];
		$wpdb->insert( "{$wpdb->prefix}nazarener_submissions", $fields );
		return $wpdb->insert_id ?: false;
	}

	public static function review_submission( int $id, string $status, string $reviewer_notes = '' ): bool {
		global $wpdb;
		return (bool) $wpdb->update(
			"{$wpdb->prefix}nazarener_submissions",
			[
				'status'         => in_array( $status, [ 'approved', 'rejected' ], true ) ? $status : 'rejected',
				'reviewer_id'    => get_current_user_id(),
				'reviewer_notes' => sanitize_textarea_field( $reviewer_notes ),
				'reviewed_at'    => current_time( 'mysql' ),
			],
			[ 'id' => $id ]
		);
	}

	public static function delete_submission( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( "{$wpdb->prefix}nazarener_submissions", [ 'id' => $id ] );
	}

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
		];
	}

	// ── CSV Export ────────────────────────────────────────────────────────────

	public static function export_products_csv(): void {
		global $wpdb;
		$products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}nazarener_products ORDER BY name ASC" );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nazarener-produkte-' . date( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Barcode', 'Name', 'Marke', 'Status', 'Verbotene Zutaten', 'Notizen', 'Geprüft am' ], ';' );

		foreach ( $products as $p ) {
			fputcsv( $out, [
				$p->barcode,
				$p->name,
				$p->brand,
				$p->status,
				$p->forbidden_ingredients ?? '',
				$p->notes ?? '',
				$p->reviewed_at ?? '',
			], ';' );
		}
		fclose( $out );
		exit;
	}
}
