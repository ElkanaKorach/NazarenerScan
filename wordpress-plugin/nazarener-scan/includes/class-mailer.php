<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Mailer {

	// ── Send methods ──────────────────────────────────────────────────────────

	public static function submission_received( object $submission ): bool {
		if ( ! $submission->submitter_email ) return false;

		$subject = '[NazarenerScan] Deine Einreichung wurde empfangen';
		$body    = self::wrap_template(
			'Einreichung empfangen',
			'🟡',
			'Deine Einreichung ist eingegangen!',
			sprintf(
				'<p>Vielen Dank für deine Einreichung des Produkts <strong>%s</strong> (Barcode: <code>%s</code>).</p>
				 <p>Unser Team wird das Produkt nach biblischen Maßstäben prüfen und es als <strong style="color:#2E7D32">🟢 Erlaubt</strong> oder <strong style="color:#C62828">🔴 Nicht erlaubt</strong> eintragen.</p>
				 <p>Du wirst per E-Mail benachrichtigt, sobald die Prüfung abgeschlossen ist.</p>',
				esc_html( $submission->product_name ?: 'Unbekanntes Produkt' ),
				esc_html( $submission->barcode )
			),
			'#E65100',
			'#FFF3E0'
		);

		return self::send( $submission->submitter_email, $subject, $body );
	}

	public static function submission_approved_green( object $submission, string $reviewer_notes = '' ): bool {
		if ( ! $submission->submitter_email ) return false;

		$subject = '[NazarenerScan] ✅ Produkt freigegeben – Grün';
		$notes   = $reviewer_notes
			? '<div class="ns-note"><strong>Notiz des Prüfers:</strong><br>' . nl2br( esc_html( $reviewer_notes ) ) . '</div>'
			: '';
		$body = self::wrap_template(
			'Produkt freigegeben',
			'🟢',
			'Das Produkt ist erlaubt!',
			sprintf(
				'<p>Gute Neuigkeit! Das von dir eingereichte Produkt <strong>%s</strong> (Barcode: <code>%s</code>) wurde von unserem Team geprüft und als <strong style="color:#2E7D32">🟢 Erlaubt</strong> eingetragen.</p>
				 <p>Es ist jetzt dauerhaft in unserer Datenbank verfügbar.</p>%s',
				esc_html( $submission->product_name ?: 'Dein Produkt' ),
				esc_html( $submission->barcode ),
				$notes
			),
			'#2E7D32',
			'#E8F5E9'
		);

		return self::send( $submission->submitter_email, $subject, $body );
	}

	public static function submission_approved_red( object $submission, string $reviewer_notes = '' ): bool {
		if ( ! $submission->submitter_email ) return false;

		$subject = '[NazarenerScan] ❌ Produkt enthält verbotene Zutaten – Rot';
		$notes   = $reviewer_notes
			? '<div class="ns-note"><strong>Begründung:</strong><br>' . nl2br( esc_html( $reviewer_notes ) ) . '</div>'
			: '';
		$body = self::wrap_template(
			'Produkt nicht erlaubt',
			'🔴',
			'Das Produkt enthält verbotene Zutaten.',
			sprintf(
				'<p>Das von dir eingereichte Produkt <strong>%s</strong> (Barcode: <code>%s</code>) wurde von unserem Team geprüft und als <strong style="color:#C62828">🔴 Nicht erlaubt</strong> eingetragen, da es biblisch verbotene Bestandteile enthält.</p>
				 <p>Es ist jetzt in unserer Datenbank als verboten markiert.</p>%s',
				esc_html( $submission->product_name ?: 'Dein Produkt' ),
				esc_html( $submission->barcode ),
				$notes
			),
			'#C62828',
			'#FFEBEE'
		);

		return self::send( $submission->submitter_email, $subject, $body );
	}

	public static function submission_rejected( object $submission, string $reviewer_notes = '' ): bool {
		if ( ! $submission->submitter_email ) return false;

		$subject = '[NazarenerScan] Einreichung konnte nicht bearbeitet werden';
		$notes   = $reviewer_notes
			? '<div class="ns-note"><strong>Begründung:</strong><br>' . nl2br( esc_html( $reviewer_notes ) ) . '</div>'
			: '';
		$body = self::wrap_template(
			'Einreichung nicht bearbeitet',
			'✖️',
			'Leider konnten wir diese Einreichung nicht bearbeiten.',
			sprintf(
				'<p>Deine Einreichung für den Barcode <code>%s</code> konnte leider nicht bearbeitet werden.</p>
				 <p>Mögliche Gründe: unlesbare Fotos, unvollständige Informationen oder doppelte Einreichung.</p>%s
				 <p>Du kannst das Produkt jederzeit erneut einreichen – bitte achte auf gute Fotoqualität der Zutatenliste.</p>',
				esc_html( $submission->barcode ),
				$notes
			),
			'#455A64',
			'#ECEFF1'
		);

		return self::send( $submission->submitter_email, $subject, $body );
	}

	public static function notify_me( string $email, string $barcode, object $product ): bool {
		$subject = '[NazarenerScan] Produkt jetzt geprüft: ' . $product->barcode;
		$status  = $product->status === 'green'
			? '<strong style="color:#2E7D32">🟢 Erlaubt</strong>'
			: '<strong style="color:#C62828">🔴 Nicht erlaubt</strong>';

		$body = self::wrap_template(
			'Produktstatus verfügbar',
			$product->status === 'green' ? '🟢' : '🔴',
			'Das Produkt wurde jetzt geprüft!',
			sprintf(
				'<p>Du hattest Interesse am Produkt mit Barcode <code>%s</code>.</p>
				 <p>Es wurde inzwischen geprüft und als %s eingetragen.</p>
				 <p>Beim nächsten Scan siehst du das Ergebnis direkt.</p>',
				esc_html( $barcode ),
				$status
			),
			$product->status === 'green' ? '#2E7D32' : '#C62828',
			$product->status === 'green' ? '#E8F5E9' : '#FFEBEE'
		);

		return self::send( $email, $subject, $body );
	}

	public static function admin_new_submission( string $admin_email, object $submission ): bool {
		$review_url = admin_url( 'admin.php?page=nazarener-submissions&action=review&id=' . $submission->id );
		$subject    = '[NazarenerScan] Neues Produkt eingereicht – Barcode ' . $submission->barcode;
		$body       = self::wrap_template(
			'Neue Einreichung',
			'🟡',
			'Ein neues Produkt wartet auf Prüfung.',
			sprintf(
				'<p>Barcode: <code>%s</code><br>Produktname: %s<br>Einreicher: %s</p>
				 <p><a href="%s" class="ns-btn">→ Jetzt prüfen</a></p>',
				esc_html( $submission->barcode ),
				esc_html( $submission->product_name ?: '–' ),
				esc_html( $submission->submitter_email ?: 'Anonym' ),
				esc_url( $review_url )
			),
			'#1565C0',
			'#E3F2FD'
		);

		return self::send( $admin_email, $subject, $body );
	}

	// ── Core send ─────────────────────────────────────────────────────────────

	private static function send( string $to, string $subject, string $body ): bool {
		$settings = get_option( 'nazarener_scan_settings', [] );
		$from     = $settings['mail_from'] ?? get_option( 'admin_email' );
		$name     = $settings['mail_from_name'] ?? get_bloginfo( 'name' );

		add_filter( 'wp_mail_content_type', fn() => 'text/html' );
		add_filter( 'wp_mail_from',      fn() => $from );
		add_filter( 'wp_mail_from_name', fn() => $name );

		$result = wp_mail( $to, $subject, $body );

		remove_all_filters( 'wp_mail_content_type' );
		remove_all_filters( 'wp_mail_from' );
		remove_all_filters( 'wp_mail_from_name' );

		return $result;
	}

	// ── HTML template ─────────────────────────────────────────────────────────

	private static function wrap_template(
		string $title,
		string $icon,
		string $heading,
		string $content,
		string $accent_color,
		string $accent_bg
	): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url() );
		$year      = date( 'Y' );

		return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#F5F5F5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#212121">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F5F5;padding:32px 16px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">

      <!-- Header -->
      <tr><td style="background:{$accent_color};padding:28px 32px;text-align:center">
        <div style="font-size:42px;line-height:1">{$icon}</div>
        <h1 style="color:#fff;margin:12px 0 0;font-size:20px;font-weight:700">{$heading}</h1>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:28px 32px;font-size:15px;line-height:1.65;color:#424242">
        {$content}
      </td></tr>

      <!-- Divider -->
      <tr><td style="padding:0 32px"><hr style="border:none;border-top:1px solid #EEEEEE"></td></tr>

      <!-- Footer -->
      <tr><td style="padding:20px 32px;text-align:center;font-size:12px;color:#9E9E9E">
        <p style="margin:0">
          Diese E-Mail wurde von <a href="{$site_url}" style="color:#1565C0">{$site_name}</a> gesendet.<br>
          NazarenerScan – Biblische Produktprüfung · {$year}
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
<style>
  .ns-btn{display:inline-block;background:#1565C0;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;font-size:15px}
  .ns-note{background:{$accent_bg};border-left:4px solid {$accent_color};padding:12px 16px;border-radius:0 8px 8px 0;margin:16px 0;font-size:14px}
  code{background:#F5F5F5;padding:2px 6px;border-radius:4px;font-family:monospace;font-size:.9em}
</style>
</body>
</html>
HTML;
	}
}
