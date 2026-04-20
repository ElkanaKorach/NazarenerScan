<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$product_id   = (int) ( $_GET['product_id'] ?? $_POST['product_id'] ?? 0 );
$product      = $product_id ? NazarenerScan_Database::get_product( $product_id ) : null;
$extra_barcodes = $product_id ? NazarenerScan_Database::get_extra_barcodes( $product_id ) : [];
$changelog    = $product_id ? NazarenerScan_Database::get_changelog( [ 'object_type' => 'product', 'object_id' => $product_id, 'per_page' => 5 ] ) : [];
$is_new       = ! $product;
// Pre-fill barcode from analytics "Hinzufügen" link
$prefill_bc   = sanitize_text_field( $_GET['barcode'] ?? '' );
?>
<div class="wrap ns-wrap">
	<h1>
		<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products' ) ) ?>" class="ns-back-link">← Produkte</a>
		<?= $is_new ? 'Neues Produkt' : 'Produkt bearbeiten' ?>
	</h1>

	<?php if ( isset( $_GET['saved'] ) ): ?>
		<div class="notice notice-success is-dismissible"><p>✅ Produkt gespeichert.</p></div>
	<?php endif; ?>

	<div class="ns-edit-grid">
		<div class="ns-edit-main">
			<form method="post" class="ns-edit-form">
				<?php wp_nonce_field( 'nazarener_product_nonce' ); ?>
				<input type="hidden" name="page" value="nazarener-products" />
				<?php if ( $product_id ): ?>
					<input type="hidden" name="product_id" value="<?= esc_attr( $product_id ) ?>" />
				<?php endif; ?>

				<div class="ns-form-section">
					<h3 class="ns-section-title">Grunddaten</h3>

					<table class="form-table">
						<tr>
							<th><label for="barcode">Barcode <span class="required">*</span></label></th>
							<td>
								<input type="text" id="barcode" name="barcode" class="regular-text"
								       value="<?= esc_attr( $product->barcode ?? $prefill_bc ) ?>"
								       placeholder="z.B. 4006381333931" required
								       <?= $product ? 'readonly' : '' ?> />
								<?php if ( $product ): ?><p class="description">Nicht änderbar nach Anlage.</p><?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><label for="name">Produktname <span class="required">*</span></label></th>
							<td><input type="text" id="name" name="name" class="regular-text" value="<?= esc_attr( $product->name ?? '' ) ?>" required /></td>
						</tr>
						<tr>
							<th><label for="brand">Marke</label></th>
							<td><input type="text" id="brand" name="brand" class="regular-text" value="<?= esc_attr( $product->brand ?? '' ) ?>" placeholder="z.B. Haribo" /></td>
						</tr>
					</table>
				</div>

				<div class="ns-form-section">
					<h3 class="ns-section-title">Ampel-Status <span class="required">*</span></h3>
					<div class="ns-traffic-choice">
						<label class="ns-radio-label ns-radio-green">
							<input type="radio" name="status" value="green" <?= ( ( $product->status ?? 'green' ) === 'green' ) ? 'checked' : '' ?> />
							<span class="ns-traffic-dot ns-traffic-dot--green"></span>
							<div>
								<strong>🟢 Grün – Erlaubt</strong>
								<span class="description">Geprüft, keine biblisch verbotenen Bestandteile.</span>
							</div>
						</label>
						<label class="ns-radio-label ns-radio-red">
							<input type="radio" name="status" value="red" <?= ( ( $product->status ?? '' ) === 'red' ) ? 'checked' : '' ?> />
							<span class="ns-traffic-dot ns-traffic-dot--red"></span>
							<div>
								<strong>🔴 Rot – Nicht erlaubt</strong>
								<span class="description">Enthält eindeutig verbotene Bestandteile.</span>
							</div>
						</label>
					</div>
				</div>

				<div class="ns-form-section" id="section-forbidden" style="<?= ( ( $product->status ?? 'green' ) !== 'red' ) ? 'display:none' : '' ?>">
					<h3 class="ns-section-title">Verbotene Zutaten</h3>
					<table class="form-table">
						<tr>
							<th><label for="forbidden_ingredients">Verbotene Bestandteile</label></th>
							<td>
								<textarea id="forbidden_ingredients" name="forbidden_ingredients" class="large-text" rows="3"
								          placeholder="z.B. Gelatine (Schwein), Schweineschmalz"><?= esc_textarea( $product->forbidden_ingredients ?? '' ) ?></textarea>
								<p class="description">Werden dem Nutzer beim Scan-Ergebnis angezeigt.</p>
							</td>
						</tr>
						<tr>
							<th><label for="biblical_reference">Biblische Referenz</label></th>
							<td>
								<input type="text" id="biblical_reference" name="biblical_reference" class="large-text"
								       value="<?= esc_attr( $product->biblical_reference ?? '' ) ?>"
								       placeholder="z.B. 3. Mose 11:7 – Schwein ist unrein" />
								<p class="description">Wird dem Nutzer als Begründung angezeigt.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="ns-form-section">
					<h3 class="ns-section-title">Weitere Barcodes</h3>
					<p class="description">Gleiche Produkt, verschiedene Verpackungsgrößen oder Länderversionen. Einer pro Zeile.</p>
					<textarea name="extra_barcodes" class="large-text" rows="3"
					          placeholder="4006381333930&#10;4006381333929"><?= esc_textarea( implode( "\n", array_column( $extra_barcodes, 'barcode' ) ) ) ?></textarea>
				</div>

				<div class="ns-form-section">
					<h3 class="ns-section-title">Interne Notizen</h3>
					<textarea id="notes" name="notes" class="large-text" rows="3"
					          placeholder="Optionale Hinweise für das Team"><?= esc_textarea( $product->notes ?? '' ) ?></textarea>
				</div>

				<div class="ns-form-actions">
					<input type="submit" name="nazarener_save_product" class="button button-primary button-large" value="💾 Speichern" />
					<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products' ) ) ?>" class="button button-large">Abbrechen</a>
					<?php if ( $product ): ?>
					<div class="ns-delete-zone">
						<form method="post" onsubmit="return confirm('Produkt wirklich löschen?')">
							<?php wp_nonce_field( 'nazarener_delete_product' ); ?>
							<input type="hidden" name="page" value="nazarener-products" />
							<input type="hidden" name="product_id" value="<?= esc_attr( $product_id ) ?>" />
							<input type="submit" name="nazarener_delete_product" class="button ns-delete-btn" value="🗑️ Löschen" />
						</form>
					</div>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<!-- Sidebar -->
		<div class="ns-edit-sidebar">

			<!-- Product image -->
			<div class="ns-sidebar-card">
				<h3>Produktbild</h3>
				<?php
				$img_id = (int) ( $product->product_image_id ?? 0 );
				$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
				?>
				<div id="ns-product-image-preview">
					<?php if ( $img_url ): ?>
						<img src="<?= esc_url( $img_url ) ?>" style="max-width:100%;border-radius:6px;margin-bottom:8px" />
					<?php else: ?>
						<div class="ns-image-placeholder">Kein Bild</div>
					<?php endif; ?>
				</div>
				<form method="post" id="ns-image-form">
					<?php wp_nonce_field( 'nazarener_product_nonce' ); ?>
					<input type="hidden" name="page" value="nazarener-products" />
					<input type="hidden" name="product_id" value="<?= esc_attr( $product_id ) ?>" />
					<input type="hidden" name="barcode"  value="<?= esc_attr( $product->barcode ?? '' ) ?>" />
					<input type="hidden" name="name"     value="<?= esc_attr( $product->name ?? '' ) ?>" />
					<input type="hidden" name="brand"    value="<?= esc_attr( $product->brand ?? '' ) ?>" />
					<input type="hidden" name="status"   value="<?= esc_attr( $product->status ?? 'green' ) ?>" />
					<input type="hidden" id="ns-image-id-input" name="product_image_id" value="<?= esc_attr( $img_id ) ?>" />
					<button type="button" id="ns-select-image" class="button">🖼️ Bild auswählen</button>
					<?php if ( $img_id ): ?>
						<button type="button" id="ns-remove-image" class="button">✕ Entfernen</button>
					<?php endif; ?>
				</form>
			</div>

			<!-- Changelog sidebar -->
			<?php if ( $changelog ): ?>
			<div class="ns-sidebar-card">
				<h3>Letzte Änderungen</h3>
				<ul class="ns-changelog-list">
					<?php foreach ( $changelog as $entry ): ?>
					<li>
						<strong><?= esc_html( $entry->action ) ?></strong>
						<?php if ( $entry->user_name ): ?>
							von <?= esc_html( $entry->user_name ) ?>
						<?php endif; ?>
						<br><small><?= esc_html( date_i18n( 'd.m.Y H:i', strtotime( $entry->created_at ) ) ) ?></small>
					</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-log' ) ) ?>" class="button button-small">Vollständiges Log</a>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
document.querySelectorAll('input[name="status"]').forEach(function(r) {
	r.addEventListener('change', function() {
		document.getElementById('section-forbidden').style.display = this.value === 'red' ? '' : 'none';
	});
});

// WP Media uploader for product image
jQuery(function($) {
	var frame;
	$('#ns-select-image').on('click', function(e) {
		e.preventDefault();
		if (frame) { frame.open(); return; }
		frame = wp.media({ title: 'Produktbild wählen', button: { text: 'Bild verwenden' }, multiple: false });
		frame.on('select', function() {
			var att = frame.state().get('selection').first().toJSON();
			$('#ns-image-id-input').val(att.id);
			$('#ns-product-image-preview').html('<img src="' + att.url + '" style="max-width:100%;border-radius:6px;margin-bottom:8px" />');
			$('#ns-image-form').find('[name="nazarener_save_product"]').length || $('<input type="submit" name="nazarener_save_product" value="💾 Speichern" class="button button-primary" style="margin-top:8px">').appendTo('#ns-image-form');
		});
		frame.open();
	});
	$('#ns-remove-image').on('click', function() {
		$('#ns-image-id-input').val('');
		$('#ns-product-image-preview').html('<div class="ns-image-placeholder">Kein Bild</div>');
	});
});
</script>
