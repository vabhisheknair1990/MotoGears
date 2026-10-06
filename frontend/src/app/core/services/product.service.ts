import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService, Query } from '../api/api.service';
import { Compatibility, Facets, Page, Product, ProductCard } from '../models/api.models';

export interface ProductFilters {
  page?: number;
  per_page?: number;
  search?: string | null;
  category?: string | null;
  brand?: string | string[] | null;
  vehicle_variant?: number | null;
  vehicle_year?: number | null;
  vehicle_type?: string | null;
  min_price?: number | null;
  max_price?: number | null;
  rating?: number | null;
  discount?: number | null;
  in_stock?: boolean | null;
  featured?: boolean | null;
  attributes?: Record<string, string[]>;
  sort?: string | null;
  with_facets?: boolean;
}

export interface ProductListMeta {
  sort: string | null;
  facets?: Facets;
  vehicle?: { id: number; name: string; year_range: string };
  query?: string;
}

export interface CompatibilityInfo {
  is_universal: boolean;
  vehicle_type: string;
  vehicles: Compatibility[];
  grouped: { manufacturer: string; models: { model: string; variants: string[]; years: string[] }[] }[];
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly api = inject(ApiService);

  getProducts(filters: ProductFilters): Observable<Page<ProductCard, ProductListMeta>> {
    return this.api.page<ProductCard, ProductListMeta>('products', filters as unknown as Query);
  }

  getProduct(slug: string, vehicleVariant?: number | null): Observable<Product> {
    return this.api.get<Product>(`products/${encodeURIComponent(slug)}`, { vehicle_variant: vehicleVariant ?? undefined });
  }

  search(filters: ProductFilters & { q: string }): Observable<Page<ProductCard, ProductListMeta>> {
    return this.api.page<ProductCard, ProductListMeta>('search', filters as unknown as Query);
  }

  getFacets(filters: ProductFilters): Observable<Facets> {
    return this.api.get<Facets>('products/filters', filters as unknown as Query);
  }

  getFeatured(limit = 8): Observable<Page<ProductCard, ProductListMeta>> {
    return this.getProducts({ featured: true, per_page: limit });
  }

  getCompatibleProducts(variantId: number, filters: ProductFilters = {}): Observable<Page<ProductCard, ProductListMeta>> {
    return this.getProducts({ ...filters, vehicle_variant: variantId });
  }

  getCompatibility(productId: number): Observable<CompatibilityInfo> {
    return this.api.get<CompatibilityInfo>(`products/${productId}/compatibility`);
  }

  checkFitment(productId: number, variantId: number, year?: number | null): Observable<{ fits: boolean; vehicle: string; message: string }> {
    return this.api.get(`products/${productId}/check-compatibility`, { vehicle_variant_id: variantId, year: year ?? undefined });
  }

  getRelated(productId: number): Observable<ProductCard[]> {
    return this.api.get<ProductCard[]>(`products/${productId}/related`);
  }
}
