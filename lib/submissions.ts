import AsyncStorage from '@react-native-async-storage/async-storage';
import { ProductSubmission } from '../types';

const STORAGE_KEY = '@nazarener_submissions';

export async function getSubmissions(): Promise<ProductSubmission[]> {
  const data = await AsyncStorage.getItem(STORAGE_KEY);
  return data ? (JSON.parse(data) as ProductSubmission[]) : [];
}

export async function addSubmission(
  params: Pick<ProductSubmission, 'barcode' | 'productName' | 'productPhotoUri' | 'ingredientsPhotoUri'>
): Promise<ProductSubmission> {
  const submissions = await getSubmissions();
  const entry: ProductSubmission = {
    ...params,
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    submittedAt: new Date().toISOString(),
    status: 'pending',
  };
  await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify([...submissions, entry]));
  return entry;
}

export async function getSubmissionByBarcode(barcode: string): Promise<ProductSubmission | null> {
  const submissions = await getSubmissions();
  return submissions.find((s) => s.barcode === barcode) ?? null;
}

export async function clearSubmissions(): Promise<void> {
  await AsyncStorage.removeItem(STORAGE_KEY);
}
