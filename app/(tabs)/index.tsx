import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Alert,
} from 'react-native';
import { CameraView, Camera, BarcodeScanningResult } from 'expo-camera';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function ScannerScreen() {
  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [scanned, setScanned] = useState(false);
  const [manualBarcode, setManualBarcode] = useState('');
  const [showManual, setShowManual] = useState(false);
  const lastScanned = useRef<string>('');

  useEffect(() => {
    Camera.requestCameraPermissionsAsync().then(({ status }) => {
      setHasPermission(status === 'granted');
    });
  }, []);

  function handleBarcodeScanned({ data }: BarcodeScanningResult) {
    if (scanned || data === lastScanned.current) return;
    lastScanned.current = data;
    setScanned(true);
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    router.push(`/result/${encodeURIComponent(data)}`);
    // Allow re-scan after navigating back
    setTimeout(() => {
      setScanned(false);
      lastScanned.current = '';
    }, 2000);
  }

  function handleManualSubmit() {
    const code = manualBarcode.trim();
    if (!code) return;
    setManualBarcode('');
    setShowManual(false);
    router.push(`/result/${encodeURIComponent(code)}`);
  }

  if (hasPermission === null) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.infoText}>Kamera wird gestartet…</Text>
      </SafeAreaView>
    );
  }

  if (hasPermission === false) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.infoText}>Kamerazugriff verweigert.</Text>
        <Text style={styles.subText}>
          Bitte erlaube den Kamerazugriff in den Einstellungen oder gib den Barcode manuell ein.
        </Text>
        <TouchableOpacity style={styles.manualBtn} onPress={() => setShowManual(true)}>
          <Text style={styles.manualBtnText}>Barcode manuell eingeben</Text>
        </TouchableOpacity>
        {showManual && renderManualInput()}
      </SafeAreaView>
    );
  }

  function renderManualInput() {
    return (
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={styles.manualInputRow}>
          <TextInput
            style={styles.input}
            value={manualBarcode}
            onChangeText={setManualBarcode}
            placeholder="z.B. 4006381333931"
            keyboardType="numeric"
            returnKeyType="search"
            onSubmitEditing={handleManualSubmit}
            autoFocus
          />
          <TouchableOpacity style={styles.searchBtn} onPress={handleManualSubmit}>
            <Text style={styles.searchBtnText}>Suchen</Text>
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['ean13', 'ean8', 'upc_a', 'upc_e', 'code128', 'qr'] }}
        onBarcodeScanned={scanned ? undefined : handleBarcodeScanned}
      />

      {/* Viewfinder overlay */}
      <View style={styles.overlay}>
        <View style={styles.topMask} />
        <View style={styles.middleRow}>
          <View style={styles.sideMask} />
          <View style={styles.viewfinder}>
            <View style={[styles.corner, styles.topLeft]} />
            <View style={[styles.corner, styles.topRight]} />
            <View style={[styles.corner, styles.bottomLeft]} />
            <View style={[styles.corner, styles.bottomRight]} />
          </View>
          <View style={styles.sideMask} />
        </View>
        <View style={styles.bottomMask}>
          <Text style={styles.scanHint}>Barcode in den Rahmen halten</Text>
          <TouchableOpacity
            style={styles.manualBtn}
            onPress={() => setShowManual((v) => !v)}
          >
            <Text style={styles.manualBtnText}>Manuell eingeben</Text>
          </TouchableOpacity>
          {showManual && renderManualInput()}
        </View>
      </View>
    </View>
  );
}

const MASK_COLOR = 'rgba(0,0,0,0.55)';
const VIEWFINDER_SIZE = 260;

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, gap: 16 },
  infoText: { fontSize: 18, fontWeight: '600', textAlign: 'center' },
  subText: { fontSize: 14, color: '#666', textAlign: 'center' },

  // Overlay
  overlay: { ...StyleSheet.absoluteFillObject },
  topMask: { flex: 1, backgroundColor: MASK_COLOR },
  middleRow: { flexDirection: 'row', height: VIEWFINDER_SIZE },
  sideMask: { flex: 1, backgroundColor: MASK_COLOR },
  bottomMask: {
    flex: 1,
    backgroundColor: MASK_COLOR,
    alignItems: 'center',
    paddingTop: 20,
    gap: 12,
  },
  viewfinder: {
    width: VIEWFINDER_SIZE,
    height: VIEWFINDER_SIZE,
    borderRadius: 4,
  },

  // Corner markers
  corner: {
    position: 'absolute',
    width: 24,
    height: 24,
    borderColor: '#fff',
    borderWidth: 3,
  },
  topLeft: { top: 0, left: 0, borderBottomWidth: 0, borderRightWidth: 0, borderTopLeftRadius: 4 },
  topRight: { top: 0, right: 0, borderBottomWidth: 0, borderLeftWidth: 0, borderTopRightRadius: 4 },
  bottomLeft: { bottom: 0, left: 0, borderTopWidth: 0, borderRightWidth: 0, borderBottomLeftRadius: 4 },
  bottomRight: { bottom: 0, right: 0, borderTopWidth: 0, borderLeftWidth: 0, borderBottomRightRadius: 4 },

  scanHint: { color: '#fff', fontSize: 15, fontWeight: '500', textAlign: 'center' },

  manualBtn: {
    backgroundColor: 'rgba(255,255,255,0.15)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.5)',
    borderRadius: 8,
    paddingVertical: 10,
    paddingHorizontal: 20,
  },
  manualBtnText: { color: '#fff', fontSize: 14, fontWeight: '600' },

  manualInputRow: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 20,
    marginTop: 4,
  },
  input: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 16,
  },
  searchBtn: {
    backgroundColor: '#1565C0',
    borderRadius: 8,
    paddingHorizontal: 16,
    justifyContent: 'center',
  },
  searchBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
