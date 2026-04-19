import { Product } from '../types';

// Mock product database – in production this would be fetched from a backend API.
const PRODUCTS: Record<string, Product> = {
  '4006381333931': {
    barcode: '4006381333931',
    name: 'Haribo Goldbären',
    brand: 'Haribo',
    status: 'red',
    forbiddenIngredients: ['Gelatine (Schwein)'],
    notes: 'Enthält Schweinegelatine',
    reviewedAt: '2024-01-15',
  },
  '4000521005603': {
    barcode: '4000521005603',
    name: 'Leibniz Butterkeks',
    brand: 'Bahlsen',
    status: 'green',
    reviewedAt: '2024-01-10',
  },
  '4103370010096': {
    barcode: '4103370010096',
    name: 'Lachs Filet',
    brand: 'followfish',
    status: 'green',
    reviewedAt: '2024-01-20',
  },
  '5000159484695': {
    barcode: '5000159484695',
    name: 'Twix',
    brand: 'Mars',
    status: 'green',
    notes: 'Keine unreinen Zutaten',
    reviewedAt: '2024-02-01',
  },
  '4005500173281': {
    barcode: '4005500173281',
    name: 'Manner Schnitten',
    brand: 'Manner',
    status: 'red',
    forbiddenIngredients: ['Schweineschmalz'],
    reviewedAt: '2024-01-25',
  },
  '4388844058651': {
    barcode: '4388844058651',
    name: 'Katjes Fruchtgummi',
    brand: 'Katjes',
    status: 'green',
    notes: 'Verwendet pflanzliche Gelatine-Alternative',
    reviewedAt: '2024-02-10',
  },
  '20724565': {
    barcode: '20724565',
    name: 'Garnelen (Schalentiere)',
    brand: '',
    status: 'red',
    forbiddenIngredients: ['Schalentiere'],
    notes: 'Biblisch verboten (3. Mose 11:10)',
    reviewedAt: '2024-01-05',
  },
};

export function getProduct(barcode: string): Product | null {
  return PRODUCTS[barcode] ?? null;
}

export function getAllProducts(): Product[] {
  return Object.values(PRODUCTS);
}
