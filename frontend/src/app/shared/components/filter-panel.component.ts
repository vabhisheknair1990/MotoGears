import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { Facets } from '../../core/models/api.models';
import { IconComponent } from './icon.component';

export interface ActiveFilters {
  brand: string[];
  category: string | null;
  min_price: number | null;
  max_price: number | null;
  rating: number | null;
  discount: number | null;
  in_stock: boolean;
  attributes: Record<string, string[]>;
}

/** Sidebar / drawer of catalogue filters driven entirely by API facet counts. */
@Component({
  selector: 'app-filter-panel',
  imports: [IconComponent],
  template: `
    @if (facets(); as f) {
      <div class="fp">
        @if (!hideCategory() && f.categories.length > 1) {
          <details open>
            <summary>Category</summary>
            <ul class="list-reset opts">
              @for (c of f.categories; track c.id) {
                <li><label class="check"><input type="radio" name="fp-cat" [checked]="active().category === c.slug" (change)="set({ category: c.slug })" />
                  <span class="grow">{{ c.name }}</span><span class="n">{{ c.count }}</span></label></li>
              }
              @if (active().category) { <li><button type="button" class="clear" (click)="set({ category: null })">Any category</button></li> }
            </ul>
          </details>
        }
        @if (!hideBrand() && f.brands.length) {
          <details open>
            <summary>Brand</summary>
            <ul class="list-reset opts" [class.scroll]="f.brands.length > 8">
              @for (b of f.brands; track b.id) {
                <li><label class="check"><input type="checkbox" [checked]="active().brand.includes(b.slug)" (change)="toggleBrand(b.slug)" />
                  <span class="grow">{{ b.name }}</span><span class="n">{{ b.count }}</span></label></li>
              }
            </ul>
          </details>
        }
        <details open>
          <summary>Price</summary>
          <div class="price">
            <input class="input input-sm" type="number" inputmode="numeric" min="0" placeholder="Min ₹" [value]="active().min_price ?? ''" #min />
            <span>–</span>
            <input class="input input-sm" type="number" inputmode="numeric" min="0" placeholder="Max ₹" [value]="active().max_price ?? ''" #max />
            <button type="button" class="btn btn-sm" (click)="set({ min_price: min.value ? +min.value : null, max_price: max.value ? +max.value : null })" aria-label="Apply price"><app-icon name="arrow-right" [size]="14" /></button>
          </div>
          <div class="presets">
            @for (p of pricePresets; track p.label) {
              <button type="button" class="chip" [class.active]="active().min_price === p.min && active().max_price === p.max" (click)="set({ min_price: p.min, max_price: p.max })">{{ p.label }}</button>
            }
          </div>
        </details>
        <details open>
          <summary>Customer rating</summary>
          <ul class="list-reset opts">
            @for (r of f.ratings; track r) {
              <li><label class="check"><input type="radio" name="fp-rating" [checked]="active().rating === r" (change)="set({ rating: r })" /><span>{{ r }}★ & above</span></label></li>
            }
          </ul>
        </details>
        <details open>
          <summary>Availability & discount</summary>
          <ul class="list-reset opts">
            <li><label class="check"><input type="checkbox" [checked]="active().in_stock" (change)="set({ in_stock: !active().in_stock })" /><span>In stock only</span></label></li>
            @for (d of f.discounts; track d) {
              <li><label class="check"><input type="radio" name="fp-disc" [checked]="active().discount === d" (change)="set({ discount: d })" /><span>{{ d }}% off or more</span></label></li>
            }
          </ul>
        </details>
        @for (a of f.attributes; track a.slug) {
          <details [open]="!!active().attributes[a.slug]?.length">
            <summary>{{ a.name }}</summary>
            <ul class="list-reset opts">
              @for (v of a.values; track v.slug) {
                <li><label class="check"><input type="checkbox" [checked]="isAttr(a.slug, v.slug)" (change)="toggleAttr(a.slug, v.slug)" />
                  <span class="grow">{{ v.value }}</span><span class="n">{{ v.count }}</span></label></li>
              }
            </ul>
          </details>
        }
        @if (activeCount() > 0) {
          <button type="button" class="btn btn-block mt-16" (click)="clearAll.emit()"><app-icon name="x" [size]="16" /> Clear all filters</button>
        }
      </div>
    }
  `,
  styles: `
    .fp { display: flex; flex-direction: column; }
    details { border-bottom: 1px solid var(--line); padding: 14px 0; }
    summary { font-weight: 700; font-size: 14px; cursor: pointer; list-style: none; display: flex; justify-content: space-between; }
    summary::after { content: '+'; color: var(--muted); }
    details[open] summary::after { content: '−'; }
    .opts { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
    .opts.scroll { max-height: 260px; overflow-y: auto; padding-right: 4px; }
    .opts .check { width: 100%; font-size: 13.5px; }
    .n { color: var(--subtle); font-size: 12px; }
    .clear { border: 0; background: none; color: var(--brand); font-size: 13px; font-weight: 600; padding: 0; }
    .price { display: flex; align-items: center; gap: 6px; margin-top: 12px; }
    .price .input { min-width: 0; }
    .presets { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .presets .chip { height: 28px; font-size: 12px; cursor: pointer; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FilterPanelComponent {
  readonly facets = input<Facets | null>(null);
  readonly active = input.required<ActiveFilters>();
  readonly hideCategory = input(false);
  readonly hideBrand = input(false);
  readonly changed = output<Partial<ActiveFilters>>();
  readonly clearAll = output<void>();

  protected readonly pricePresets = [
    { label: 'Under ₹500', min: null, max: 500 },
    { label: '₹500–2,000', min: 500, max: 2000 },
    { label: '₹2,000–10,000', min: 2000, max: 10000 },
    { label: 'Above ₹10,000', min: 10000, max: null },
  ];
  protected readonly activeCount = computed(() => {
    const a = this.active();
    return a.brand.length + (a.category ? 1 : 0) + (a.min_price !== null || a.max_price !== null ? 1 : 0) + (a.rating ? 1 : 0)
      + (a.discount ? 1 : 0) + (a.in_stock ? 1 : 0) + Object.values(a.attributes).reduce((n, v) => n + v.length, 0);
  });
  readonly mobileOpen = signal(false);

  set(patch: Partial<ActiveFilters>): void {
    this.changed.emit(patch);
  }

  toggleBrand(slug: string): void {
    const cur = this.active().brand;
    this.set({ brand: cur.includes(slug) ? cur.filter((b) => b !== slug) : [...cur, slug] });
  }

  isAttr(attr: string, value: string): boolean {
    return this.active().attributes[attr]?.includes(value) ?? false;
  }

  toggleAttr(attr: string, value: string): void {
    const attrs = { ...this.active().attributes };
    const cur = attrs[attr] ?? [];
    attrs[attr] = cur.includes(value) ? cur.filter((v) => v !== value) : [...cur, value];
    if (!attrs[attr].length) delete attrs[attr];
    this.set({ attributes: attrs });
  }
}
