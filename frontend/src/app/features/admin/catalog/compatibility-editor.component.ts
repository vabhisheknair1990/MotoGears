import { ChangeDetectionStrategy, Component, computed, inject, input, output, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, of } from 'rxjs';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminModel, AdminVariant, CompatibilityInput, CompatibilityRow } from '../data/admin.models';

/**
 * Edits a product's fitment list. Each row targets a manufacturer, a model
 * (all variants) or one exact variant, optionally limited to a year range.
 * Dropdowns are dependent: make → model → variant.
 */
@Component({
  selector: 'adm-compatibility-editor',
  imports: [IconComponent],
  template: `
    <div class="adder">
      <div class="f">
        <label class="label" for="cm-make">Make</label>
        <select id="cm-make" class="select" [value]="make() ?? ''" (change)="setMake($any($event.target).value)">
          <option value="">Choose make…</option>
          @for (m of manufacturers(); track m.id) { <option [value]="m.id">{{ m.name }}</option> }
        </select>
      </div>
      <div class="f">
        <label class="label" for="cm-model">Model</label>
        <select id="cm-model" class="select" [disabled]="!make() || loadingModels()" [value]="model() ?? ''" (change)="setModel($any($event.target).value)">
          <option value="">{{ loadingModels() ? 'Loading…' : 'All models' }}</option>
          @for (m of models(); track m.id) { <option [value]="m.id">{{ m.name }}</option> }
        </select>
      </div>
      <div class="f">
        <label class="label" for="cm-var">Variant</label>
        <select id="cm-var" class="select" [disabled]="!model() || loadingVariants()" [value]="variant() ?? ''" (change)="variant.set(+$any($event.target).value || null)">
          <option value="">{{ loadingVariants() ? 'Loading…' : 'All variants' }}</option>
          @for (v of variants(); track v.id) { <option [value]="v.id">{{ v.name }} ({{ v.year_range }})</option> }
        </select>
      </div>
      <div class="f sm">
        <label class="label" for="cm-yf">Year from</label>
        <input id="cm-yf" class="input" type="number" min="1950" max="2100" [value]="yearFrom() ?? ''" (input)="yearFrom.set(+$any($event.target).value || null)" />
      </div>
      <div class="f sm">
        <label class="label" for="cm-yt">Year to</label>
        <input id="cm-yt" class="input" type="number" min="1950" max="2100" [value]="yearTo() ?? ''" (input)="yearTo.set(+$any($event.target).value || null)" />
      </div>
      <div class="f grow">
        <label class="label" for="cm-notes">Notes</label>
        <input id="cm-notes" class="input" maxlength="190" placeholder="e.g. Front axle only" [value]="notes()" (input)="notes.set($any($event.target).value)" />
      </div>
      <button type="button" class="btn btn-dark add" [disabled]="!make()" (click)="add()"><app-icon name="plus" [size]="16" /> Add fitment</button>
    </div>
    @if (error()) { <p class="error-text">{{ error() }}</p> }

    @if (rows().length) {
      <ul class="rows">
        @for (r of rows(); track $index) {
          <li>
            <app-icon name="car" [size]="16" />
            <span class="grow"><strong>{{ r.manufacturer.name }}</strong>@if (r.model) { › {{ r.model.name }} }@if (r.variant) { › {{ r.variant.name }} }
              @if (!r.model) { <span class="badge">all models</span> } @else if (!r.variant) { <span class="badge">all variants</span> }
              @if (r.year_from || r.year_to) { <span class="text-muted text-sm"> · {{ r.year_from ?? '…' }}–{{ r.year_to ?? 'present' }}</span> }
              @if (r.notes) { <span class="text-muted text-sm"> · {{ r.notes }}</span> }
            </span>
            <button type="button" class="icon-btn sm" (click)="remove($index)" [attr.aria-label]="'Remove fitment ' + r.manufacturer.name"><app-icon name="x" [size]="16" /></button>
          </li>
        }
      </ul>
    } @else {
      <p class="text-muted text-sm empty">No fitment rows yet. Universal products fit every vehicle of their type; otherwise add at least one make.</p>
    }
  `,
  styles: `
    .adder { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
    .f { display: flex; flex-direction: column; gap: 4px; flex: 1 1 150px; min-width: 0; }
    .f.sm { flex: 0 1 100px; }
    .f.grow { flex: 2 1 180px; }
    .f .label { margin: 0; font-size: 12px; }
    .add { flex: none; }
    .rows { list-style: none; margin: 14px 0 0; padding: 0; border: 1px solid var(--line); border-radius: var(--radius-sm); max-height: 320px; overflow-y: auto; }
    .rows li { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--line); font-size: 14px; }
    .rows li:last-child { border-bottom: 0; }
    .rows .grow { flex: 1; min-width: 0; }
    .badge { margin-left: 6px; font-size: 11px; }
    .empty { margin: 12px 0 0; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CompatibilityEditorComponent {
  readonly rows = input<CompatibilityRow[]>([]);
  readonly rowsChange = output<CompatibilityRow[]>();
  private readonly api = inject(AdminApiService);
  private readonly lookups = toSignal(this.api.lookups().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly manufacturers = computed(() => this.lookups()?.manufacturers ?? []);

  protected readonly make = signal<number | null>(null);
  protected readonly model = signal<number | null>(null);
  protected readonly variant = signal<number | null>(null);
  protected readonly yearFrom = signal<number | null>(null);
  protected readonly yearTo = signal<number | null>(null);
  protected readonly notes = signal('');
  protected readonly models = signal<AdminModel[]>([]);
  protected readonly variants = signal<AdminVariant[]>([]);
  protected readonly loadingModels = signal(false);
  protected readonly loadingVariants = signal(false);
  protected readonly error = signal<string | null>(null);

  protected setMake(v: string): void {
    const id = +v || null;
    this.make.set(id);
    this.model.set(null);
    this.variant.set(null);
    this.models.set([]);
    this.variants.set([]);
    if (!id) return;
    this.loadingModels.set(true);
    this.api.get<AdminModel[]>('vehicles/models', { manufacturer_id: id }).subscribe({
      next: (m) => {
        this.models.set(m);
        this.loadingModels.set(false);
      },
      error: () => this.loadingModels.set(false),
    });
  }

  protected setModel(v: string): void {
    const id = +v || null;
    this.model.set(id);
    this.variant.set(null);
    this.variants.set([]);
    if (!id) return;
    this.loadingVariants.set(true);
    this.api.get<AdminVariant[]>('vehicles/variants', { model_id: id }).subscribe({
      next: (list) => {
        this.variants.set(list);
        this.loadingVariants.set(false);
      },
      error: () => this.loadingVariants.set(false),
    });
  }

  protected add(): void {
    const make = this.manufacturers().find((m) => m.id === this.make());
    if (!make) return;
    const yf = this.yearFrom();
    const yt = this.yearTo();
    if (yf && yt && yt < yf) {
      this.error.set('"Year to" must be the same as or after "Year from".');
      return;
    }
    const model = this.models().find((m) => m.id === this.model()) ?? null;
    const variant = this.variants().find((v) => v.id === this.variant()) ?? null;
    const row: CompatibilityRow = {
      manufacturer: { id: make.id, name: make.name },
      model: model ? { id: model.id, name: model.name } : null,
      variant: variant ? { id: variant.id, name: variant.name } : null,
      year_from: yf,
      year_to: yt,
      notes: this.notes().trim() || null,
    };
    const dup = this.rows().some((r) => r.manufacturer.id === row.manufacturer.id && r.model?.id === row.model?.id && r.variant?.id === row.variant?.id && r.year_from === row.year_from && r.year_to === row.year_to);
    if (dup) {
      this.error.set('That fitment is already in the list.');
      return;
    }
    this.error.set(null);
    this.rowsChange.emit([...this.rows(), row]);
    this.variant.set(null);
    this.yearFrom.set(null);
    this.yearTo.set(null);
    this.notes.set('');
  }

  protected remove(i: number): void {
    this.rowsChange.emit(this.rows().filter((_, idx) => idx !== i));
  }
}

export function toCompatibilityInput(rows: CompatibilityRow[]): CompatibilityInput[] {
  return rows.map((r) => ({
    vehicle_manufacturer_id: r.manufacturer.id,
    vehicle_model_id: r.model?.id ?? null,
    vehicle_variant_id: r.variant?.id ?? null,
    year_from: r.year_from,
    year_to: r.year_to,
    notes: r.notes,
  }));
}
