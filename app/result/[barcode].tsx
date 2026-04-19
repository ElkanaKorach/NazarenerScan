import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { getProduct } from '../../lib/products';
import { getSubmissionByBarcode } from '../../lib/submissions';
import { TrafficLight } from '../../components/TrafficLight';
import { HintsBanner } from '../../components/HintsBanner';
import { Product, ProductSubmission } from '../../types';

export default function ResultScreen() {
  const { barcode } = useLocalSearchParams<{ barcode: string }>();
  const [product, setProduct] = useState<Product | null | undefined>(undefined);
  const [existingSubmission, setExistingSubmission] = useState<ProductSubmission | null>(null);

  useEffect(() => {
    const decoded = decodeURIComponent(barcode ?? '');
    setProduct(getProduct(decoded));
    getSubmissionByBarcode(decoded).then(setExistingSubmission);
  }, [barcode]);

  const decodedBarcode = decodeURIComponent(barcode ?? '');

  if (product === undefined) {
    return (
      <SafeAreaView style={styles.centered}>
        <ActivityIndicator size="large" color="#1565C0" />
      </SafeAreaView>
    );
  }

  // Yellow: product not in database
  if (product === null) {
    return (
      <SafeAreaView style={styles.safe} edges={['bottom']}>
        <ScrollView contentContainerStyle={styles.content}>
          <TrafficLight status="yellow" size="large" />

          <View style={styles.barcodeBox}>
            <Ionicons name="barcode-outline" size={20} color="#757575" />
            <Text style={styles.barcodeText}>{decodedBarcode}</Text>
          </View>

          <View style={styles.infoBox}>
            <Text style={styles.infoBoxTitle}>
              Dieses Produkt ist noch nicht im System.
            </Text>
            <Text style={styles.infoBoxBody}>
              Du kannst es jetzt zur Prüfung einreichen. Unser Team überprüft die Zutaten nach
              biblischen Maßstäben und gibt das Produkt als{' '}
              <Text style={{ color: '#2E7D32', fontWeight: '700' }}>Grün</Text> oder{' '}
              <Text style={{ color: '#C62828', fontWeight: '700' }}>Rot</Text> frei.
            </Text>
          </View>

          <HintsBanner hints={[]} />

          {existingSubmission ? (
            <View style={styles.alreadySubmitted}>
              <Ionicons name="time-outline" size={20} color="#FF9800" />
              <Text style={styles.alreadySubmittedText}>
                Du hast dieses Produkt bereits eingereicht. Es wird gerade geprüft.
              </Text>
            </View>
          ) : (
            <TouchableOpacity
              style={styles.submitCta}
              onPress={() => router.push(`/submit/${encodeURIComponent(decodedBarcode)}`)}
              activeOpacity={0.85}
            >
              <Ionicons name="cloud-upload-outline" size={22} color="#fff" />
              <Text style={styles.submitCtaText}>Zur Prüfung einreichen</Text>
            </TouchableOpacity>
          )}

          <TouchableOpacity style={styles.scanAgain} onPress={() => router.back()}>
            <Ionicons name="scan-outline" size={18} color="#1565C0" />
            <Text style={styles.scanAgainText}>Neues Produkt scannen</Text>
          </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Green or Red: product is in the database
  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.content}>
        <TrafficLight status={product.status} size="large" />

        <View style={styles.productCard}>
          <Text style={styles.productName}>{product.name}</Text>
          {product.brand ? <Text style={styles.productBrand}>{product.brand}</Text> : null}
          <View style={styles.barcodeBox}>
            <Ionicons name="barcode-outline" size={18} color="#757575" />
            <Text style={styles.barcodeText}>{product.barcode}</Text>
          </View>
        </View>

        {product.status === 'red' && product.forbiddenIngredients && (
          <View style={styles.forbiddenBox}>
            <Text style={styles.forbiddenTitle}>Verbotene Bestandteile</Text>
            {product.forbiddenIngredients.map((ing, i) => (
              <View key={i} style={styles.forbiddenRow}>
                <Ionicons name="close-circle" size={16} color="#C62828" />
                <Text style={styles.forbiddenText}>{ing}</Text>
              </View>
            ))}
            {product.notes ? (
              <Text style={styles.forbiddenNote}>{product.notes}</Text>
            ) : null}
          </View>
        )}

        {product.status === 'green' && product.notes ? (
          <View style={styles.notesBox}>
            <Ionicons name="information-circle-outline" size={16} color="#2E7D32" />
            <Text style={styles.notesText}>{product.notes}</Text>
          </View>
        ) : null}

        {product.reviewedAt ? (
          <Text style={styles.reviewDate}>
            Geprüft am{' '}
            {new Date(product.reviewedAt).toLocaleDateString('de-DE', {
              day: '2-digit',
              month: 'long',
              year: 'numeric',
            })}
          </Text>
        ) : null}

        <TouchableOpacity style={styles.scanAgain} onPress={() => router.back()}>
          <Ionicons name="scan-outline" size={18} color="#1565C0" />
          <Text style={styles.scanAgainText}>Neues Produkt scannen</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F5F5F5' },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  content: { padding: 20, gap: 16 },

  barcodeBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#EEEEEE',
    borderRadius: 8,
    padding: 10,
  },
  barcodeText: { fontSize: 13, color: '#616161', fontFamily: 'monospace' },

  productCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    gap: 6,
    elevation: 1,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 2 },
  },
  productName: { fontSize: 22, fontWeight: '700', color: '#212121' },
  productBrand: { fontSize: 15, color: '#757575' },

  infoBox: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    gap: 8,
    elevation: 1,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 2 },
  },
  infoBoxTitle: { fontSize: 17, fontWeight: '700', color: '#212121' },
  infoBoxBody: { fontSize: 14, color: '#424242', lineHeight: 21 },

  forbiddenBox: {
    backgroundColor: '#FFEBEE',
    borderWidth: 1,
    borderColor: '#EF9A9A',
    borderRadius: 10,
    padding: 14,
    gap: 8,
  },
  forbiddenTitle: { fontSize: 14, fontWeight: '700', color: '#C62828', textTransform: 'uppercase', letterSpacing: 0.5 },
  forbiddenRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  forbiddenText: { fontSize: 15, color: '#C62828', fontWeight: '500' },
  forbiddenNote: { fontSize: 13, color: '#B71C1C', marginTop: 4, fontStyle: 'italic' },

  notesBox: {
    flexDirection: 'row',
    gap: 8,
    backgroundColor: '#E8F5E9',
    borderRadius: 8,
    padding: 12,
    alignItems: 'flex-start',
  },
  notesText: { flex: 1, fontSize: 14, color: '#2E7D32', lineHeight: 20 },

  reviewDate: { fontSize: 12, color: '#9E9E9E', textAlign: 'center' },

  alreadySubmitted: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#FFF3E0',
    borderWidth: 1,
    borderColor: '#FFB74D',
    borderRadius: 10,
    padding: 14,
  },
  alreadySubmittedText: { flex: 1, fontSize: 14, color: '#E65100', lineHeight: 20 },

  submitCta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: '#1565C0',
    borderRadius: 12,
    paddingVertical: 16,
    paddingHorizontal: 24,
    elevation: 2,
    shadowColor: '#1565C0',
    shadowOpacity: 0.3,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
  },
  submitCtaText: { color: '#fff', fontSize: 17, fontWeight: '700' },

  scanAgain: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
  },
  scanAgainText: { color: '#1565C0', fontSize: 15, fontWeight: '600' },
});
