import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Image,
  Alert,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { useLocalSearchParams, router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as ImagePicker from 'expo-image-picker';
import { Ionicons } from '@expo/vector-icons';
import { addSubmission } from '../../lib/submissions';
import { analyzeIngredients } from '../../lib/hints';
import { HintsBanner } from '../../components/HintsBanner';
import { ProductHint } from '../../types';

type PhotoField = 'product' | 'ingredients';

export default function SubmitScreen() {
  const { barcode } = useLocalSearchParams<{ barcode: string }>();
  const decodedBarcode = decodeURIComponent(barcode ?? '');

  const [productName, setProductName] = useState('');
  const [productPhotoUri, setProductPhotoUri] = useState<string | null>(null);
  const [ingredientsPhotoUri, setIngredientsPhotoUri] = useState<string | null>(null);
  const [ingredientsText, setIngredientsText] = useState('');
  const [hints, setHints] = useState<ProductHint[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  async function pickPhoto(field: PhotoField) {
    const { status } = await ImagePicker.requestCameraPermissionsAsync();
    if (status !== 'granted') {
      // Fall back to library
      const libResult = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.8,
      });
      if (!libResult.canceled && libResult.assets[0]) {
        applyPhoto(field, libResult.assets[0].uri);
      }
      return;
    }

    Alert.alert(
      field === 'product' ? 'Produktfoto' : 'Zutatenliste',
      'Foto aufnehmen oder aus Galerie wählen?',
      [
        {
          text: 'Kamera',
          onPress: async () => {
            const result = await ImagePicker.launchCameraAsync({ quality: 0.8 });
            if (!result.canceled && result.assets[0]) applyPhoto(field, result.assets[0].uri);
          },
        },
        {
          text: 'Galerie',
          onPress: async () => {
            const result = await ImagePicker.launchImageLibraryAsync({
              mediaTypes: ImagePicker.MediaTypeOptions.Images,
              quality: 0.8,
            });
            if (!result.canceled && result.assets[0]) applyPhoto(field, result.assets[0].uri);
          },
        },
        { text: 'Abbrechen', style: 'cancel' },
      ]
    );
  }

  function applyPhoto(field: PhotoField, uri: string) {
    if (field === 'product') setProductPhotoUri(uri);
    else setIngredientsPhotoUri(uri);
  }

  function handleIngredientsTextChange(text: string) {
    setIngredientsText(text);
    if (text.length > 3) {
      setHints(analyzeIngredients(text));
    } else {
      setHints([]);
    }
  }

  async function handleSubmit() {
    if (!productPhotoUri && !ingredientsPhotoUri && !productName.trim()) {
      Alert.alert('Bitte ergänze', 'Füge mindestens ein Foto oder den Produktnamen hinzu.');
      return;
    }

    setSubmitting(true);
    try {
      await addSubmission({
        barcode: decodedBarcode,
        productName: productName.trim() || undefined,
        productPhotoUri: productPhotoUri ?? undefined,
        ingredientsPhotoUri: ingredientsPhotoUri ?? undefined,
      });
      setDone(true);
    } catch {
      Alert.alert('Fehler', 'Einreichung konnte nicht gespeichert werden. Bitte versuche es erneut.');
    } finally {
      setSubmitting(false);
    }
  }

  if (done) {
    return (
      <SafeAreaView style={styles.safe} edges={['bottom']}>
        <View style={styles.successState}>
          <View style={styles.successIcon}>
            <Ionicons name="checkmark-circle" size={72} color="#4CAF50" />
          </View>
          <Text style={styles.successTitle}>Erfolgreich eingereicht!</Text>
          <Text style={styles.successBody}>
            Danke! Das Produkt wurde zur manuellen Prüfung übergeben. Sobald es geprüft ist, erscheint es
            in der Datenbank als{' '}
            <Text style={{ color: '#2E7D32', fontWeight: '700' }}>Grün</Text> oder{' '}
            <Text style={{ color: '#C62828', fontWeight: '700' }}>Rot</Text>.
          </Text>
          <TouchableOpacity
            style={styles.doneBtn}
            onPress={() => router.dismissAll()}
          >
            <Text style={styles.doneBtnText}>Zurück zum Scanner</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {/* Barcode confirmation */}
          <View style={styles.section}>
            <Text style={styles.sectionLabel}>Barcode bestätigt</Text>
            <View style={styles.barcodeConfirm}>
              <Ionicons name="checkmark-circle" size={20} color="#4CAF50" />
              <Text style={styles.barcodeText}>{decodedBarcode}</Text>
            </View>
          </View>

          {/* Product name (optional) */}
          <View style={styles.section}>
            <Text style={styles.sectionLabel}>Produktname (optional)</Text>
            <TextInput
              style={styles.input}
              value={productName}
              onChangeText={setProductName}
              placeholder="z.B. Haribo Goldbären"
              returnKeyType="done"
            />
          </View>

          {/* Product photo */}
          <View style={styles.section}>
            <Text style={styles.sectionLabel}>
              Foto vom Produkt{' '}
              <Text style={styles.required}>*empfohlen</Text>
            </Text>
            <TouchableOpacity
              style={[styles.photoBtn, productPhotoUri ? styles.photoBtnFilled : null]}
              onPress={() => pickPhoto('product')}
              activeOpacity={0.8}
            >
              {productPhotoUri ? (
                <Image source={{ uri: productPhotoUri }} style={styles.photoPreview} />
              ) : (
                <>
                  <Ionicons name="camera-outline" size={28} color="#1565C0" />
                  <Text style={styles.photoBtnText}>Produktfoto aufnehmen</Text>
                </>
              )}
            </TouchableOpacity>
            {productPhotoUri && (
              <TouchableOpacity onPress={() => setProductPhotoUri(null)}>
                <Text style={styles.removePhoto}>Foto entfernen</Text>
              </TouchableOpacity>
            )}
          </View>

          {/* Ingredients photo */}
          <View style={styles.section}>
            <Text style={styles.sectionLabel}>
              Foto der Zutatenliste{' '}
              <Text style={styles.required}>*empfohlen</Text>
            </Text>
            <TouchableOpacity
              style={[styles.photoBtn, ingredientsPhotoUri ? styles.photoBtnFilled : null]}
              onPress={() => pickPhoto('ingredients')}
              activeOpacity={0.8}
            >
              {ingredientsPhotoUri ? (
                <Image source={{ uri: ingredientsPhotoUri }} style={styles.photoPreview} />
              ) : (
                <>
                  <Ionicons name="document-text-outline" size={28} color="#1565C0" />
                  <Text style={styles.photoBtnText}>Zutatenliste fotografieren</Text>
                </>
              )}
            </TouchableOpacity>
            {ingredientsPhotoUri && (
              <TouchableOpacity onPress={() => setIngredientsPhotoUri(null)}>
                <Text style={styles.removePhoto}>Foto entfernen</Text>
              </TouchableOpacity>
            )}
          </View>

          {/* Optional: manual ingredients text for hint analysis */}
          <View style={styles.section}>
            <Text style={styles.sectionLabel}>Zutaten tippen (optional – für Vorab-Hinweise)</Text>
            <TextInput
              style={[styles.input, styles.textArea]}
              value={ingredientsText}
              onChangeText={handleIngredientsTextChange}
              placeholder="z.B. Zucker, Gelatine, Aroma…"
              multiline
              numberOfLines={4}
              textAlignVertical="top"
            />
          </View>

          {/* Preliminary hints – informational only, does NOT affect the yellow status */}
          {hints.length > 0 && <HintsBanner hints={hints} />}

          <TouchableOpacity
            style={[styles.submitBtn, submitting && styles.submitBtnDisabled]}
            onPress={handleSubmit}
            disabled={submitting}
            activeOpacity={0.85}
          >
            {submitting ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Ionicons name="cloud-upload-outline" size={22} color="#fff" />
                <Text style={styles.submitBtnText}>Zur Prüfung einreichen</Text>
              </>
            )}
          </TouchableOpacity>

          <Text style={styles.disclaimer}>
            Das Produkt bleibt gelb bis zur manuellen Prüfung durch unser Team. Einreichungen werden nicht automatisch bewertet.
          </Text>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F5F5F5' },
  content: { padding: 20, gap: 20, paddingBottom: 40 },

  section: { gap: 8 },
  sectionLabel: { fontSize: 13, fontWeight: '700', color: '#424242', textTransform: 'uppercase', letterSpacing: 0.4 },
  required: { color: '#FF9800', fontWeight: '600', textTransform: 'none' },

  barcodeConfirm: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#E8F5E9',
    borderRadius: 8,
    padding: 12,
    borderWidth: 1,
    borderColor: '#A5D6A7',
  },
  barcodeText: { fontSize: 15, color: '#2E7D32', fontFamily: 'monospace', fontWeight: '600' },

  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#E0E0E0',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    color: '#212121',
  },
  textArea: { minHeight: 90 },

  photoBtn: {
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#90CAF9',
    borderStyle: 'dashed',
    borderRadius: 12,
    height: 130,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  photoBtnFilled: {
    borderStyle: 'solid',
    borderColor: '#1565C0',
    padding: 0,
    overflow: 'hidden',
  },
  photoBtnText: { color: '#1565C0', fontSize: 14, fontWeight: '600' },
  photoPreview: { width: '100%', height: '100%', resizeMode: 'cover' },
  removePhoto: { color: '#F44336', fontSize: 13, textAlign: 'center' },

  submitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: '#1565C0',
    borderRadius: 12,
    paddingVertical: 16,
    elevation: 2,
    shadowColor: '#1565C0',
    shadowOpacity: 0.3,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
  },
  submitBtnDisabled: { opacity: 0.6 },
  submitBtnText: { color: '#fff', fontSize: 17, fontWeight: '700' },

  disclaimer: {
    fontSize: 12,
    color: '#9E9E9E',
    textAlign: 'center',
    lineHeight: 18,
    fontStyle: 'italic',
  },

  // Success state
  successState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    gap: 20,
  },
  successIcon: {
    backgroundColor: '#E8F5E9',
    borderRadius: 60,
    padding: 16,
  },
  successTitle: { fontSize: 24, fontWeight: '700', color: '#212121', textAlign: 'center' },
  successBody: { fontSize: 15, color: '#424242', textAlign: 'center', lineHeight: 22 },
  doneBtn: {
    backgroundColor: '#1565C0',
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 32,
    marginTop: 8,
  },
  doneBtnText: { color: '#fff', fontSize: 16, fontWeight: '700' },
});
