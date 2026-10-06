import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { ApiService, Query, toFormData } from '../../../core/api/api.service';
import { ApiEnvelope, Page } from '../../../core/models/api.models';
import { Lookups } from './admin.models';

/**
 * Thin typed wrapper around `/api/v1/admin/*`. Every call is authorised again by
 * Laravel (Sanctum + role/permission middleware); the UI only hides what the
 * user cannot use.
 */
@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private readonly api = inject(ApiService);
  private lookups$?: Observable<Lookups>;

  path(p: string): string {
    return `admin/${p.replace(/^\//, '')}`;
  }

  get<T>(p: string, query?: Query): Observable<T> {
    return this.api.get<T>(this.path(p), query);
  }

  envelope<T>(p: string, query?: Query): Observable<ApiEnvelope<T>> {
    return this.api.envelope<T>(this.path(p), query);
  }

  page<T, M = Record<string, unknown>>(p: string, query?: Query): Observable<Page<T, M>> {
    return this.api.page<T, M>(this.path(p), query);
  }

  post<T>(p: string, body: unknown = {}): Observable<ApiEnvelope<T>> {
    return this.api.post<T>(this.path(p), body);
  }

  put<T>(p: string, body: unknown = {}): Observable<ApiEnvelope<T>> {
    return this.api.put<T>(this.path(p), body);
  }

  patch<T>(p: string, body: unknown = {}): Observable<ApiEnvelope<T>> {
    return this.api.patch<T>(this.path(p), body);
  }

  delete<T>(p: string): Observable<ApiEnvelope<T>> {
    return this.api.delete<T>(this.path(p));
  }

  /** multipart create/update — used whenever a form carries a file. */
  upload<T>(p: string, values: Record<string, unknown>, method: 'POST' | 'PUT' = 'POST'): Observable<ApiEnvelope<T>> {
    return this.api.upload<T>(this.path(p), toFormData(values), method);
  }

  uploadForm<T>(p: string, form: FormData): Observable<ApiEnvelope<T>> {
    return this.api.upload<T>(this.path(p), form, 'POST');
  }

  blob(p: string, query?: Query): Observable<Blob> {
    return this.api.blob(this.path(p), query);
  }

  /** Dropdown data shared by many admin forms; cached until `refreshLookups()`. */
  lookups(): Observable<Lookups> {
    return (this.lookups$ ??= this.get<Lookups>('lookups').pipe(shareReplay({ bufferSize: 1, refCount: false })));
  }

  refreshLookups(): void {
    this.lookups$ = undefined;
  }

  /** Saves a Blob to disk via a temporary object URL. */
  download(p: string, filename: string, query?: Query): Observable<void> {
    return this.blob(p, query).pipe(map((blob) => saveBlob(blob, filename)));
  }
}

export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Builds a CSV (RFC 4180 quoting) from rows and triggers a download. */
export function downloadCsv(filename: string, header: string[], rows: (string | number | null | undefined)[][]): void {
  const esc = (v: string | number | null | undefined) => {
    const s = v === null || v === undefined ? '' : String(v);
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const csv = [header, ...rows].map((r) => r.map(esc).join(',')).join('\n');
  saveBlob(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }), filename);
}
