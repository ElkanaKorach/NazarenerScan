<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php $settings = get_option( 'nazarener_scan_settings', [] ); ?>
<div id="ns-app" class="ns-app" data-nonce="<?= esc_attr( wp_create_nonce( 'nazarener_frontend' ) ) ?>">

	<!-- ── Step 1: Scanner ── -->
	<div id="ns-step-scan" class="ns-step ns-step--active">
		<div class="ns-scanner-header">
			<h2 class="ns-title">🔍 Produkt scannen</h2>
			<p class="ns-subtitle">Halte die Kamera auf den Barcode oder gib ihn manuell ein.</p>
		</div>

		<div id="ns-reader" style="height:<?= esc_attr( $settings['scanner_height'] ?? '400px' ) ?>"></div>

		<div class="ns-manual-row">
			<input type="text" id="ns-barcode-input" class="ns-input" placeholder="Barcode manuell eingeben…" inputmode="numeric" />
			<button id="ns-barcode-submit" class="ns-btn ns-btn--primary">Suchen</button>
		</div>
	</div>

	<!-- ── Step 2: Result ── -->
	<div id="ns-step-result" class="ns-step">
		<button class="ns-back-btn" id="ns-back-to-scan">← Neu scannen</button>

		<!-- Green result -->
		<div id="ns-result-green" class="ns-result" style="display:none">
			<div class="ns-traffic-light ns-traffic-light--green">
				<div class="ns-tl-dot"></div>
				<div class="ns-tl-text">
					<strong>🟢 Erlaubt</strong>
					<span>Produkt ist geprüft und unbedenklich nach biblischen Maßstäben.</span>
				</div>
			</div>
			<div class="ns-product-card">
				<div class="ns-product-name" id="ns-result-name"></div>
				<div class="ns-product-brand" id="ns-result-brand"></div>
				<div class="ns-product-barcode" id="ns-result-barcode-green"></div>
				<div class="ns-product-notes" id="ns-result-notes"></div>
			</div>
		</div>

		<!-- Red result -->
		<div id="ns-result-red" class="ns-result" style="display:none">
			<div class="ns-traffic-light ns-traffic-light--red">
				<div class="ns-tl-dot"></div>
				<div class="ns-tl-text">
					<strong>🔴 Nicht erlaubt</strong>
					<span>Produkt enthält eindeutig verbotene Bestandteile.</span>
				</div>
			</div>
			<div class="ns-product-card ns-product-card--red">
				<div class="ns-product-name" id="ns-result-name-red"></div>
				<div class="ns-product-brand" id="ns-result-brand-red"></div>
				<div id="ns-forbidden-box" class="ns-forbidden-box" style="display:none">
					<strong>Verbotene Bestandteile:</strong>
					<div id="ns-forbidden-list"></div>
				</div>
				<div class="ns-product-barcode" id="ns-result-barcode-red"></div>
			</div>
		</div>

		<!-- Yellow result -->
		<div id="ns-result-yellow" class="ns-result" style="display:none">
			<div class="ns-traffic-light ns-traffic-light--yellow">
				<div class="ns-tl-dot"></div>
				<div class="ns-tl-text">
					<strong>🟡 Nicht im System</strong>
					<span>Produkt wurde noch nicht geprüft und ist nicht in der Datenbank.</span>
				</div>
			</div>

			<div class="ns-product-card">
				<p><strong>Dieses Produkt ist noch nicht im System.</strong></p>
				<p>Du kannst es jetzt zur Prüfung einreichen. Unser Team überprüft die Zutaten nach biblischen Maßstäben.</p>
				<div class="ns-product-barcode" id="ns-result-barcode-yellow"></div>
			</div>

			<div id="ns-already-submitted" class="ns-info-banner" style="display:none">
				⏳ Dieses Produkt wurde bereits eingereicht und wird gerade geprüft.
			</div>

			<button id="ns-open-submit" class="ns-btn ns-btn--primary ns-btn--large">
				📤 Zur Prüfung einreichen
			</button>
		</div>
	</div>

	<!-- ── Step 3: Submit form ── -->
	<div id="ns-step-submit" class="ns-step">
		<button class="ns-back-btn" id="ns-back-to-result">← Zurück</button>
		<h2 class="ns-title">Produkt einreichen</h2>

		<form id="ns-submit-form" class="ns-form" enctype="multipart/form-data">
			<input type="hidden" id="ns-submit-barcode" name="barcode" />

			<!-- Barcode confirmation -->
			<div class="ns-field">
				<label class="ns-label">✅ Barcode bestätigt</label>
				<div class="ns-barcode-confirm" id="ns-barcode-display"></div>
			</div>

			<!-- Product name -->
			<div class="ns-field">
				<label class="ns-label" for="ns-product-name">Produktname (optional)</label>
				<input type="text" id="ns-product-name" name="product_name" class="ns-input" placeholder="z.B. Haribo Goldbären" />
			</div>

			<!-- Product photo -->
			<div class="ns-field">
				<label class="ns-label">📸 Foto vom Produkt <span class="ns-recommended">empfohlen</span></label>
				<div class="ns-photo-upload" id="ns-product-photo-zone">
					<input type="file" id="ns-product-photo" name="product_photo" accept="image/*" capture="environment" class="ns-file-input" />
					<label for="ns-product-photo" class="ns-photo-label">
						<span class="ns-photo-icon">📷</span>
						<span class="ns-photo-text">Produktfoto aufnehmen / wählen</span>
					</label>
					<img id="ns-product-photo-preview" class="ns-photo-preview" style="display:none" alt="Produktfoto" />
					<button type="button" class="ns-remove-photo" id="ns-remove-product-photo" style="display:none">✕ Entfernen</button>
				</div>
			</div>

			<!-- Ingredients photo -->
			<div class="ns-field">
				<label class="ns-label">📋 Foto der Zutatenliste <span class="ns-recommended">empfohlen</span></label>
				<div class="ns-photo-upload" id="ns-ingredients-photo-zone">
					<input type="file" id="ns-ingredients-photo" name="ingredients_photo" accept="image/*" capture="environment" class="ns-file-input" />
					<label for="ns-ingredients-photo" class="ns-photo-label">
						<span class="ns-photo-icon">📄</span>
						<span class="ns-photo-text">Zutatenliste fotografieren / wählen</span>
					</label>
					<img id="ns-ingredients-photo-preview" class="ns-photo-preview" style="display:none" alt="Zutatenliste" />
					<button type="button" class="ns-remove-photo" id="ns-remove-ingredients-photo" style="display:none">✕ Entfernen</button>
				</div>
			</div>

			<!-- Ingredients text -->
			<div class="ns-field">
				<label class="ns-label" for="ns-ingredients-text">Zutaten eintippen <span class="ns-optional">(optional – für Vorab-Hinweise)</span></label>
				<textarea id="ns-ingredients-text" name="ingredients_text" class="ns-textarea" rows="4"
				          placeholder="z.B. Zucker, Gelatine, Aroma, Wasser…"></textarea>
			</div>

			<!-- Hints banner (shown automatically) -->
			<div id="ns-hints-banner" class="ns-hints-banner" style="display:none">
				<div class="ns-hints-header">⚠️ Vorläufige Hinweise (keine offizielle Bewertung)</div>
				<ul id="ns-hints-list" class="ns-hints-list"></ul>
				<div class="ns-hints-footer">Diese Hinweise ersetzen keine manuelle Prüfung. Das Produkt bleibt 🟡 Gelb bis zur Freigabe.</div>
			</div>

			<!-- Submitter info -->
			<div class="ns-field">
				<label class="ns-label" for="ns-submitter-name">Dein Name (optional)</label>
				<input type="text" id="ns-submitter-name" name="submitter_name" class="ns-input" placeholder="Anonym" />
			</div>
			<div class="ns-field">
				<label class="ns-label" for="ns-submitter-email">E-Mail (optional)</label>
				<input type="email" id="ns-submitter-email" name="submitter_email" class="ns-input" placeholder="für Rückmeldung" />
			</div>

			<button type="submit" id="ns-submit-btn" class="ns-btn ns-btn--primary ns-btn--large ns-btn--full">
				📤 Zur Prüfung einreichen
			</button>

			<p class="ns-submit-disclaimer">
				Das Produkt bleibt 🟡 Gelb, bis unser Team es manuell prüft. Einreichungen werden nicht automatisch bewertet.
			</p>
		</form>
	</div>

	<!-- ── Step 4: Success ── -->
	<div id="ns-step-success" class="ns-step">
		<div class="ns-success-state">
			<div class="ns-success-icon">✅</div>
			<h2 class="ns-success-title">Erfolgreich eingereicht!</h2>
			<p class="ns-success-body">
				Danke! Das Produkt wurde zur manuellen Prüfung übergeben.
				Sobald es geprüft ist, erscheint es in der Datenbank als 🟢 Grün oder 🔴 Rot.
			</p>
			<button id="ns-scan-again" class="ns-btn ns-btn--primary">🔍 Neues Produkt scannen</button>
		</div>
	</div>

</div><!-- #ns-app -->
