import { Injectable, inject } from '@angular/core';
import { Observable, shareReplay } from 'rxjs';
import { ApiService } from '../api/api.service';
import { Brand } from '../models/api.models';

@Injectable({ providedIn: 'root' })
export class BrandService {
  private readonly api = inject(ApiService);
  private brands$?: Observable<Brand[]>;

  getBrands(): Observable<Brand[]> {
    return (this.brands$ ??= this.api.get<Brand[]>('brands').pipe(shareReplay({ bufferSize: 1, refCount: false })));
  }

  getBrand(slug: string): Observable<Brand> {
    return this.api.get<Brand>(`brands/${encodeURIComponent(slug)}`);
  }
}
