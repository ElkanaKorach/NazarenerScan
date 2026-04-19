import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { ProductHint } from '../types';

interface Props {
  hints: ProductHint[];
}

/**
 * Displays preliminary ingredient hints for yellow (unreviewed) products.
 * These are informational only – they do NOT change the traffic light status.
 */
export function HintsBanner({ hints }: Props) {
  if (hints.length === 0) return null;

  return (
    <View style={styles.container}>
      <Text style={styles.header}>Hinweise (vorläufig, keine Bewertung)</Text>
      {hints.map((hint, i) => (
        <View key={i} style={styles.row}>
          <Text style={hint.type === 'warning' ? styles.iconWarning : styles.iconInfo}>
            {hint.type === 'warning' ? '⚠️' : 'ℹ️'}
          </Text>
          <Text style={styles.message}>{hint.message}</Text>
        </View>
      ))}
      <Text style={styles.disclaimer}>
        Diese Hinweise ersetzen keine offizielle Prüfung. Das Produkt bleibt gelb, bis es manuell freigegeben wird.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#FFFDE7',
    borderWidth: 1,
    borderColor: '#F9A825',
    borderRadius: 10,
    padding: 14,
    gap: 8,
  },
  header: {
    fontSize: 13,
    fontWeight: '700',
    color: '#5D4037',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
  },
  iconWarning: {
    fontSize: 16,
  },
  iconInfo: {
    fontSize: 16,
  },
  message: {
    flex: 1,
    fontSize: 14,
    color: '#4E342E',
    lineHeight: 20,
  },
  disclaimer: {
    fontSize: 12,
    color: '#8D6E63',
    fontStyle: 'italic',
    marginTop: 4,
    lineHeight: 17,
  },
});
