import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiEnvelope, Page } from '../../../core/models/api.models';
import { AdminApiService } from './admin-api.service';
import { ImportColumn, ImportMode, ProductImportDetail, ProductImportJob } from './admin.models';

/** Excel product import: template download, upload → background check → background import. */
@Injectable({ providedIn: 'root' })
export class ProductImportService {
  private readonly api = inject(AdminApiService);
  private readonly base = 'product-imports';

  history(page = 1): Observable<Page<ProductImportJob>> {
    return this.api.page<ProductImportJob>(this.base, { page, per_page: 10 });
  }

  get(id: number, level: 'error' | 'warning' | null = null): Observable<ProductImportDetail> {
    return this.api.get<ProductImportDetail>(`${this.base}/${id}`, level ? { level } : undefined);
  }

  columns(): Observable<ImportColumn[]> {
    return this.api.get<ImportColumn[]>(`${this.base}/columns`);
  }

  upload(file: File, mode: ImportMode, autoStart: boolean): Observable<ApiEnvelope<ProductImportJob>> {
    const form = new FormData();
    form.append('file', file, file.name);
    form.append('mode', mode);
    form.append('auto_start', autoStart ? '1' : '0');
    return this.api.uploadForm<ProductImportJob>(this.base, form);
  }

  start(id: number): Observable<ApiEnvelope<ProductImportJob>> {
    return this.api.post<ProductImportJob>(`${this.base}/${id}/start`);
  }

  cancel(id: number): Observable<ApiEnvelope<ProductImportJob>> {
    return this.api.post<ProductImportJob>(`${this.base}/${id}/cancel`);
  }

  remove(id: number): Observable<ApiEnvelope<null>> {
    return this.api.delete<null>(`${this.base}/${id}`);
  }

  downloadTemplate(withProducts: boolean, filters: { category?: number | null; brand?: number | null } = {}): Observable<void> {
    const name = withProducts ? `products-${new Date().toISOString().slice(0, 10)}.xlsx` : 'product-import-template.xlsx';
    return this.api.download(`${this.base}/template`, name, { with_products: withProducts ? 1 : 0, category: filters.category ?? null, brand: filters.brand ?? null });
  }

  downloadReport(job: Pick<ProductImportJob, 'id' | 'original_name'>): Observable<void> {
    return this.api.download(`${this.base}/${job.id}/report`, `${job.original_name.replace(/\.[^.]+$/, '')} - problems.xlsx`);
  }

  downloadFile(job: Pick<ProductImportJob, 'id' | 'original_name'>): Observable<void> {
    return this.api.download(`${this.base}/${job.id}/file`, job.original_name);
  }
}

/** Statuses during which the page keeps polling for progress. */
export const ACTIVE_STATUSES = ['pending', 'validating', 'queued', 'importing'] as const;

export function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1048576) return `${(n / 1024).toFixed(0)} KB`;
  return `${(n / 1048576).toFixed(1)} MB`;
}
