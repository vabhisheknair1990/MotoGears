import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, of } from 'rxjs';
import { BrandService } from '../../../core/services/brand.service';
import { SeoService } from '../../../core/services/seo.service';
import { BrandCardComponent } from '../../../shared/components/brand-card.component';
import { BreadcrumbComponent } from '../../../shared/components/breadcrumb.component';
import { SkeletonComponent } from '../../../shared/components/skeleton.component';

@Component({
  selector: 'app-brands-page',
  imports: [BrandCardComponent, BreadcrumbComponent, SkeletonComponent],
  template: `
    <div class="container">
      <div class="page-title"><app-breadcrumb [items]="[{ label: 'Home', link: '/' }, { label: 'Brands' }]" /></div>
      <div class="row between wrap mb-16">
        <div><h1>Shop by brand</h1><p class="text-muted mb-0">{{ brands()?.length ?? 0 }} trusted manufacturers, all sourced from authorised distributors.</p></div>
        <input class="input" style="max-width: 280px" type="search" placeholder="Filter brands…" aria-label="Filter brands" (input)="filter.set($any($event.target).value)" />
      </div>
      @if (!brands()) {
        <div class="grid-b"><app-skeleton [count]="12" /></div>
      } @else {
        <div class="grid-b">
          @for (b of visible(); track b.id) {
            <div class="bwrap">
              <app-brand-card [brand]="b" />
              <div class="meta"><strong>{{ b.name }}</strong><span class="text-muted text-sm">{{ b.products_count }} products · {{ b.country }}</span></div>
            </div>
          } @empty {
            <p class="text-muted">No brands match “{{ filter() }}”.</p>
          }
        </div>
      }
    </div>
  `,
  styles: `
    .grid-b { display: grid; gap: 16px; grid-template-columns: repeat(2, 1fr); }
    @media (min-width: 768px) { .grid-b { grid-template-columns: repeat(4, 1fr); } }
    @media (min-width: 1100px) { .grid-b { grid-template-columns: repeat(6, 1fr); } }
    .meta { display: flex; flex-direction: column; padding: 8px 2px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandsPage {
  protected readonly brands = toSignal(inject(BrandService).getBrands().pipe(catchError(() => of([]))));
  protected readonly filter = signal('');
  protected readonly visible = computed(() => (this.brands() ?? []).filter((b) => b.name.toLowerCase().includes(this.filter().toLowerCase())));

  constructor() {
    inject(SeoService).set({ title: 'All brands', description: 'Shop genuine auto parts and accessories by brand — Bosch, Brembo, Motul, Castrol, Philips, Hella and more.' });
  }
}
