<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NazarenerScan_Products_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'product',
			'plural'   => 'products',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'cb'      => '<input type="checkbox" />',
			'barcode' => 'Barcode',
			'name'    => 'Produktname',
			'brand'   => 'Marke',
			'status'  => 'Status',
			'notes'   => 'Notizen',
			'actions' => 'Aktionen',
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'name'    => [ 'name', false ],
			'brand'   => [ 'brand', false ],
			'status'  => [ 'status', false ],
			'barcode' => [ 'barcode', false ],
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'set_green' => '🟢 Als Grün markieren',
			'set_red'   => '🔴 Als Rot markieren',
			'delete'    => 'Löschen',
		];
	}

	protected function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="product_ids[]" value="' . esc_attr( $item->id ) . '" />';
	}

	protected function column_status( $item ): string {
		if ( $item->status === 'green' ) {
			return '<span class="ns-badge ns-badge--green">🟢 Erlaubt</span>';
		}
		return '<span class="ns-badge ns-badge--red">🔴 Nicht erlaubt</span>';
	}

	protected function column_name( $item ): string {
		$edit_url   = admin_url( 'admin.php?page=nazarener-products&action=edit&product_id=' . $item->id );
		$name       = esc_html( $item->name );
		$row_actions = $this->row_actions( [
			'edit' => '<a href="' . esc_url( $edit_url ) . '">Bearbeiten</a>',
		] );
		return "<strong><a href=\"" . esc_url( $edit_url ) . "\">$name</a></strong>$row_actions";
	}

	protected function column_notes( $item ): string {
		$text = $item->notes ?? '';
		return $text ? '<span title="' . esc_attr( $text ) . '">' . esc_html( mb_substr( $text, 0, 50 ) ) . ( mb_strlen( $text ) > 50 ? '…' : '' ) . '</span>' : '–';
	}

	protected function column_actions( $item ): string {
		$edit_url   = esc_url( admin_url( 'admin.php?page=nazarener-products&action=edit&product_id=' . $item->id ) );
		return '<a href="' . $edit_url . '" class="button button-small">Bearbeiten</a>';
	}

	public function prepare_items(): void {
		$per_page     = 20;
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

		$total = NazarenerScan_Database::count_products( $args );

		$this->items = NazarenerScan_Database::get_products( $args );

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}
}
