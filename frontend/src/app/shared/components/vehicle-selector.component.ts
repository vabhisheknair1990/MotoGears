import { ChangeDetectionStrategy, Component, DestroyRef, OnInit, inject, input, output, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Manufacturer, SelectedVehicle, VehicleModel, VehicleVariant } from '../../core/models/api.models';
import { VehicleService } from '../../core/services/vehicle.service';
import { VehicleStore } from '../../core/state/vehicle.store';
import { IconComponent } from './icon.component';

/**
 * Make → Model → Year → Variant dependent dropdowns. Every list is loaded from the API as the
 * previous choice is made. Emits the chosen vehicle and (optionally) stores it as "My vehicle".
 */
@Component({
  selector: 'app-vehicle-selector',
  imports: [IconComponent],
  template: `
    <div class="vs" [class]="'vs ' + layout()">
      @if (showTypeToggle()) {
        <div class="types" role="tablist" aria-label="Vehicle type">
          <button type="button" role="tab" [attr.aria-selected]="type() === 'car'" [class.on]="type() === 'car'" (click)="setType('car')"><app-icon name="car" [size]="18" /> Car</button>
          <button type="button" role="tab" [attr.aria-selected]="type() === 'motorcycle'" [class.on]="type() === 'motorcycle'" (click)="setType('motorcycle')"><app-icon name="bike" [size]="18" /> Bike</button>
        </div>
      }
      <div class="fields">
        <label class="f">
          <span class="step">1</span>
          <select class="select" aria-label="Make" (change)="onManufacturer($any($event.target).value)">
            <option value="">{{ loadingM() ? 'Loading…' : 'Select make' }}</option>
            @for (m of manufacturers(); track m.id) { <option [value]="m.id" [selected]="m.id === manufacturerId()">{{ m.name }}</option> }
          </select>
        </label>
        <label class="f">
          <span class="step">2</span>
          <select class="select" aria-label="Model" [disabled]="!manufacturerId()" (change)="onModel($any($event.target).value)">
            <option value="">{{ loadingMo() ? 'Loading…' : 'Select model' }}</option>
            @for (m of models(); track m.id) { <option [value]="m.id" [selected]="m.id === modelId()">{{ m.name }}</option> }
          </select>
        </label>
        <label class="f">
          <span class="step">3</span>
          <select class="select" aria-label="Year" [disabled]="!modelId()" (change)="onYear($any($event.target).value)">
            <option value="">Select year</option>
            @for (y of years(); track y) { <option [value]="y" [selected]="y === year()">{{ y }}</option> }
          </select>
        </label>
        <label class="f">
          <span class="step">4</span>
          <select class="select" aria-label="Variant" [disabled]="!year()" (change)="variantId.set(+$any($event.target).value || null)">
            <option value="">{{ loadingV() ? 'Loading…' : 'Select variant' }}</option>
            @for (v of variants(); track v.id) { <option [value]="v.id" [selected]="v.id === variantId()">{{ v.name }}{{ v.fuel_type ? ' · ' + v.fuel_type : '' }}</option> }
          </select>
        </label>
        <button type="button" class="btn btn-primary btn-lg go" [disabled]="!variantId()" (click)="confirm()">
          <app-icon name="search" [size]="18" /> {{ buttonLabel() }}
        </button>
      </div>
    </div>
  `,
  styleUrl: './vehicle-selector.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehicleSelectorComponent implements OnInit {
  private readonly api = inject(VehicleService);
  private readonly store = inject(VehicleStore);
  private readonly destroyRef = inject(DestroyRef);

  readonly layout = input<'hero' | 'stacked' | 'inline'>('hero');
  readonly buttonLabel = input('FIND PARTS');
  readonly showTypeToggle = input(true);
  /** Persist the choice as the shopper's selected vehicle. */
  readonly persist = input(true);
  readonly selected = output<SelectedVehicle>();

  protected readonly type = signal<'car' | 'motorcycle'>('car');
  protected readonly manufacturers = signal<Manufacturer[]>([]);
  protected readonly models = signal<VehicleModel[]>([]);
  protected readonly years = signal<number[]>([]);
  protected readonly variants = signal<VehicleVariant[]>([]);
  protected readonly manufacturerId = signal<number | null>(null);
  protected readonly modelId = signal<number | null>(null);
  protected readonly year = signal<number | null>(null);
  protected readonly variantId = signal<number | null>(null);
  protected readonly loadingM = signal(false);
  protected readonly loadingMo = signal(false);
  protected readonly loadingV = signal(false);

  ngOnInit(): void {
    const current = this.store.selected();
    if (current?.vehicleType === 'motorcycle') this.type.set('motorcycle');
    this.loadManufacturers(current ?? undefined);
  }

  setType(type: 'car' | 'motorcycle'): void {
    if (type === this.type()) return;
    this.type.set(type);
    this.resetFrom('make');
    this.loadManufacturers();
  }

  onManufacturer(value: string): void {
    this.resetFrom('model');
    this.manufacturerId.set(+value || null);
    const id = this.manufacturerId();
    if (!id) return;
    this.loadingMo.set(true);
    this.api.getModels(id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (m) => { this.models.set(m.filter((x) => this.showTypeToggle() ? x.vehicle_type === this.type() : true)); this.loadingMo.set(false); },
      error: () => this.loadingMo.set(false),
    });
  }

  onModel(value: string): void {
    this.resetFrom('year');
    this.modelId.set(+value || null);
    const id = this.modelId();
    if (!id) return;
    this.api.getYears(id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((y) => this.years.set(y));
  }

  onYear(value: string): void {
    this.resetFrom('variant');
    this.year.set(+value || null);
    const modelId = this.modelId();
    if (!modelId || !this.year()) return;
    this.loadingV.set(true);
    this.api.getVariants(modelId, this.year()).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (v) => {
        this.variants.set(v);
        if (v.length === 1) this.variantId.set(v[0].id);
        this.loadingV.set(false);
      },
      error: () => this.loadingV.set(false),
    });
  }

  confirm(): void {
    const m = this.manufacturers().find((x) => x.id === this.manufacturerId());
    const mo = this.models().find((x) => x.id === this.modelId());
    const v = this.variants().find((x) => x.id === this.variantId());
    if (!m || !mo || !v) return;
    const vehicle: SelectedVehicle = {
      manufacturerId: m.id, manufacturerName: m.name, modelId: mo.id, modelName: mo.name,
      year: this.year(), variantId: v.id, variantName: v.name, vehicleType: mo.vehicle_type,
    };
    if (this.persist()) this.store.select(vehicle);
    this.selected.emit(vehicle);
  }

  private loadManufacturers(prefill?: SelectedVehicle): void {
    this.loadingM.set(true);
    this.api.getManufacturers(this.showTypeToggle() ? this.type() : null).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (list) => {
        this.manufacturers.set(list);
        this.loadingM.set(false);
        if (prefill && list.some((m) => m.id === prefill.manufacturerId)) this.prefill(prefill);
      },
      error: () => this.loadingM.set(false),
    });
  }

  /** Restores the previously selected vehicle into the dropdowns. */
  private prefill(v: SelectedVehicle): void {
    this.manufacturerId.set(v.manufacturerId);
    this.api.getModels(v.manufacturerId).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((models) => {
      this.models.set(models.filter((x) => this.showTypeToggle() ? x.vehicle_type === this.type() : true));
      this.modelId.set(v.modelId);
      this.api.getYears(v.modelId).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((years) => {
        this.years.set(years);
        const year = v.year ?? years[0] ?? null;
        this.year.set(year);
        this.api.getVariants(v.modelId, year).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((variants) => {
          this.variants.set(variants);
          this.variantId.set(v.variantId);
        });
      });
    });
  }

  private resetFrom(level: 'make' | 'model' | 'year' | 'variant'): void {
    const order = ['make', 'model', 'year', 'variant'];
    const idx = order.indexOf(level);
    if (idx <= 0) { this.manufacturerId.set(null); this.manufacturers.set([]); }
    if (idx <= 1) { this.modelId.set(null); this.models.set([]); }
    if (idx <= 2) { this.year.set(null); this.years.set([]); }
    this.variantId.set(null);
    this.variants.set([]);
  }
}
