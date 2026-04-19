<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php $s = get_option( 'nazarener_scan_settings', [] ); ?>
<div class="wrap ns-wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		Einstellungen
	</h1>

	<?php if ( isset( $_GET['saved'] ) ): ?>
		<div class="notice notice-success is-dismissible"><p>✅ Einstellungen gespeichert.</p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'nazarener_settings' ); ?>
		<input type="hidden" name="page" value="nazarener-settings" />

		<table class="form-table">

			<tr>
				<th scope="row">Einreichungen</th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="require_login" value="1"
							       <?= ! empty( $s['require_login'] ) ? 'checked' : '' ?> />
							Nur für angemeldete Nutzer (Login erforderlich)
						</label>
						<p class="description">Wenn deaktiviert, können auch Gäste Produkte einreichen.</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="notify_email">Benachrichtigungs-E-Mail</label></th>
				<td>
					<input type="email" id="notify_email" name="notify_email" class="regular-text"
					       value="<?= esc_attr( $s['notify_email'] ?? get_option( 'admin_email' ) ) ?>"
					       placeholder="admin@example.com" />
					<p class="description">Bei neuen Einreichungen wird diese Adresse benachrichtigt. Leer lassen für keine Benachrichtigung.</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="max_upload_mb">Max. Dateigröße für Fotos (MB)</label></th>
				<td>
					<input type="number" id="max_upload_mb" name="max_upload_mb" class="small-text"
					       value="<?= esc_attr( $s['max_upload_mb'] ?? 5 ) ?>"
					       min="1" max="20" />
					<p class="description">Maximale Größe für Produktfotos und Zutatenlisten-Fotos (1–20 MB).</p>
				</td>
			</tr>

			<tr>
				<th scope="row">Vorläufige Zutaten-Hinweise</th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="show_hints" value="1"
							       <?= ! empty( $s['show_hints'] ) ? 'checked' : '' ?> />
							Automatische Hinweise für gelbe Produkte anzeigen
						</label>
						<p class="description">
							Zeigt dem Nutzer beim Einreichen vorläufige Hinweise (z.B. „Enthält möglicherweise Gelatine").
							Diese ändern die Ampelfarbe <strong>nicht</strong> – rein informativ.
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="scanner_height">Scanner-Höhe</label></th>
				<td>
					<input type="text" id="scanner_height" name="scanner_height" class="small-text"
					       value="<?= esc_attr( $s['scanner_height'] ?? '400px' ) ?>"
					       placeholder="400px" />
					<p class="description">Höhe des Kamera-Viewfinders im Frontend. Beispiel: <code>400px</code>, <code>60vh</code></p>
				</td>
			</tr>

		</table>

		<?php submit_button( '💾 Einstellungen speichern', 'primary large', 'nazarener_save_settings' ); ?>
	</form>

	<hr>

	<h2>Shortcode-Referenz</h2>
	<table class="widefat">
		<thead>
			<tr><th>Shortcode</th><th>Beschreibung</th></tr>
		</thead>
		<tbody>
			<tr>
				<td><code>[nazarener_scanner]</code></td>
				<td>Bettet den vollständigen Scanner mit Ergebnis- und Einreichungsformular ein.</td>
			</tr>
			<tr>
				<td><code>[nazarener_scanner height="500px"]</code></td>
				<td>Scanner mit benutzerdefinierter Höhe.</td>
			</tr>
		</tbody>
	</table>

	<hr>

	<h2>Datenbank-Informationen</h2>
	<?php
	global $wpdb;
	$p_count = NazarenerScan_Database::count_products();
	$s_count = NazarenerScan_Database::count_submissions();
	?>
	<p>
		<strong>Produkte in DB:</strong> <?= esc_html( $p_count ) ?> &nbsp;|&nbsp;
		<strong>Einreichungen in DB:</strong> <?= esc_html( $s_count ) ?> &nbsp;|&nbsp;
		<strong>Plugin-Version:</strong> <?= esc_html( NAZARENER_SCAN_VERSION ) ?> &nbsp;|&nbsp;
		<strong>DB-Version:</strong> <?= esc_html( get_option( 'nazarener_scan_db_version', '–' ) ) ?>
	</p>
</div>
