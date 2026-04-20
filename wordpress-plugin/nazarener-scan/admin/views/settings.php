<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php $s = get_option( 'nazarener_scan_settings', [] ); ?>
<div class="wrap ns-wrap">
	<h1><span class="dashicons dashicons-admin-settings"></span> Einstellungen</h1>

	<?php if ( isset( $_GET['saved'] ) ): ?>
		<div class="notice notice-success is-dismissible"><p>✅ Einstellungen gespeichert.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'nazarener_settings' ); ?>
		<input type="hidden" name="page" value="nazarener-settings" />

		<!-- ── Einreichungen ── -->
		<h2 class="ns-settings-section">📥 Einreichungen</h2>
		<table class="form-table">
			<tr>
				<th>Einreichung erlaubt für</th>
				<td>
					<label><input type="checkbox" name="require_login" value="1" <?= ! empty( $s['require_login'] ) ? 'checked' : '' ?> />
					Nur für angemeldete Nutzer (Login erforderlich)</label>
					<p class="description">Wenn deaktiviert, können auch Gäste Produkte einreichen.</p>
				</td>
			</tr>
			<tr>
				<th><label for="cleanup_after_days">Alte Einreichungen löschen nach</label></th>
				<td>
					<input type="number" id="cleanup_after_days" name="cleanup_after_days" class="small-text"
					       value="<?= esc_attr( $s['cleanup_after_days'] ?? 0 ) ?>" min="0" max="3650" />
					Tagen (0 = nie löschen)
					<p class="description">Genehmigte und abgelehnte Einreichungen werden nach dieser Zeit automatisch gelöscht (täglicher Cron-Job).</p>
				</td>
			</tr>
		</table>

		<!-- ── E-Mail ── -->
		<h2 class="ns-settings-section">📧 E-Mail & Benachrichtigungen</h2>
		<table class="form-table">
			<tr>
				<th><label for="notify_email">Admin-Benachrichtigung</label></th>
				<td>
					<input type="email" id="notify_email" name="notify_email" class="regular-text"
					       value="<?= esc_attr( $s['notify_email'] ?? get_option( 'admin_email' ) ) ?>" />
					<p class="description">Bei neuen Einreichungen wird diese Adresse benachrichtigt. Leer = keine Benachrichtigung.</p>
				</td>
			</tr>
			<tr>
				<th><label for="mail_from">Absender-E-Mail</label></th>
				<td>
					<input type="email" id="mail_from" name="mail_from" class="regular-text"
					       value="<?= esc_attr( $s['mail_from'] ?? get_option( 'admin_email' ) ) ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="mail_from_name">Absender-Name</label></th>
				<td>
					<input type="text" id="mail_from_name" name="mail_from_name" class="regular-text"
					       value="<?= esc_attr( $s['mail_from_name'] ?? get_bloginfo( 'name' ) ) ?>" />
				</td>
			</tr>
		</table>

		<!-- ── DSGVO & Datenschutz ── -->
		<h2 class="ns-settings-section">🔒 DSGVO & Datenschutz</h2>
		<table class="form-table">
			<tr>
				<th>IP-Adressen</th>
				<td>
					<label><input type="checkbox" name="anonymize_ip" value="1" <?= ! empty( $s['anonymize_ip'] ) ? 'checked' : '' ?> />
					IP-Adressen anonymisieren (letztes Oktett wird gelöscht)</label>
					<p class="description">Empfohlen für DSGVO-Konformität. Betrifft neue Einreichungen.</p>
				</td>
			</tr>
			<tr>
				<th>Scan-Protokoll</th>
				<td>
					<label><input type="checkbox" name="disable_scan_log" value="1" <?= ! empty( $s['disable_scan_log'] ) ? 'checked' : '' ?> />
					Scan-Protokoll deaktivieren</label>
					<p class="description">Wenn deaktiviert, werden keine Scan-Ereignisse für die Analysen aufgezeichnet.</p>
				</td>
			</tr>
		</table>

		<!-- ── Scanner & Frontend ── -->
		<h2 class="ns-settings-section">📷 Scanner & Frontend</h2>
		<table class="form-table">
			<tr>
				<th>Vorläufige Zutaten-Hinweise</th>
				<td>
					<label><input type="checkbox" name="show_hints" value="1" <?= ! empty( $s['show_hints'] ) ? 'checked' : '' ?> />
					Automatische Hinweise für gelbe Produkte anzeigen</label>
					<p class="description">Zeigt dem Nutzer Muster-Warnungen (z.B. „Enthält möglicherweise Gelatine"). Ändert die Ampelfarbe <strong>nicht</strong>.</p>
				</td>
			</tr>
			<tr>
				<th><label for="scanner_height">Scanner-Höhe</label></th>
				<td>
					<input type="text" id="scanner_height" name="scanner_height" class="small-text"
					       value="<?= esc_attr( $s['scanner_height'] ?? '420px' ) ?>" placeholder="420px" />
					<p class="description">Höhe des Kamera-Viewfinders. Beispiel: <code>420px</code>, <code>60vh</code></p>
				</td>
			</tr>
			<tr>
				<th><label for="max_upload_mb">Max. Foto-Größe (MB)</label></th>
				<td>
					<input type="number" id="max_upload_mb" name="max_upload_mb" class="small-text"
					       value="<?= esc_attr( $s['max_upload_mb'] ?? 5 ) ?>" min="1" max="20" />
					MB (1–20 MB)
				</td>
			</tr>
		</table>

		<?php submit_button( '💾 Einstellungen speichern', 'primary large', 'nazarener_save_settings' ); ?>
	</form>

	<hr>
	<h2>Shortcode-Referenz</h2>
	<table class="widefat">
		<thead><tr><th>Shortcode</th><th>Beschreibung</th></tr></thead>
		<tbody>
			<tr><td><code>[nazarener_scanner]</code></td><td>Vollständiger Scanner mit Ergebnis- und Einreichungsformular.</td></tr>
			<tr><td><code>[nazarener_scanner height="500px"]</code></td><td>Scanner mit eigener Höhe.</td></tr>
			<tr><td><code>[nazarener_produktliste]</code></td><td>Öffentliche Produktliste (alle geprüften Produkte, durchsuchbar).</td></tr>
			<tr><td><code>[nazarener_produktliste status="green"]</code></td><td>Nur erlaubte Produkte.</td></tr>
			<tr><td><code>[nazarener_stats]</code></td><td>Statistik-Widget: Anzahl grüner/roter Produkte und Scans.</td></tr>
			<tr><td><code>[nazarener_status]</code></td><td>Einreichungs-Statusseite: Nutzer prüft den Stand seiner Einreichung per E-Mail.</td></tr>
		</tbody>
	</table>

	<hr>
	<h2>REST API</h2>
	<table class="widefat">
		<thead><tr><th>Endpunkt</th><th>Beschreibung</th></tr></thead>
		<tbody>
			<tr><td><code>GET /wp-json/nazarener/v1/product/{barcode}</code></td><td>Einzelnes Produkt abfragen</td></tr>
			<tr><td><code>GET /wp-json/nazarener/v1/products?status=green&search=…</code></td><td>Produktliste</td></tr>
			<tr><td><code>GET /wp-json/nazarener/v1/stats</code></td><td>Öffentliche Statistiken</td></tr>
			<tr><td><code>POST /wp-json/nazarener/v1/submit</code></td><td>Produkt einreichen (JSON)</td></tr>
			<tr><td><code>POST /wp-json/nazarener/v1/notify</code></td><td>Benachrichtigung registrieren</td></tr>
		</tbody>
	</table>

	<hr>
	<h2>Benutzerrollen</h2>
	<p>Die Rolle <strong>NazarenerScan Prüfer</strong> kann Einreichungen prüfen und Produkte verwalten, hat aber keinen Zugriff auf WordPress-Einstellungen.</p>
	<p>Aktuell verfügbare Fähigkeiten: <code>nazarener_review</code>, <code>nazarener_manage</code>.</p>

	<hr>
	<h2>Datenbank-Info</h2>
	<?php
	$stats = NazarenerScan_Database::get_stats();
	?>
	<p>
		<strong>Produkte:</strong> <?= $stats['products_total'] ?> &nbsp;|&nbsp;
		<strong>Einreichungen:</strong> <?= $stats['submissions_total'] ?> &nbsp;|&nbsp;
		<strong>Scans gesamt:</strong> <?= $stats['scans_week'] ?> (letzte 7 Tage) &nbsp;|&nbsp;
		<strong>Plugin-Version:</strong> <?= NAZARENER_SCAN_VERSION ?> &nbsp;|&nbsp;
		<strong>DB-Version:</strong> <?= get_option( 'nazarener_scan_db_version', '–' ) ?>
	</p>
</div>
