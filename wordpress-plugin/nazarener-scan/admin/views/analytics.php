<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$stats      = NazarenerScan_Database::get_stats();
$by_day     = NazarenerScan_Database::get_scan_stats_by_day( 30 );
$top_scanned = NazarenerScan_Database::get_top_scanned( 10 );
$top_yellow  = NazarenerScan_Database::get_top_yellow( 10 );

// Build chart data
$chart_labels = []; $chart_green = []; $chart_red = []; $chart_yellow = [];
$day_map = [];
foreach ( $by_day as $row ) {
	$day_map[ $row->scanned_at ][ $row->status ] = (int) $row->count;
}
// Fill last 30 days
for ( $i = 29; $i >= 0; $i-- ) {
	$day    = date( 'Y-m-d', strtotime( "-{$i} days" ) );
	$label  = date_i18n( 'd.m.', strtotime( $day ) );
	$chart_labels[] = $label;
	$chart_green[]  = $day_map[ $day ]['green']  ?? 0;
	$chart_red[]    = $day_map[ $day ]['red']    ?? 0;
	$chart_yellow[] = $day_map[ $day ]['yellow'] ?? 0;
}
?>
<div class="wrap ns-wrap">
	<h1><span class="dashicons dashicons-chart-bar"></span> Analysen</h1>
	<hr class="wp-header-end">

	<!-- KPI Row -->
	<div class="ns-stats-grid" style="margin-bottom:28px">
		<div class="ns-stat-card"><div class="ns-stat-number"><?= $stats['scans_today'] ?></div><div class="ns-stat-label">Scans heute</div></div>
		<div class="ns-stat-card"><div class="ns-stat-number"><?= $stats['scans_week'] ?></div><div class="ns-stat-label">Scans (7 Tage)</div></div>
		<div class="ns-stat-card ns-stat-card--yellow"><div class="ns-stat-number"><?= $stats['notify_requests'] ?></div><div class="ns-stat-label">🔔 Benachrichtigungen ausstehend</div></div>
		<div class="ns-stat-card"><div class="ns-stat-number"><?= $stats['submissions_pending'] ?></div><div class="ns-stat-label">Einreichungen offen</div></div>
	</div>

	<!-- Scan chart (last 30 days) -->
	<div class="ns-analytics-card">
		<h2>Scans der letzten 30 Tage</h2>
		<div style="position:relative;height:260px">
			<canvas id="ns-scan-chart"></canvas>
		</div>
	</div>

	<div class="ns-analytics-two-col">
		<!-- Top scanned -->
		<div class="ns-analytics-card">
			<h3>Häufigste Scans (30 Tage)</h3>
			<?php if ( $top_scanned ): ?>
			<table class="wp-list-table widefat striped fixed">
				<thead><tr><th>Barcode</th><th>Scans</th><th>Status</th></tr></thead>
				<tbody>
				<?php foreach ( $top_scanned as $row ):
					$product = NazarenerScan_Database::get_product_by_barcode( $row->barcode );
				?>
				<tr>
					<td>
						<code><?= esc_html( $row->barcode ) ?></code>
						<?php if ( $product ): ?>
							<br><small><?= esc_html( $product->name ) ?></small>
						<?php endif; ?>
					</td>
					<td><strong><?= esc_html( $row->total_scans ) ?></strong></td>
					<td>
						<?php if ( ! $product ): ?>
							<span class="ns-badge ns-badge--yellow">🟡 Unbekannt</span>
						<?php else: ?>
							<span class="ns-badge ns-badge--<?= esc_attr( $product->status ) ?>">
								<?= $product->status === 'green' ? '🟢 Erlaubt' : '🔴 Verboten' ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else: ?>
				<p class="description">Noch keine Scan-Daten vorhanden.</p>
			<?php endif; ?>
		</div>

		<!-- Most wanted yellow (requested but not in DB) -->
		<div class="ns-analytics-card">
			<h3>Meistgefragte Unbekannte (🟡)</h3>
			<p class="description">Diese Barcodes werden oft gescannt, sind aber nicht in der Datenbank.</p>
			<?php if ( $top_yellow ): ?>
			<table class="wp-list-table widefat striped fixed">
				<thead><tr><th>Barcode</th><th>Scans</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $top_yellow as $row ): ?>
				<tr>
					<td><code><?= esc_html( $row->barcode ) ?></code></td>
					<td><?= esc_html( $row->total_scans ) ?></td>
					<td>
						<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products&action=new&barcode=' . urlencode( $row->barcode ) ) ) ?>"
						   class="button button-small">Hinzufügen</a>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else: ?>
				<p class="description">Keine Daten.</p>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Chart.js (self-hosted via WordPress script loader) -->
<script>
(function() {
	if (!document.getElementById('ns-scan-chart')) return;

	var script = document.createElement('script');
	script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
	script.onload = function() {
		var ctx = document.getElementById('ns-scan-chart').getContext('2d');
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: <?= wp_json_encode( $chart_labels ) ?>,
				datasets: [
					{ label: '🟢 Erlaubt',       data: <?= wp_json_encode( $chart_green ) ?>,  backgroundColor: '#4CAF50', stack: 'a' },
					{ label: '🔴 Nicht erlaubt', data: <?= wp_json_encode( $chart_red ) ?>,    backgroundColor: '#F44336', stack: 'a' },
					{ label: '🟡 Unbekannt',     data: <?= wp_json_encode( $chart_yellow ) ?>, backgroundColor: '#FF9800', stack: 'a' },
				]
			},
			options: {
				responsive: true, maintainAspectRatio: false,
				plugins: { legend: { position: 'top' } },
				scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
			}
		});
	};
	document.head.appendChild(script);
})();
</script>
