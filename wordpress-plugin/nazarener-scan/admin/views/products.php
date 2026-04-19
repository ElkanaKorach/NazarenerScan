<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$table = new NazarenerScan_Products_Table();
$table->prepare_items();
?>
<div class="wrap ns-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-carrot"></span>
		Produkte
	</h1>
	<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products&action=new' ) ) ?>" class="page-title-action">Neu hinzufügen</a>
	<a href="<?= esc_url( wp_nonce_url( admin_url( 'admin.php?page=nazarener-products&export=csv' ), 'nazarener_export_csv' ) ) ?>" class="page-title-action">CSV exportieren</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['saved'] ) )   : ?><div class="notice notice-success is-dismissible"><p>✅ Produkt gespeichert.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p>🗑️ Produkt gelöscht.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['bulk_done'] ) ) : ?><div class="notice notice-success is-dismissible"><p>✅ Bulk-Aktion ausgeführt.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['imported'] ) ) : ?><div class="notice notice-success is-dismissible"><p>✅ <?= (int) $_GET['imported'] ?> Produkte importiert.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['import_error'] ) ) : ?><div class="notice notice-error is-dismissible"><p>❌ CSV-Import fehlgeschlagen.</p></div><?php endif; ?>

	<!-- Status filter tabs -->
	<div class="ns-filter-tabs">
		<?php
		$base    = admin_url( 'admin.php?page=nazarener-products' );
		$current = sanitize_text_field( $_GET['status_filter'] ?? '' );
		$total   = NazarenerScan_Database::count_products();
		$green   = NazarenerScan_Database::count_products( [ 'status' => 'green' ] );
		$red     = NazarenerScan_Database::count_products( [ 'status' => 'red' ] );
		?>
		<a href="<?= esc_url( $base ) ?>" class="<?= ! $current ? 'current' : '' ?>">Alle (<?= $total ?>)</a>
		<a href="<?= esc_url( add_query_arg( 'status_filter', 'green', $base ) ) ?>" class="<?= $current === 'green' ? 'current' : '' ?>">🟢 Erlaubt (<?= $green ?>)</a>
		<a href="<?= esc_url( add_query_arg( 'status_filter', 'red', $base ) ) ?>" class="<?= $current === 'red' ? 'current' : '' ?>">🔴 Nicht erlaubt (<?= $red ?>)</a>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'bulk-products' ); ?>
		<input type="hidden" name="page" value="nazarener-products" />
		<?php $table->search_box( 'Suchen', 'product_search' ); ?>
		<?php $table->display(); ?>
	</form>

	<!-- CSV Import -->
	<div class="ns-import-box">
		<h3>CSV importieren</h3>
		<p class="description">Format: <code>Barcode;Name;Marke;Status(green|red);Verbotene Zutaten;Notizen</code> – Trennzeichen: Semikolon</p>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'nazarener_import_csv' ); ?>
			<input type="hidden" name="page" value="nazarener-products" />
			<input type="file" name="csv_file" accept=".csv,text/csv" required />
			<input type="submit" name="nazarener_import_csv" class="button" value="CSV importieren" />
		</form>
	</div>
</div>
