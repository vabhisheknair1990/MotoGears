import { Injectable, inject } from '@angular/core';
import { Observable, shareReplay } from 'rxjs';
import { ApiService } from '../api/api.service';
import { Category } from '../models/api.models';

@Injectable({ providedIn: 'root' })
export class CategoryService {
  private readonly api = inject(ApiService);
  private tree$?: Observable<Category[]>;

  /** Cached category tree (used by the header mega-menu, filters and footer). */
  getTree(): Observable<Category[]> {
    return (this.tree$ ??= this.api.get<Category[]>('categories').pipe(shareReplay({ bufferSize: 1, refCount: false })));
  }

  getCategory(slug: string): Observable<Category> {
    return this.api.get<Category>(`categories/${encodeURIComponent(slug)}`);
  }
}
