<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class NazarenerScan_Activity_Log_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [ 'singular' => 'log', 'plural' => 'logs', 'ajax' => false ] );
	}

	public function get_columns(): array {
		return [
			'created_at'  => 'Zeit',
			'user_name'   => 'Benutzer',
			'object_type' => 'Typ',
			'object_id'   => 'ID',
			'action'      => 'Aktion',
			'notes'       => 'Notizen',
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'created_at'  => [ 'created_at', true ],
			'user_name'   => [ 'user_name', false ],
			'object_type' => [ 'object_type', false ],
			'action'      => [ 'action', false ],
		];
	}

	protected function column_default( $item, $col ): string {
		return esc_html( $item->$col ?? '–' );
	}

	protected function column_created_at( $item ): string {
		return esc_html( date_i18n( 'd.m.Y H:i:s', strtotime( $item->created_at ) ) );
	}

	protected function column_object_type( $item ): string {
		return match ( $item->object_type ) {
			'product'    => '<span class="ns-badge ns-badge--green">Produkt</span>',
			'submission' => '<span class="ns-badge ns-badge--yellow">Einreichung</span>',
			default      => esc_html( $item->object_type ),
		};
	}

	protected function column_object_id( $item ): string {
		if ( $item->object_type === 'product' ) {
			$url = admin_url( 'admin.php?page=nazarener-products&action=edit&product_id=' . $item->object_id );
			return '<a href="' . esc_url( $url ) . '">#' . esc_html( $item->object_id ) . '</a>';
		}
		if ( $item->object_type === 'submission' ) {
			$url = admin_url( 'admin.php?page=nazarener-submissions&action=review&id=' . $item->object_id );
			return '<a href="' . esc_url( $url ) . '">#' . esc_html( $item->object_id ) . '</a>';
		}
		return esc_html( $item->object_id );
	}

	protected function column_action( $item ): string {
		$labels = [
			'created'            => '➕ Erstellt',
			'updated'            => '✏️ Bearbeitet',
			'deleted'            => '🗑️ Gelöscht',
			'reviewed_approved'  => '🟢 Genehmigt',
			'reviewed_rejected'  => '🔴 Abgelehnt',
		];
		return esc_html( $labels[ $item->action ] ?? $item->action );
	}

	protected function column_notes( $item ): string {
		$notes = $item->notes ?? '';
		return $notes
			? '<span title="' . esc_attr( $notes ) . '">' . esc_html( mb_substr( $notes, 0, 60 ) ) . ( mb_strlen( $notes ) > 60 ? '…' : '' ) . '</span>'
			: '–';
	}

	public function prepare_items(): void {
		$per_page = 30;
		$args     = [
			'per_page' => $per_page,
			'paged'    => $this->get_pagenum(),
		];
		$total       = NazarenerScan_Database::count_changelog( $args );
		$this->items = NazarenerScan_Database::get_changelog( $args );
		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		] );
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}
}
