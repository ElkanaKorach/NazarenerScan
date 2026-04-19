<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$product_id = (int) ( $_GET['product_id'] ?? 0 );
$product    = $product_id ? NazarenerScan_Database::get_product( $product_id ) : null;
$is_new     = ! $product;
$title      = $is_new ? 'Neues Produkt' : 'Produkt bearbeiten';
?>
<div class="wrap ns-wrap">
	<h1>
		<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products' ) ) ?>" class="ns-back-link">← Produkte</a>
		<?= esc_html( $title ) ?>
	</h1>

	<?php if ( isset( $_GET['saved'] ) ): ?>
		<div class="notice notice-success is-dismissible"><p>✅ Produkt gespeichert.</p></div>
	<?php endif; ?>

	<form method="post" class="ns-edit-form">
		<?php wp_nonce_field( 'nazarener_product_nonce' ); ?>
		<input type="hidden" name="page" value="nazarener-products" />
		<?php if ( $product_id ): ?>
			<input type="hidden" name="product_id" value="<?= esc_attr( $product_id ) ?>" />
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th><label for="barcode">Barcode <span class="required">*</span></label></th>
				<td>
					<input type="text" id="barcode" name="barcode" class="regular-text"
					       value="<?= esc_attr( $product->barcode ?? '' ) ?>"
					       placeholder="z.B. 4006381333931" required
					       <?= $product ? 'readonly' : '' ?> />
					<?php if ( $product ): ?>
						<p class="description">Barcode kann nach dem Anlegen nicht geändert werden.</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="name">Produktname <span class="required">*</span></label></th>
				<td>
					<input type="text" id="name" name="name" class="regular-text"
					       value="<?= esc_attr( $product->name ?? '' ) ?>"
					       placeholder="z.B. Haribo Goldbären" required />
				</td>
			</tr>
			<tr>
				<th><label for="brand">Marke</label></th>
				<td>
					<input type="text" id="brand" name="brand" class="regular-text"
					       value="<?= esc_attr( $product->brand ?? '' ) ?>"
					       placeholder="z.B. Haribo" />
				</td>
			</tr>
			<tr>
				<th><label>Ampel-Status <span class="required">*</span></label></th>
				<td>
					<fieldset>
						<label class="ns-radio-label ns-radio-green">
							<input type="radio" name="status" value="green"
							       <?= ( ( $product->status ?? 'green' ) === 'green' ) ? 'checked' : '' ?> />
							<span class="ns-traffic-dot ns-traffic-dot--green"></span>
							<strong>Grün – Erlaubt</strong>
							<span class="description">Produkt ist geprüft und unbedenklich nach biblischen Maßstäben.</span>
						</label>
						<br>
						<label class="ns-radio-label ns-radio-red">
							<input type="radio" name="status" value="red"
							       <?= ( ( $product->status ?? '' ) === 'red' ) ? 'checked' : '' ?> />
							<span class="ns-traffic-dot ns-traffic-dot--red"></span>
							<strong>Rot – Nicht erlaubt</strong>
							<span class="description">Produkt enthält eindeutig verbotene Bestandteile.</span>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr id="row-forbidden" style="<?= ( ( $product->status ?? 'green' ) !== 'red' ) ? 'display:none' : '' ?>">
				<th><label for="forbidden_ingredients">Verbotene Zutaten</label></th>
				<td>
					<textarea id="forbidden_ingredients" name="forbidden_ingredients" class="large-text" rows="3"
					          placeholder="z.B. Gelatine (Schwein), Schweineschmalz"><?= esc_textarea( $product->forbidden_ingredients ?? '' ) ?></textarea>
					<p class="description">Diese werden dem Nutzer beim Scan-Ergebnis angezeigt.</p>
				</td>
			</tr>
			<tr>
				<th><label for="notes">Interne Notizen</label></th>
				<td>
					<textarea id="notes" name="notes" class="large-text" rows="3"
					          placeholder="Optionale Hinweise für das Team"><?= esc_textarea( $product->notes ?? '' ) ?></textarea>
				</td>
			</tr>
		</table>

		<div class="ns-form-actions">
			<input type="submit" name="nazarener_save_product" class="button button-primary button-large" value="💾 Speichern" />
			<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products' ) ) ?>" class="button button-large">Abbrechen</a>

			<?php if ( $product ): ?>
				<div class="ns-delete-zone">
					<form method="post" onsubmit="return confirm('Produkt wirklich löschen?')">
						<?php wp_nonce_field( 'nazarener_delete_product' ); ?>
						<input type="hidden" name="page" value="nazarener-products" />
						<input type="hidden" name="product_id" value="<?= esc_attr( $product_id ) ?>" />
						<input type="submit" name="nazarener_delete_product" class="button ns-delete-btn" value="🗑️ Produkt löschen" />
					</form>
				</div>
			<?php endif; ?>
		</div>
	</form>
</div>

<script>
document.querySelectorAll('input[name="status"]').forEach(function(radio) {
	radio.addEventListener('change', function() {
		document.getElementById('row-forbidden').style.display = this.value === 'red' ? '' : 'none';
	});
});
</script>
