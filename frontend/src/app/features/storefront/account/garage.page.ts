import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { CustomerVehicle, SelectedVehicle } from '../../../core/models/api.models';
import { ConfirmService } from '../../../core/services/confirm.service';
import { CustomerService } from '../../../core/services/customer.service';
import { ToastService } from '../../../core/services/toast.service';
import { VehicleStore } from '../../../core/state/vehicle.store';
import { errorMessage } from '../../../core/utils/http-errors';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { VehicleSelectorComponent } from '../../../shared/components/vehicle-selector.component';

@Component({
  selector: 'app-garage-page',
  imports: [IconComponent, EmptyStateComponent, VehicleSelectorComponent],
  template: `
    <h1 class="h1">My garage</h1>
    <p class="text-muted">Save your cars and bikes to get personalised compatibility, search results and recommendations.</p>
    <div class="card card-body mb-16">
      <h3>Add a vehicle</h3>
      <app-vehicle-selector layout="stacked" buttonLabel="Save to garage" [persist]="false" (selected)="add($event)" />
    </div>
    @if (loading()) {
      <div class="skeleton" style="height: 120px"></div>
    } @else {
      <div class="grid md-grid-2 gap-16">
        @for (v of vehicles(); track v.id) {
          <div class="card card-body veh" [class.def]="v.is_default">
            <div class="row gap-16">
              <div class="ic"><app-icon [name]="v.variant.model?.vehicle_type === 'motorcycle' ? 'bike' : 'car'" [size]="28" /></div>
              <div class="grow">
                <strong>{{ v.nickname || v.variant.full_name }}</strong>
                <span class="text-sm text-muted">{{ v.variant.full_name }}</span>
                <span class="text-xs text-muted">{{ v.year ?? v.variant.year_range }} · {{ v.variant.fuel_type }} · {{ v.variant.transmission }}@if (v.registration_number) { · {{ v.registration_number }} }</span>
              </div>
              @if (v.is_default) { <span class="badge badge-info">Default</span> }
            </div>
            <div class="row wrap gap-8 mt-16">
              <button class="btn btn-sm btn-primary" (click)="shop(v)"><app-icon name="search" [size]="14" /> Shop parts</button>
              @if (!v.is_default) { <button class="btn btn-sm" (click)="setDefault(v)">Make default</button> }
              <button class="btn btn-sm btn-ghost text-danger" (click)="remove(v)"><app-icon name="trash" [size]="14" /> Remove</button>
            </div>
          </div>
        } @empty {
          <div class="card" style="grid-column: 1 / -1"><app-empty-state icon="car" title="No saved vehicles yet" message="Use the selector above to add your first vehicle." /></div>
        }
      </div>
    }
  `,
  styles: `
    .h1 { font-size: 28px; margin: 0 0 4px; } h3 { font-size: 15px; }
    .veh .grow { display: flex; flex-direction: column; } .veh.def { border-color: var(--info); }
    .ic { width: 52px; height: 52px; border-radius: 12px; background: var(--brand-50); color: var(--brand); display: grid; place-items: center; flex: none; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GaragePage {
  private readonly customer = inject(CustomerService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly store = inject(VehicleStore);
  private readonly router = inject(Router);
  protected readonly vehicles = signal<CustomerVehicle[]>([]);
  protected readonly loading = signal(true);

  constructor() {
    this.load();
  }

  load(): void {
    this.customer.getVehicles().subscribe({ next: (v) => { this.vehicles.set(v); this.loading.set(false); }, error: () => this.loading.set(false) });
  }

  add(v: SelectedVehicle): void {
    this.customer.saveVehicle({ vehicle_variant_id: v.variantId, year: v.year }).subscribe({
      next: () => { this.toast.success(`${v.manufacturerName} ${v.modelName} added to your garage`); this.load(); },
      error: (e) => this.toast.error(errorMessage(e)),
    });
  }

  setDefault(v: CustomerVehicle): void {
    this.customer.setDefaultVehicle(v.id).subscribe(() => { this.toast.success('Default vehicle updated'); this.load(); });
  }

  shop(v: CustomerVehicle): void {
    const m = v.variant.model;
    if (m?.manufacturer) {
      this.store.select({
        manufacturerId: m.manufacturer.id, manufacturerName: m.manufacturer.name, modelId: m.id, modelName: m.name,
        year: v.year, variantId: v.variant.id, variantName: v.variant.name, vehicleType: m.vehicle_type,
      });
    }
    void this.router.navigate(['/vehicles'], { queryParams: { variant: v.variant.id } });
  }

  async remove(v: CustomerVehicle): Promise<void> {
    if (!(await this.confirm.ask({ title: 'Remove vehicle?', message: `Remove ${v.nickname || v.variant.full_name} from your garage?`, confirmLabel: 'Remove', danger: true }))) return;
    this.customer.deleteVehicle(v.id).subscribe(() => { this.toast.info('Vehicle removed'); this.load(); });
  }
}
