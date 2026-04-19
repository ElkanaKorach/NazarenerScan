<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NazarenerScan_Submissions_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'submission',
			'plural'   => 'submissions',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'barcode'      => 'Barcode',
			'product_name' => 'Produktname',
			'submitter'    => 'Einreicher',
			'status'       => 'Status',
			'photos'       => 'Fotos',
			'created_at'   => 'Eingereicht',
			'actions'      => 'Aktionen',
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'barcode'    => [ 'barcode', false ],
			'status'     => [ 'status', false ],
			'created_at' => [ 'created_at', true ],
		];
	}

	protected function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '–' );
	}

	protected function column_status( $item ): string {
		return match ( $item->status ) {
			'pending'  => '<span class="ns-badge ns-badge--yellow">🟡 Ausstehend</span>',
			'approved' => '<span class="ns-badge ns-badge--green">🟢 Genehmigt</span>',
			'rejected' => '<span class="ns-badge ns-badge--red">🔴 Abgelehnt</span>',
			default    => esc_html( $item->status ),
		};
	}

	protected function column_submitter( $item ): string {
		$name  = esc_html( $item->submitter_name ?: 'Anonym' );
		$email = $item->submitter_email ? '<br><small>' . esc_html( $item->submitter_email ) . '</small>' : '';
		return $name . $email;
	}

	protected function column_photos( $item ): string {
		$out = [];
		if ( $item->product_photo_url ) {
			$out[] = '<a href="' . esc_url( $item->product_photo_url ) . '" target="_blank">📷 Produkt</a>';
		}
		if ( $item->ingredients_photo_url ) {
			$out[] = '<a href="' . esc_url( $item->ingredients_photo_url ) . '" target="_blank">📋 Zutaten</a>';
		}
		return $out ? implode( ' · ', $out ) : '–';
	}

	protected function column_created_at( $item ): string {
		return $item->created_at
			? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item->created_at ) ) )
			: '–';
	}

	protected function column_barcode( $item ): string {
		$review_url = admin_url( 'admin.php?page=nazarener-submissions&action=review&id=' . $item->id );
		return '<code>' . esc_html( $item->barcode ) . '</code>' .
		       $this->row_actions( [
			       'review' => '<a href="' . esc_url( $review_url ) . '">Prüfen</a>',
		       ] );
	}

	protected function column_actions( $item ): string {
		$review_url = esc_url( admin_url( 'admin.php?page=nazarener-submissions&action=review&id=' . $item->id ) );
		return '<a href="' . $review_url . '" class="button button-small button-primary">Prüfen</a>';
	}

	public function get_views(): array {
		$base    = admin_url( 'admin.php?page=nazarener-submissions' );
		$current = sanitize_text_field( $_GET['status_filter'] ?? '' );
		$total   = NazarenerScan_Database::count_submissions();
		$pending = NazarenerScan_Database::count_submissions( [ 'status' => 'pending' ] );
		$app     = NazarenerScan_Database::count_submissions( [ 'status' => 'approved' ] );
		$rej     = NazarenerScan_Database::count_submissions( [ 'status' => 'rejected' ] );

		return [
			'all'      => sprintf( '<a href="%s"%s>Alle <span class="count">(%d)</span></a>', esc_url( $base ), ! $current ? ' class="current"' : '', $total ),
			'pending'  => sprintf( '<a href="%s"%s>Ausstehend <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status_filter', 'pending', $base ) ), $current === 'pending' ? ' class="current"' : '', $pending ),
			'approved' => sprintf( '<a href="%s"%s>Genehmigt <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status_filter', 'approved', $base ) ), $current === 'approved' ? ' class="current"' : '', $app ),
			'rejected' => sprintf( '<a href="%s"%s>Abgelehnt <span class="count">(%d)</span></a>', esc_url( add_query_arg( 'status_filter', 'rejected', $base ) ), $current === 'rejected' ? ' class="current"' : '', $rej ),
		];
	}

	public function prepare_items(): void {
		$per_page     = (int) get_user_meta( get_current_user_id(), 'nazarener_submissions_per_page', true ) ?: 20;
		$current_page = $this->get_pagenum();
		$search       = sanitize_text_field( $_GET['s'] ?? '' );
		$status       = sanitize_text_field( $_GET['status_filter'] ?? '' );

		$args = [
			'per_page' => $per_page,
			'paged'    => $current_page,
			'search'   => $search,
			'status'   => $status,
			'orderby'  => sanitize_text_field( $_GET['orderby'] ?? 'created_at' ),
			'order'    => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
		];

		$total       = NazarenerScan_Database::count_submissions( $args );
		$this->items = NazarenerScan_Database::get_submissions( $args );

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}
}
