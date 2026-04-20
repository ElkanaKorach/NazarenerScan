<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$settings       = get_option( 'nazarener_scan_settings', [] );
$scanner_height = esc_attr( $atts['height'] ?? $settings['scanner_height'] ?? '420px' );
$captcha        = NazarenerScan_Captcha::generate();
$privacy_url    = get_privacy_policy_url();
?>
<div id="ns-app" class="ns-app">

	<!-- ── Step 1: Scanner ── -->
	<div id="ns-step-scan" class="ns-step ns-step--active">
		<div class="ns-scanner-header">
			<h2 class="ns-title">🔍 Produkt scannen</h2>
			<p class="ns-subtitle">Kamera auf Barcode halten oder manuell eingeben.</p>
		</div>

		<div id="ns-reader" style="height:<?= $scanner_height ?>"></div>

		<div class="ns-manual-row">
			<input type="text" id="ns-barcode-input" class="ns-input"
			       placeholder="Barcode eingeben…" inputmode="decimal" autocomplete="off" />
			<button id="ns-barcode-submit" class="ns-btn ns-btn--primary">Suchen</button>
		</div>

		<!-- Scan history -->
		<div id="ns-history" class="ns-history" style="display:none">
			<div class="ns-history-title">Zuletzt gescannt</div>
			<div id="ns-history-list" class="ns-history-list"></div>
		</div>
	</div>

	<!-- ── Step 2: Result ── -->
	<div id="ns-step-result" class="ns-step">
		<button class="ns-back-btn" id="ns-back-to-scan">← Neu scannen</button>
		<div id="ns-result-loading" class="ns-loading">
			<div class="ns-spinner"></div><span>Suche in Datenbank…</span>
		</div>

		<!-- Green -->
		<div id="ns-result-green" class="ns-result" style="display:none">
			<div class="ns-traffic-light ns-traffic-light--green">
				<div class="ns-tl-dot"></div>
				<div class="ns-tl-text">
					<strong>🟢 Erlaubt</strong>
					<span>Geprüft und unbedenklich nach biblischen Maßstäben.</span>
				</div>
			</div>
			<div class="ns-product-card">
				<div id="ns-g-image" class="ns-product-image" style="display:none"></div>
				<div class="ns-product-name"  id="ns-g-name"></div>
				<div class="ns-product-brand" id="ns-g-brand"></div>
				<div class="ns-product-notes" id="ns-g-notes"></div>
				<div class="ns-product-barcode" id="ns-g-barcode"></div>
			</div>
		</div>

		<!-- Red -->
		<div id="ns-result-red" class="ns-result" style="display:none">
			<div class="ns-traffic-light ns-traffic-light--red">
				<div class="ns-tl-dot"></div>
				<div class="ns-tl-text">
					<strong>🔴 Nicht erlaubt</strong>
					<span>Enthält eindeutig verbotene Bestandteile.</span>
				</div>
			</div>
			<div class="ns-product-card ns-product-card--red">
				<div class="ns-product-name"  id="ns-r-name"></div>
				<div class="ns-product-brand" id="ns-r-brand"></div>
				<div id="ns-r-forbidden-box" class="ns-forbidden-box" style="display:none">
					<strong>Verbotene Bestandteile</strong>
					<div id="ns-r-forbidden"></div>
				</div>
				<div id="ns-r-biblical" class="ns-biblical-ref" style="display:none"></div>
				<div class="ns-product-barcode" id="ns-r-barcode"></div>
			</div>
		</div>

		<!-- Yellow -->
		<div id="ns-result-yellow" class="ns-result" style="display:none">
			<div class="ns-traffic-light ns-traffic-light--yellow">
				<div class="ns-tl-dot"></div>
				<div class="ns-tl-text">
					<strong>🟡 Nicht im System</strong>
					<span>Produkt noch nicht in der Datenbank – kein Urteil.</span>
				</div>
			</div>
			<div class="ns-product-card">
				<p><strong>Dieses Produkt ist noch nicht geprüft.</strong></p>
				<p>Du kannst es jetzt zur Prüfung einreichen. Unser Team bewertet die Zutaten nach biblischen Maßstäben und trägt das Ergebnis als 🟢 oder 🔴 ein.</p>
				<div class="ns-product-barcode" id="ns-y-barcode"></div>
			</div>

			<div id="ns-already-submitted" class="ns-info-banner" style="display:none">
				⏳ Dieses Produkt wurde bereits eingereicht und wird gerade geprüft.
			</div>

			<!-- Notify me (visible when not submitted) -->
			<div id="ns-notify-box" class="ns-notify-box" style="display:none">
				<p class="ns-notify-label">🔔 Benachrichtigung erhalten, wenn das Produkt geprüft wird:</p>
				<div class="ns-notify-row">
					<input type="email" id="ns-notify-email" class="ns-input" placeholder="Deine E-Mail-Adresse" inputmode="email" />
					<button id="ns-notify-btn" class="ns-btn ns-btn--secondary">Benachrichtigen</button>
				</div>
				<div id="ns-notify-feedback" class="ns-notify-feedback" style="display:none"></div>
			</div>

			<button id="ns-open-submit" class="ns-btn ns-btn--primary ns-btn--large ns-btn--full" style="display:none">
				📤 Zur Prüfung einreichen
			</button>
		</div>
	</div>

	<!-- ── Step 3: Submit form ── -->
	<div id="ns-step-submit" class="ns-step">
		<button class="ns-back-btn" id="ns-back-to-result">← Zurück zum Ergebnis</button>
		<h2 class="ns-title">Produkt einreichen</h2>

		<form id="ns-submit-form" class="ns-form" enctype="multipart/form-data" novalidate>

			<!-- Honeypot (must stay empty, hidden from real users) -->
			<div style="position:absolute;left:-9999px" aria-hidden="true">
				<input type="text" name="ns_website" autocomplete="off" tabindex="-1" />
			</div>
			<input type="hidden" name="ns_form_time" id="ns-form-time" value="" />
			<input type="hidden" name="ns_captcha_token" id="ns-captcha-token" value="<?= esc_attr( $captcha['token'] ) ?>" />

			<!-- Barcode -->
			<div class="ns-field">
				<label class="ns-label">✅ Barcode bestätigt</label>
				<div class="ns-barcode-confirm" id="ns-barcode-display"></div>
				<input type="hidden" id="ns-submit-barcode" name="barcode" />
			</div>

			<!-- Product name -->
			<div class="ns-field">
				<label class="ns-label" for="ns-product-name">Produktname <span class="ns-optional">(optional)</span></label>
				<input type="text" id="ns-product-name" name="product_name" class="ns-input" placeholder="z.B. Haribo Goldbären" />
			</div>

			<!-- Product photo -->
			<div class="ns-field">
				<label class="ns-label">📸 Foto vom Produkt <span class="ns-recommended">empfohlen</span></label>
				<div class="ns-photo-upload">
					<input type="file" id="ns-product-photo" name="product_photo" accept="image/*" capture="environment" class="ns-file-input" />
					<label for="ns-product-photo" class="ns-photo-label">
						<span class="ns-photo-icon">📷</span>
						<span class="ns-photo-text">Produktfoto aufnehmen / wählen</span>
					</label>
					<img id="ns-product-photo-preview" class="ns-photo-preview" style="display:none" alt="" />
					<button type="button" class="ns-remove-photo" id="ns-remove-product-photo" style="display:none">✕ Entfernen</button>
				</div>
			</div>

			<!-- Ingredients photo -->
			<div class="ns-field">
				<label class="ns-label">📋 Foto der Zutatenliste <span class="ns-recommended">empfohlen</span></label>
				<div class="ns-photo-upload">
					<input type="file" id="ns-ingredients-photo" name="ingredients_photo" accept="image/*" capture="environment" class="ns-file-input" />
					<label for="ns-ingredients-photo" class="ns-photo-label">
						<span class="ns-photo-icon">📄</span>
						<span class="ns-photo-text">Zutatenliste fotografieren / wählen</span>
					</label>
					<img id="ns-ingredients-photo-preview" class="ns-photo-preview" style="display:none" alt="" />
					<button type="button" class="ns-remove-photo" id="ns-remove-ingredients-photo" style="display:none">✕ Entfernen</button>
				</div>
			</div>

			<!-- Ingredients text -->
			<div class="ns-field">
				<label class="ns-label" for="ns-ingredients-text">
					Zutaten eintippen <span class="ns-optional">(optional – für Vorab-Hinweise)</span>
				</label>
				<textarea id="ns-ingredients-text" name="ingredients_text" class="ns-textarea" rows="4"
				          placeholder="z.B. Zucker, Gelatine, Aroma, Wasser…"></textarea>
			</div>

			<!-- Hints (shown automatically when hints are enabled) -->
			<div id="ns-hints-banner" class="ns-hints-banner" style="display:none">
				<div class="ns-hints-header">⚠️ Vorläufige Hinweise (keine offizielle Bewertung)</div>
				<ul id="ns-hints-list" class="ns-hints-list"></ul>
				<div class="ns-hints-footer">
					Diese Hinweise ersetzen keine manuelle Prüfung. Das Produkt bleibt 🟡 Gelb bis zur Freigabe.
				</div>
			</div>

			<!-- Captcha -->
			<div class="ns-field ns-captcha-field">
				<label class="ns-label" for="ns-captcha-answer">
					Sicherheitsfrage: <strong id="ns-captcha-question"><?= esc_html( $captcha['question'] ) ?></strong>
				</label>
				<div class="ns-captcha-row">
					<input type="number" id="ns-captcha-answer" name="ns_captcha_answer"
					       class="ns-input ns-input--captcha" placeholder="Antwort" required
					       inputmode="numeric" autocomplete="off" min="0" max="99" />
					<button type="button" id="ns-captcha-refresh" class="ns-btn-icon" title="Neue Frage">↻</button>
				</div>
			</div>

			<!-- Submitter info -->
			<div class="ns-field">
				<label class="ns-label" for="ns-submitter-name">Dein Name <span class="ns-optional">(optional)</span></label>
				<input type="text" id="ns-submitter-name" name="submitter_name" class="ns-input" placeholder="Anonym" />
			</div>
			<div class="ns-field">
				<label class="ns-label" for="ns-submitter-email">E-Mail <span class="ns-optional">(optional – für Rückmeldung)</span></label>
				<input type="email" id="ns-submitter-email" name="submitter_email" class="ns-input" inputmode="email" placeholder="für Status-Benachrichtigung" />
			</div>

			<!-- DSGVO consent -->
			<div class="ns-field ns-consent-field">
				<label class="ns-consent-label">
					<input type="checkbox" name="privacy_consent" id="ns-privacy-consent" value="1" required />
					<span>
						Ich stimme der
						<?php if ( $privacy_url ): ?>
							<a href="<?= esc_url( $privacy_url ) ?>" target="_blank" rel="noopener">Datenschutzerklärung</a>
						<?php else: ?>
							Datenschutzerklärung
						<?php endif; ?>
						zu. Meine Daten (Barcode, Fotos, E-Mail) werden ausschließlich zur Produktprüfung verwendet. <span class="required">*</span>
					</span>
				</label>
			</div>

			<button type="submit" id="ns-submit-btn" class="ns-btn ns-btn--primary ns-btn--large ns-btn--full">
				📤 Zur Prüfung einreichen
			</button>
			<p id="ns-submit-error" class="ns-submit-error" style="display:none"></p>
			<p class="ns-submit-disclaimer">
				Das Produkt bleibt 🟡 Gelb, bis unser Team es manuell prüft. Keine automatische Bewertung.
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
				Sobald es geprüft ist, erscheint es als 🟢 Grün oder 🔴 Rot.
			</p>
			<p id="ns-success-email-hint" style="display:none;font-size:.9em;color:#616161">
				Du erhältst eine E-Mail, sobald die Prüfung abgeschlossen ist.
			</p>
			<button id="ns-scan-again" class="ns-btn ns-btn--primary">🔍 Neues Produkt scannen</button>
		</div>
	</div>

	<!-- Offline banner -->
	<div id="ns-offline-banner" class="ns-offline-banner" style="display:none">
		📵 Keine Internetverbindung. Bitte Verbindung prüfen und erneut versuchen.
	</div>

</div><!-- #ns-app -->
