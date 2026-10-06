import { HttpClient, HttpContext, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiEnvelope, Page, PageMeta } from '../models/api.models';

export type QueryValue = string | number | boolean | null | undefined | (string | number)[] | Record<string, string | number | (string | number)[] | null | undefined>;
export type Query = Record<string, QueryValue>;

/**
 * Thin wrapper around HttpClient that knows the API base URL and unwraps the
 * `{ success, message, data, meta }` envelope. Components never call HttpClient
 * directly — they go through feature services built on this.
 */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  readonly baseUrl = environment.apiUrl.replace(/\/$/, '');

  url(path: string): string {
    return `${this.baseUrl}/${path.replace(/^\//, '')}`;
  }

  get<T>(path: string, query?: Query, context?: HttpContext): Observable<T> {
    return this.envelope<T>(path, query, context).pipe(map((r) => r.data));
  }

  /** Full envelope — use when the message or meta is needed. */
  envelope<T>(path: string, query?: Query, context?: HttpContext): Observable<ApiEnvelope<T>> {
    return this.http.get<ApiEnvelope<T>>(this.url(path), { params: toParams(query), context });
  }

  page<T, M = Record<string, unknown>>(path: string, query?: Query, context?: HttpContext): Observable<Page<T, M>> {
    return this.envelope<T[]>(path, query, context).pipe(
      map((r) => ({ items: r.data, meta: (r.meta ?? emptyMeta()) as PageMeta & M })),
    );
  }

  post<T>(path: string, body: unknown = {}, context?: HttpContext): Observable<ApiEnvelope<T>> {
    return this.http.post<ApiEnvelope<T>>(this.url(path), body, { context });
  }

  put<T>(path: string, body: unknown = {}, context?: HttpContext): Observable<ApiEnvelope<T>> {
    return this.http.put<ApiEnvelope<T>>(this.url(path), body, { context });
  }

  patch<T>(path: string, body: unknown = {}, context?: HttpContext): Observable<ApiEnvelope<T>> {
    return this.http.patch<ApiEnvelope<T>>(this.url(path), body, { context });
  }

  delete<T>(path: string, context?: HttpContext): Observable<ApiEnvelope<T>> {
    return this.http.delete<ApiEnvelope<T>>(this.url(path), { context });
  }

  /** multipart/form-data upload (PHP only parses multipart on POST, so updates use `_method`). */
  upload<T>(path: string, form: FormData, method: 'POST' | 'PUT' = 'POST'): Observable<ApiEnvelope<T>> {
    if (method === 'PUT') {
      form.set('_method', 'PUT');
    }
    return this.http.post<ApiEnvelope<T>>(this.url(path), form);
  }

  blob(path: string, query?: Query): Observable<Blob> {
    return this.http.get(this.url(path), { params: toParams(query), responseType: 'blob' });
  }
}

export function emptyMeta(): PageMeta {
  return { current_page: 1, last_page: 1, per_page: 0, total: 0, from: null, to: null };
}

/** Builds query params, skipping empty values and supporting arrays and one level of nesting. */
export function toParams(query?: Query): HttpParams {
  let params = new HttpParams();
  if (!query) {
    return params;
  }
  const add = (key: string, value: unknown) => {
    if (value === null || value === undefined || value === '') {
      return;
    }
    params = params.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
  };
  for (const [key, value] of Object.entries(query)) {
    if (Array.isArray(value)) {
      if (value.length) {
        add(key, value.join(','));
      }
    } else if (value && typeof value === 'object') {
      for (const [sub, v] of Object.entries(value)) {
        add(`${key}[${sub}]`, Array.isArray(v) ? v.join(',') : v);
      }
    } else {
      add(key, value);
    }
  }
  return params;
}

/** Converts a plain object into FormData (booleans as 1/0, arrays as JSON unless files). */
export function toFormData(values: Record<string, unknown>): FormData {
  const form = new FormData();
  for (const [key, value] of Object.entries(values)) {
    if (value === undefined) {
      continue;
    }
    if (value instanceof File || value instanceof Blob) {
      form.append(key, value);
    } else if (Array.isArray(value) && value.every((v) => v instanceof File)) {
      value.forEach((f) => form.append(`${key}[]`, f as File));
    } else if (value === null) {
      form.append(key, '');
    } else if (typeof value === 'boolean') {
      form.append(key, value ? '1' : '0');
    } else if (typeof value === 'object') {
      form.append(key, JSON.stringify(value));
    } else {
      form.append(key, String(value));
    }
  }
  return form;
}
