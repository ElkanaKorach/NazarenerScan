<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Handle form submission
$results      = null;
$lookup_email = '';
if ( isset( $_POST['ns_status_lookup'] ) ) {
	check_admin_referer( 'ns_status_lookup', 'ns_status_nonce' );
	$lookup_email = sanitize_email( wp_unslash( $_POST['ns_lookup_email'] ?? '' ) );
	if ( is_email( $lookup_email ) ) {
		$results = NazarenerScan_Database::get_submissions( [
			'email'    => $lookup_email,
			'per_page' => 20,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		] );
	}
}
?>
<div class="ns-status-wrap">
	<h2 class="ns-title">📋 Meine Einreichungen</h2>
	<p>Gib deine E-Mail-Adresse ein, um den Status deiner Einreichungen zu prüfen.</p>

	<form method="post" class="ns-status-form">
		<?php wp_nonce_field( 'ns_status_lookup', 'ns_status_nonce' ); ?>
		<div class="ns-manual-row">
			<input type="email" name="ns_lookup_email" class="ns-input"
			       value="<?= esc_attr( $lookup_email ) ?>"
			       placeholder="Deine E-Mail-Adresse" required inputmode="email" />
			<button type="submit" name="ns_status_lookup" class="ns-btn ns-btn--primary">Suchen</button>
		</div>
	</form>

	<?php if ( $results !== null ): ?>
		<?php if ( empty( $results ) ): ?>
			<div class="ns-info-banner" style="margin-top:20px">
				Keine Einreichungen für diese E-Mail-Adresse gefunden.
			</div>
		<?php else: ?>
			<div class="ns-status-list">
				<?php foreach ( $results as $sub ):
					$status_config = match ( $sub->status ) {
						'pending'  => [ 'class' => 'yellow', 'label' => '🟡 Wird geprüft' ],
						'approved' => [ 'class' => 'green',  'label' => '🟢 Freigegeben' ],
						'rejected' => [ 'class' => 'red',    'label' => '🔴 Abgelehnt' ],
						default    => [ 'class' => 'yellow', 'label' => $sub->status ],
					};
					$product = NazarenerScan_Database::get_product_by_barcode( $sub->barcode );
				?>
				<div class="ns-status-card">
					<div class="ns-status-card__header">
						<span class="ns-badge ns-badge--<?= esc_attr( $status_config['class'] ) ?>">
							<?= esc_html( $status_config['label'] ) ?>
						</span>
						<code class="ns-status-card__barcode"><?= esc_html( $sub->barcode ) ?></code>
					</div>

					<?php if ( $sub->product_name ): ?>
						<div class="ns-status-card__name"><?= esc_html( $sub->product_name ) ?></div>
					<?php endif; ?>

					<div class="ns-status-card__meta">
						Eingereicht: <?= esc_html( date_i18n( 'd. F Y', strtotime( $sub->created_at ) ) ) ?>
						<?php if ( $sub->reviewed_at ): ?>
							· Geprüft: <?= esc_html( date_i18n( 'd. F Y', strtotime( $sub->reviewed_at ) ) ) ?>
						<?php endif; ?>
					</div>

					<?php if ( $sub->status === 'approved' && $product ): ?>
						<div class="ns-status-card__result">
							Ergebnis:
							<?php if ( $product->status === 'green' ): ?>
								<strong style="color:#2E7D32">🟢 Erlaubt</strong>
							<?php else: ?>
								<strong style="color:#C62828">🔴 Nicht erlaubt</strong>
								<?php if ( $product->forbidden_ingredients ): ?>
									– <?= esc_html( $product->forbidden_ingredients ) ?>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $sub->reviewer_notes ): ?>
						<div class="ns-status-card__notes">
							<strong>Notiz:</strong> <?= esc_html( $sub->reviewer_notes ) ?>
						</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
