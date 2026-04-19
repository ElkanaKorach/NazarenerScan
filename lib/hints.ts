import { ProductHint } from '../types';

// Pattern-based preliminary analysis – shown for yellow (unknown) products only.
// These are hints, NOT ratings. The traffic light stays yellow until manual review.
const HINT_PATTERNS: Array<{ pattern: RegExp; hint: ProductHint }> = [
  {
    pattern: /gelatine/i,
    hint: {
      type: 'warning',
      message: 'Enthält möglicherweise Gelatine',
      ingredient: 'Gelatine',
    },
  },
  {
    pattern: /\baroma\b/i,
    hint: {
      type: 'info',
      message: 'Aroma enthalten – Herkunft unklar',
      ingredient: 'Aroma',
    },
  },
  {
    pattern: /schwein|pork|lard|schmalz/i,
    hint: {
      type: 'warning',
      message: 'Mögliche Schweineprodukte enthalten',
      ingredient: 'Schweineprodukt',
    },
  },
  {
    pattern: /karmin|carmin|e\s*120/i,
    hint: {
      type: 'warning',
      message: 'Karmin (E120) enthalten – tierischer Farbstoff aus Läusen',
      ingredient: 'Karmin (E120)',
    },
  },
  {
    pattern: /alkohol|ethanol|wein(?:säure)?(?!\s*säure)|bier/i,
    hint: {
      type: 'warning',
      message: 'Möglicherweise Alkohol enthalten',
      ingredient: 'Alkohol',
    },
  },
  {
    pattern: /rindfleisch|rinderfett|tallow/i,
    hint: {
      type: 'info',
      message: 'Rinderprodukt enthalten – Herkunft/Schächtung unbekannt',
      ingredient: 'Rinderprodukt',
    },
  },
  {
    pattern: /schalentier|shrimp|garnele|hummer|krebs/i,
    hint: {
      type: 'warning',
      message: 'Möglicherweise Schalentiere enthalten',
      ingredient: 'Schalentier',
    },
  },
];

/**
 * Analyzes free-form ingredient text and returns preliminary hints.
 * Does not change the product rating – purely informational for yellow products.
 */
export function analyzeIngredients(ingredientsText: string): ProductHint[] {
  const hints: ProductHint[] = [];
  const seen = new Set<string>();

  for (const { pattern, hint } of HINT_PATTERNS) {
    if (pattern.test(ingredientsText) && !seen.has(hint.message)) {
      hints.push(hint);
      seen.add(hint.message);
    }
  }

  return hints;
}
