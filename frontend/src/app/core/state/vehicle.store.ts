import { Injectable, computed, signal } from '@angular/core';
import { SelectedVehicle } from '../models/api.models';
import { storage } from '../utils/storage';

const KEY = 'mg.vehicle';

/** The shopper's currently selected vehicle ("My vehicle"), used to filter and badge products. */
@Injectable({ providedIn: 'root' })
export class VehicleStore {
  readonly selected = signal<SelectedVehicle | null>(storage.getJson<SelectedVehicle>(KEY));
  readonly label = computed(() => {
    const v = this.selected();
    return v ? `${v.manufacturerName} ${v.modelName}${v.year ? ' ' + v.year : ''}` : null;
  });
  readonly fullLabel = computed(() => {
    const v = this.selected();
    return v ? `${this.label()} · ${v.variantName}` : null;
  });
  readonly variantId = computed(() => this.selected()?.variantId ?? null);

  select(vehicle: SelectedVehicle | null): void {
    this.selected.set(vehicle);
    storage.setJson(KEY, vehicle);
  }

  clear(): void {
    this.select(null);
  }
}
