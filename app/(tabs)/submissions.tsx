import React, { useCallback, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  RefreshControl,
  TouchableOpacity,
  Alert,
} from 'react-native';
import { useFocusEffect } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { getSubmissions, clearSubmissions } from '../../lib/submissions';
import { ProductSubmission, SubmissionStatus } from '../../types';

const STATUS_LABEL: Record<SubmissionStatus, string> = {
  pending: 'Wird geprüft',
  approved: 'Freigegeben',
  rejected: 'Abgelehnt',
};

const STATUS_COLOR: Record<SubmissionStatus, string> = {
  pending: '#FF9800',
  approved: '#4CAF50',
  rejected: '#F44336',
};

function SubmissionRow({ item }: { item: ProductSubmission }) {
  const color = STATUS_COLOR[item.status];
  const date = new Date(item.submittedAt).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });

  return (
    <View style={styles.row}>
      <View style={[styles.statusDot, { backgroundColor: color }]} />
      <View style={styles.rowContent}>
        <Text style={styles.rowBarcode}>{item.barcode}</Text>
        {item.productName ? (
          <Text style={styles.rowName}>{item.productName}</Text>
        ) : null}
        <Text style={styles.rowDate}>Eingereicht: {date}</Text>
      </View>
      <View style={[styles.badge, { backgroundColor: color + '22', borderColor: color }]}>
        <Text style={[styles.badgeText, { color }]}>{STATUS_LABEL[item.status]}</Text>
      </View>
    </View>
  );
}

export default function SubmissionsScreen() {
  const [submissions, setSubmissions] = useState<ProductSubmission[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    const data = await getSubmissions();
    setSubmissions(data.sort((a, b) => b.submittedAt.localeCompare(a.submittedAt)));
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  async function onRefresh() {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }

  function confirmClear() {
    Alert.alert('Alle löschen?', 'Alle eingereichten Produkte werden lokal entfernt.', [
      { text: 'Abbrechen', style: 'cancel' },
      {
        text: 'Löschen',
        style: 'destructive',
        onPress: async () => {
          await clearSubmissions();
          setSubmissions([]);
        },
      },
    ]);
  }

  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <FlatList
        data={submissions}
        keyExtractor={(item) => item.id}
        renderItem={({ item }) => <SubmissionRow item={item} />}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        contentContainerStyle={submissions.length === 0 ? styles.emptyContainer : styles.list}
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Ionicons name="checkmark-circle-outline" size={56} color="#BDBDBD" />
            <Text style={styles.emptyTitle}>Keine Einreichungen</Text>
            <Text style={styles.emptyText}>
              Wenn du ein unbekanntes Produkt einreichst, erscheint es hier.
            </Text>
          </View>
        }
        ListHeaderComponent={
          submissions.length > 0 ? (
            <View style={styles.listHeader}>
              <Text style={styles.listHeaderText}>{submissions.length} Einreichung(en)</Text>
              <TouchableOpacity onPress={confirmClear}>
                <Text style={styles.clearText}>Alle löschen</Text>
              </TouchableOpacity>
            </View>
          ) : null
        }
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F5F5F5' },
  list: { padding: 16, gap: 10 },
  emptyContainer: { flex: 1 },

  listHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  listHeaderText: { fontSize: 13, color: '#757575' },
  clearText: { fontSize: 13, color: '#F44336' },

  row: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 10,
    padding: 14,
    gap: 12,
    elevation: 1,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 2 },
  },
  statusDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  rowContent: { flex: 1, gap: 2 },
  rowBarcode: { fontSize: 15, fontWeight: '600', color: '#212121' },
  rowName: { fontSize: 13, color: '#616161' },
  rowDate: { fontSize: 12, color: '#9E9E9E', marginTop: 2 },

  badge: {
    borderWidth: 1,
    borderRadius: 6,
    paddingVertical: 3,
    paddingHorizontal: 8,
  },
  badgeText: { fontSize: 11, fontWeight: '700' },

  emptyState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
    gap: 12,
    marginTop: 80,
  },
  emptyTitle: { fontSize: 18, fontWeight: '600', color: '#616161' },
  emptyText: { fontSize: 14, color: '#9E9E9E', textAlign: 'center', lineHeight: 20 },
});
