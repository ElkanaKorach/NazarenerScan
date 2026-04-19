import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { RatingStatus } from '../types';

interface Props {
  status: RatingStatus;
  size?: 'small' | 'large';
}

const STATUS_CONFIG: Record<RatingStatus, { color: string; bg: string; label: string; description: string }> = {
  green: {
    color: '#2E7D32',
    bg: '#E8F5E9',
    label: 'Erlaubt',
    description: 'Produkt ist geprüft und unbedenklich nach biblischen Maßstäben.',
  },
  red: {
    color: '#C62828',
    bg: '#FFEBEE',
    label: 'Nicht erlaubt',
    description: 'Produkt enthält eindeutig verbotene Bestandteile.',
  },
  yellow: {
    color: '#E65100',
    bg: '#FFF3E0',
    label: 'Nicht im System',
    description: 'Produkt wurde noch nicht geprüft und ist nicht in der Datenbank.',
  },
};

const INDICATOR_COLORS: Record<RatingStatus, string> = {
  green: '#4CAF50',
  red: '#F44336',
  yellow: '#FF9800',
};

export function TrafficLight({ status, size = 'large' }: Props) {
  const config = STATUS_CONFIG[status];
  const indicatorColor = INDICATOR_COLORS[status];
  const isLarge = size === 'large';

  return (
    <View style={[styles.container, { backgroundColor: config.bg, borderColor: indicatorColor }]}>
      <View style={[styles.indicator, { backgroundColor: indicatorColor }, isLarge && styles.indicatorLarge]} />
      <View style={styles.textBlock}>
        <Text style={[styles.label, { color: config.color }, isLarge && styles.labelLarge]}>
          {config.label}
        </Text>
        {isLarge && (
          <Text style={[styles.description, { color: config.color }]}>{config.description}</Text>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 2,
    borderRadius: 12,
    padding: 16,
    gap: 14,
  },
  indicator: {
    width: 20,
    height: 20,
    borderRadius: 10,
  },
  indicatorLarge: {
    width: 32,
    height: 32,
    borderRadius: 16,
  },
  textBlock: {
    flex: 1,
    gap: 4,
  },
  label: {
    fontSize: 16,
    fontWeight: '700',
  },
  labelLarge: {
    fontSize: 20,
  },
  description: {
    fontSize: 14,
    opacity: 0.85,
    lineHeight: 20,
  },
});
