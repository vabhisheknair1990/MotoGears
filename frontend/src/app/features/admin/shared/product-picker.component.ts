import { ChangeDetectionStrategy, Component, DestroyRef, inject, input, output, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject, catchError, debounceTime, distinctUntilChanged, of, switchMap } from 'rxjs';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminProductRow } from '../data/admin.models';

export interface PickedProduct { id: number; name: string; sku?: string }

/** Async product multi-select (used for coupon product restrictions). */
@Component({
  selector: 'adm-product-picker',
  imports: [IconComponent],
  template: `
    <div class="picked">
      @for (p of value(); track p.id) {
        <span class="chip">{{ p.name }} @if (p.sku) { <span class="text-subtle mono">{{ p.sku }}</span> }
          <button type="button" (click)="remove(p.id)" [attr.aria-label]="'Remove ' + p.name"><app-icon name="x" [size]="12" /></button></span>
      }
    </div>
    <div class="box">
      <input class="input input-sm" type="search" placeholder="Search products by name or SKU…" aria-label="Search products" (input)="q$.next($any($event.target).value)" (focus)="open.set(true)" (blur)="closeSoon()" />
      @if (open() && results().length) {
        <ul class="menu" role="listbox">
          @for (r of results(); track r.id) {
            <li role="option" [attr.aria-selected]="isPicked(r.id)" (mousedown)="$event.preventDefault(); toggle(r)">
              <span class="truncate">{{ r.name }}</span><span class="mono text-xs text-muted">{{ r.sku }}</span>
              @if (isPicked(r.id)) { <app-icon name="check" [size]="14" /> }
            </li>
          }
        </ul>
      }
    </div>
  `,
  styles: `
    .picked { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
    .chip { padding: 3px 4px 3px 10px; font-size: 12.5px; gap: 6px; }
    .chip button { border: 0; background: transparent; cursor: pointer; display: inline-grid; place-items: center; color: inherit; }
    .box { position: relative; }
    .menu { position: absolute; z-index: 5; left: 0; right: 0; top: calc(100% + 4px); background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); list-style: none; margin: 0; padding: 4px; max-height: 260px; overflow-y: auto; }
    li { display: flex; gap: 8px; align-items: center; padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 13px; }
    li:hover { background: var(--line-2); }
    li .truncate { flex: 1; min-width: 0; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductPickerComponent {
  readonly value = input<PickedProduct[]>([]);
  readonly valueChange = output<PickedProduct[]>();
  protected readonly q$ = new Subject<string>();
  protected readonly results = signal<AdminProductRow[]>([]);
  protected readonly open = signal(false);
  private readonly api = inject(AdminApiService);

  constructor() {
    this.q$
      .pipe(
        debounceTime(250),
        distinctUntilChanged(),
        switchMap((q) => (q.trim().length < 2 ? of({ items: [] as AdminProductRow[] }) : this.api.page<AdminProductRow>('products', { search: q.trim(), per_page: 10 }).pipe(catchError(() => of({ items: [] as AdminProductRow[] }))))),
        takeUntilDestroyed(inject(DestroyRef)),
      )
      .subscribe((r) => this.results.set(r.items));
  }

  protected isPicked(id: number): boolean {
    return this.value().some((p) => p.id === id);
  }

  protected toggle(r: AdminProductRow): void {
    this.valueChange.emit(this.isPicked(r.id) ? this.value().filter((p) => p.id !== r.id) : [...this.value(), { id: r.id, name: r.name, sku: r.sku }]);
  }

  protected remove(id: number): void {
    this.valueChange.emit(this.value().filter((p) => p.id !== id));
  }

  protected closeSoon(): void {
    setTimeout(() => this.open.set(false), 150);
  }
}
