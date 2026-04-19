export type RatingStatus = 'green' | 'red' | 'yellow';

export interface Product {
  barcode: string;
  name: string;
  brand?: string;
  /** Only set for red products */
  forbiddenIngredients?: string[];
  status: RatingStatus;
  notes?: string;
  reviewedAt?: string;
}

export interface ProductHint {
  type: 'warning' | 'info';
  message: string;
  ingredient?: string;
}

export type SubmissionStatus = 'pending' | 'approved' | 'rejected';

export interface ProductSubmission {
  id: string;
  barcode: string;
  productName?: string;
  productPhotoUri?: string;
  ingredientsPhotoUri?: string;
  submittedAt: string;
  status: SubmissionStatus;
}
