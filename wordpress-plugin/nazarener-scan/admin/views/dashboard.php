<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php $stats = NazarenerScan_Database::get_stats(); ?>
<div class="wrap ns-wrap">
	<h1 class="ns-page-title">
		<span class="dashicons dashicons-visibility"></span>
		NazarenerScan – Dashboard
	</h1>

	<?php if ( isset( $_GET['saved'] ) ): ?>
		<div class="notice notice-success is-dismissible"><p>✅ Gespeichert.</p></div>
	<?php endif; ?>

	<!-- Stats cards -->
	<div class="ns-stats-grid">
		<div class="ns-stat-card">
			<div class="ns-stat-number"><?= esc_html( $stats['products_total'] ) ?></div>
			<div class="ns-stat-label">Produkte gesamt</div>
		</div>
		<div class="ns-stat-card ns-stat-card--green">
			<div class="ns-stat-number"><?= esc_html( $stats['products_green'] ) ?></div>
			<div class="ns-stat-label">🟢 Erlaubt</div>
		</div>
		<div class="ns-stat-card ns-stat-card--red">
			<div class="ns-stat-number"><?= esc_html( $stats['products_red'] ) ?></div>
			<div class="ns-stat-label">🔴 Nicht erlaubt</div>
		</div>
		<div class="ns-stat-card ns-stat-card--yellow">
			<div class="ns-stat-number"><?= esc_html( $stats['submissions_pending'] ) ?></div>
			<div class="ns-stat-label">🟡 Ausstehende Prüfungen</div>
		</div>
		<div class="ns-stat-card">
			<div class="ns-stat-number"><?= esc_html( $stats['submissions_week'] ) ?></div>
			<div class="ns-stat-label">Einreichungen (7 Tage)</div>
		</div>
		<div class="ns-stat-card">
			<div class="ns-stat-number"><?= esc_html( $stats['submissions_total'] ) ?></div>
			<div class="ns-stat-label">Einreichungen gesamt</div>
		</div>
	</div>

	<!-- Quick actions -->
	<div class="ns-quick-actions">
		<h2>Schnellzugriff</h2>
		<div class="ns-action-row">
			<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products&action=new' ) ) ?>" class="button button-primary button-large">
				➕ Neues Produkt hinzufügen
			</a>
			<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-submissions&status_filter=pending' ) ) ?>" class="button button-large <?= $stats['submissions_pending'] > 0 ? 'button-primary' : '' ?>">
				🟡 Ausstehende prüfen
				<?php if ( $stats['submissions_pending'] > 0 ): ?>
					<span class="ns-badge-count"><?= esc_html( $stats['submissions_pending'] ) ?></span>
				<?php endif; ?>
			</a>
			<a href="<?= esc_url( wp_nonce_url( admin_url( 'admin.php?page=nazarener-products&export=csv' ), 'nazarener_export_csv' ) ) ?>" class="button button-large">
				⬇️ Produkte exportieren (CSV)
			</a>
		</div>
	</div>

	<!-- Shortcode info -->
	<div class="ns-info-box">
		<h3>Einbindung per Shortcode</h3>
		<p>Bette den Scanner auf jeder Seite oder in einem Beitrag ein:</p>
		<code class="ns-shortcode">[nazarener_scanner]</code>
		<p class="description">Optional: <code>[nazarener_scanner height="500px"]</code></p>
	</div>

	<!-- Recent submissions -->
	<?php
	$recent = NazarenerScan_Database::get_submissions( [ 'per_page' => 5, 'status' => 'pending' ] );
	if ( $recent ):
	?>
	<div class="ns-recent-table">
		<h2>Neueste ausstehende Einreichungen</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Barcode</th>
					<th>Produktname</th>
					<th>Einreicher</th>
					<th>Datum</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $sub ): ?>
				<tr>
					<td><code><?= esc_html( $sub->barcode ) ?></code></td>
					<td><?= esc_html( $sub->product_name ?: '–' ) ?></td>
					<td><?= esc_html( $sub->submitter_email ?: 'Anonym' ) ?></td>
					<td><?= esc_html( date_i18n( 'd.m.Y', strtotime( $sub->created_at ) ) ) ?></td>
					<td>
						<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-submissions&action=review&id=' . $sub->id ) ) ?>" class="button button-small button-primary">
							Prüfen
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-submissions&status_filter=pending' ) ) ?>">→ Alle ausstehenden anzeigen</a></p>
	</div>
	<?php endif; ?>
</div>
