<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$id  = (int) ( $_GET['id'] ?? 0 );
$sub = $id ? NazarenerScan_Database::get_submission( $id ) : null;
if ( ! $sub ) {
	echo '<div class="wrap"><p>Einreichung nicht gefunden.</p></div>';
	return;
}
$existing = NazarenerScan_Database::get_product_by_barcode( $sub->barcode );
$hints    = $sub->ingredients_text ? NazarenerScan_Hints::analyze( $sub->ingredients_text ) : [];
?>
<div class="wrap ns-wrap">
	<h1>
		<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-submissions' ) ) ?>" class="ns-back-link">← Einreichungen</a>
		Einreichung prüfen
	</h1>

	<div class="ns-review-grid">

		<!-- Left: submission details -->
		<div class="ns-review-details">
			<div class="ns-review-card">
				<h3>Produktinformationen</h3>
				<table class="ns-detail-table">
					<tr><th>Barcode</th><td><code><?= esc_html( $sub->barcode ) ?></code></td></tr>
					<tr><th>Produktname</th><td><?= esc_html( $sub->product_name ?: '–' ) ?></td></tr>
					<tr><th>Eingereicht</th><td><?= esc_html( date_i18n( 'd.m.Y H:i', strtotime( $sub->created_at ) ) ) ?></td></tr>
					<tr><th>Einreicher</th><td>
						<?= esc_html( $sub->submitter_name ?: 'Anonym' ) ?>
						<?php if ( $sub->submitter_email ): ?>
							<br><a href="mailto:<?= esc_attr( $sub->submitter_email ) ?>"><?= esc_html( $sub->submitter_email ) ?></a>
						<?php endif; ?>
					</td></tr>
					<tr><th>IP-Adresse</th><td><?= esc_html( $sub->submitter_ip ?: '–' ) ?></td></tr>
					<tr><th>Status</th><td>
						<?php if ( $sub->status === 'pending' ): ?>
							<span class="ns-badge ns-badge--yellow">🟡 Ausstehend</span>
						<?php elseif ( $sub->status === 'approved' ): ?>
							<span class="ns-badge ns-badge--green">🟢 Genehmigt</span>
						<?php else: ?>
							<span class="ns-badge ns-badge--red">🔴 Abgelehnt</span>
						<?php endif; ?>
					</td></tr>
				</table>
			</div>

			<!-- Ingredients text + hints -->
			<?php if ( $sub->ingredients_text ): ?>
			<div class="ns-review-card">
				<h3>Eingetragene Zutaten</h3>
				<div class="ns-ingredients-text"><?= nl2br( esc_html( $sub->ingredients_text ) ) ?></div>

				<?php if ( $hints ): ?>
				<div class="ns-hints-block">
					<strong>Automatische Hinweise (nicht bindend):</strong>
					<ul>
						<?php foreach ( $hints as $hint ): ?>
						<li class="ns-hint-<?= esc_attr( $hint['type'] ) ?>">
							<?= $hint['type'] === 'warning' ? '⚠️' : 'ℹ️' ?>
							<?= esc_html( $hint['message'] ) ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Photos -->
			<?php if ( $sub->product_photo_url || $sub->ingredients_photo_url ): ?>
			<div class="ns-review-card">
				<h3>Fotos</h3>
				<div class="ns-photos-grid">
					<?php if ( $sub->product_photo_url ): ?>
					<div class="ns-photo">
						<p><strong>Produktfoto</strong></p>
						<a href="<?= esc_url( $sub->product_photo_url ) ?>" target="_blank">
							<img src="<?= esc_url( $sub->product_photo_url ) ?>" alt="Produktfoto" />
						</a>
					</div>
					<?php endif; ?>
					<?php if ( $sub->ingredients_photo_url ): ?>
					<div class="ns-photo">
						<p><strong>Zutatenliste</strong></p>
						<a href="<?= esc_url( $sub->ingredients_photo_url ) ?>" target="_blank">
							<img src="<?= esc_url( $sub->ingredients_photo_url ) ?>" alt="Zutatenliste" />
						</a>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Existing product info -->
			<?php if ( $existing ): ?>
			<div class="ns-review-card ns-review-card--warning">
				<h3>⚠️ Produkt bereits in Datenbank</h3>
				<p>Barcode <code><?= esc_html( $sub->barcode ) ?></code> ist bereits als
					<strong><?= esc_html( $existing->name ) ?></strong>
					mit Status <span class="ns-badge ns-badge--<?= esc_attr( $existing->status ) ?>"><?= $existing->status === 'green' ? '🟢 Erlaubt' : '🔴 Nicht erlaubt' ?></span>
					gespeichert.
				</p>
				<a href="<?= esc_url( admin_url( 'admin.php?page=nazarener-products&action=edit&product_id=' . $existing->id ) ) ?>" class="button">
					Produkt bearbeiten
				</a>
			</div>
			<?php endif; ?>
		</div>

		<!-- Right: review form -->
		<div class="ns-review-panel">
			<?php if ( $sub->status === 'pending' ): ?>
			<div class="ns-review-card ns-review-action-card">
				<h3>Entscheidung treffen</h3>

				<form method="post">
					<?php wp_nonce_field( 'nazarener_review_submission' ); ?>
					<input type="hidden" name="page" value="nazarener-submissions" />
					<input type="hidden" name="submission_id" value="<?= esc_attr( $sub->id ) ?>" />

					<div class="ns-review-choices">
						<label class="ns-choice ns-choice--green">
							<input type="radio" name="review_action" value="approved_green" required />
							<span class="ns-choice-icon">🟢</span>
							<div>
								<strong>Grün freigeben</strong>
								<small>Produkt ist erlaubt – wird sofort in die Datenbank aufgenommen</small>
							</div>
						</label>
						<label class="ns-choice ns-choice--red">
							<input type="radio" name="review_action" value="approved_red" />
							<span class="ns-choice-icon">🔴</span>
							<div>
								<strong>Rot eintragen</strong>
								<small>Produkt ist verboten – wird als Rot in die Datenbank aufgenommen</small>
							</div>
						</label>
						<label class="ns-choice ns-choice--gray">
							<input type="radio" name="review_action" value="rejected" />
							<span class="ns-choice-icon">✖️</span>
							<div>
								<strong>Ablehnen</strong>
								<small>Einreichung verwerfen (Produkt bleibt gelb)</small>
							</div>
						</label>
					</div>

					<div class="ns-review-notes">
						<label for="reviewer_notes"><strong>Interne Notizen (optional)</strong></label>
						<textarea id="reviewer_notes" name="reviewer_notes" rows="3" class="large-text"
						          placeholder="Begründung, Hinweise für das Team…"></textarea>
					</div>

					<div class="ns-review-submit">
						<input type="submit" name="nazarener_review_submission" class="button button-primary button-large" value="✅ Entscheidung speichern" />
					</div>
				</form>
			</div>

			<?php else: ?>
			<div class="ns-review-card">
				<h3>Bereits bewertet</h3>
				<p>Diese Einreichung wurde am
					<?= esc_html( date_i18n( 'd.m.Y', strtotime( $sub->reviewed_at ) ) ) ?>
					bewertet.
				</p>
				<?php if ( $sub->reviewer_notes ): ?>
					<p><strong>Notiz:</strong> <?= esc_html( $sub->reviewer_notes ) ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Delete -->
			<div class="ns-review-card">
				<h3>Einreichung löschen</h3>
				<p class="description">Löscht nur die Einreichung, nicht das Produkt in der Datenbank.</p>
				<form method="post" onsubmit="return confirm('Einreichung wirklich löschen?')">
					<?php wp_nonce_field( 'nazarener_delete_submission' ); ?>
					<input type="hidden" name="page" value="nazarener-submissions" />
					<input type="hidden" name="submission_id" value="<?= esc_attr( $sub->id ) ?>" />
					<input type="submit" name="nazarener_delete_submission" class="button ns-delete-btn" value="🗑️ Einreichung löschen" />
				</form>
			</div>
		</div>
	</div>
</div>
