<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Own spam protection without any external CAPTCHA service.
 * Uses three layers: honeypot field, form-timing check, and a simple
 * server-side math challenge (for maximum bot resistance).
 */
class NazarenerScan_Captcha {

	private const TOKEN_META = 'ns_captcha_token';
	private const MIN_SECONDS = 2;     // Form must be open at least this long
	private const MAX_SECONDS = 3600;  // Token expires after 1 hour

	// ── Token generation ──────────────────────────────────────────────────────

	/**
	 * Generate a signed captcha token with embedded answer + timestamp.
	 * Returns [ 'token' => string, 'question' => string, 'answer_hash' => string ]
	 */
	public static function generate(): array {
		$a        = rand( 2, 9 );
		$b        = rand( 1, 9 );
		$answer   = $a + $b;
		$ts       = time();
		$secret   = wp_salt( 'nonce' );
		$hash     = hash_hmac( 'sha256', "$answer|$ts", $secret );
		$token    = base64_encode( "$hash|$ts|$answer" );

		return [
			'token'    => $token,
			'question' => sprintf( '%d + %d = ?', $a, $b ),
		];
	}

	/**
	 * Verify all three spam-protection layers.
	 * Returns true if valid, false (with $error set) if blocked.
	 */
	public static function verify(
		string $token,
		string $user_answer,
		string $honeypot,
		string $form_time,
		string &$error = ''
	): bool {
		// Layer 1: Honeypot
		if ( $honeypot !== '' ) {
			$error = 'Spam erkannt.';
			return false;
		}

		// Layer 2: Timing
		$elapsed = time() - (int) $form_time;
		if ( $elapsed < self::MIN_SECONDS ) {
			$error = 'Bitte das Formular vollständig ausfüllen.';
			return false;
		}
		if ( $elapsed > self::MAX_SECONDS ) {
			$error = 'Sicherheits-Token abgelaufen. Bitte Seite neu laden.';
			return false;
		}

		// Layer 3: Math captcha
		$parts = explode( '|', base64_decode( $token ), 3 );
		if ( count( $parts ) !== 3 ) {
			$error = 'Ungültiges Token.';
			return false;
		}
		[ $stored_hash, $ts, $expected_answer ] = $parts;
		$secret        = wp_salt( 'nonce' );
		$expected_hash = hash_hmac( 'sha256', "$expected_answer|$ts", $secret );

		if ( ! hash_equals( $expected_hash, $stored_hash ) ) {
			$error = 'Ungültiges Token.';
			return false;
		}
		if ( (int) $user_answer !== (int) $expected_answer ) {
			$error = 'Falsche Antwort bei der Sicherheitsfrage.';
			return false;
		}

		return true;
	}

	// ── HTML output ───────────────────────────────────────────────────────────

	/**
	 * Output hidden fields + visible math challenge.
	 * Call inside a <form> element.
	 */
	public static function render_fields(): void {
		$captcha = self::generate();
		?>
		<!-- Honeypot (must stay empty) -->
		<div style="position:absolute;left:-9999px;aria-hidden:true">
			<label>Website <input type="text" name="ns_website" autocomplete="off" tabindex="-1" /></label>
		</div>
		<input type="hidden" name="ns_form_time" value="<?= esc_attr( (string) time() )" />
		<input type="hidden" name="ns_captcha_token" value="<?= esc_attr( $captcha['token'] ) ?>" />
		<div class="ns-captcha-field">
			<label class="ns-label" for="ns_captcha_answer">
				Sicherheitsfrage: <strong><?= esc_html( $captcha['question'] ) ?></strong>
			</label>
			<input type="number" id="ns_captcha_answer" name="ns_captcha_answer"
			       class="ns-input ns-input--captcha" placeholder="Antwort" required
			       inputmode="numeric" autocomplete="off" min="0" max="99" />
		</div>
		<?php
	}

	/**
	 * Read & verify POST fields. Sets $error on failure.
	 */
	public static function verify_post( string &$error = '' ): bool {
		return self::verify(
			sanitize_text_field( wp_unslash( $_POST['ns_captcha_token']  ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['ns_captcha_answer'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['ns_website']        ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['ns_form_time']      ?? '0' ) ),
			$error
		);
	}
}
