<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NazarenerScan_Hints {

	private static array $patterns = [
		[
			'pattern' => '/gelatine/i',
			'type'    => 'warning',
			'message' => 'Enthält möglicherweise Gelatine',
		],
		[
			'pattern' => '/\baroma\b/i',
			'type'    => 'info',
			'message' => 'Aroma enthalten – Herkunft unklar',
		],
		[
			'pattern' => '/schwein|pork|lard|schmalz/i',
			'type'    => 'warning',
			'message' => 'Mögliche Schweineprodukte enthalten',
		],
		[
			'pattern' => '/karmin|carmin|e\s*120/i',
			'type'    => 'warning',
			'message' => 'Karmin (E120) enthalten – tierischer Farbstoff',
		],
		[
			'pattern' => '/alkohol|ethanol|bier(?!säure)|wein(?!säure)/i',
			'type'    => 'warning',
			'message' => 'Möglicherweise Alkohol enthalten',
		],
		[
			'pattern' => '/rindfleisch|rinderfett|tallow/i',
			'type'    => 'info',
			'message' => 'Rinderprodukt – Herkunft/Schächtung unbekannt',
		],
		[
			'pattern' => '/schalentier|shrimp|garnele|hummer|krebs|muschel/i',
			'type'    => 'warning',
			'message' => 'Möglicherweise Schalentiere enthalten',
		],
		[
			'pattern' => '/schellack|e\s*904/i',
			'type'    => 'info',
			'message' => 'Schellack (E904) enthalten – tierischer Ursprung',
		],
		[
			'pattern' => '/rennet|lab(?:\b)/i',
			'type'    => 'info',
			'message' => 'Lab enthalten – Herkunft (tierisch/mikrobiell) unklar',
		],
	];

	/**
	 * Returns preliminary hints for an ingredient text.
	 * These are informational only and do NOT change the product status.
	 */
	public static function analyze( string $text ): array {
		$hints = [];
		$seen  = [];

		foreach ( self::$patterns as $rule ) {
			if ( preg_match( $rule['pattern'], $text ) && ! in_array( $rule['message'], $seen, true ) ) {
				$hints[] = [
					'type'    => $rule['type'],
					'message' => $rule['message'],
				];
				$seen[] = $rule['message'];
			}
		}

		return $hints;
	}
}
