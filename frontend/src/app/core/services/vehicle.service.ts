import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../api/api.service';
import { Manufacturer, VehicleModel, VehicleVariant } from '../models/api.models';

/** Data for the Make → Model → Year → Variant selector. */
@Injectable({ providedIn: 'root' })
export class VehicleService {
  private readonly api = inject(ApiService);

  getManufacturers(vehicleType?: 'car' | 'motorcycle' | null): Observable<Manufacturer[]> {
    return this.api.get<Manufacturer[]>('vehicles/manufacturers', { vehicle_type: vehicleType ?? undefined });
  }

  getModels(manufacturerId: number): Observable<VehicleModel[]> {
    return this.api.get<VehicleModel[]>('vehicles/models', { manufacturer_id: manufacturerId });
  }

  getYears(modelId: number): Observable<number[]> {
    return this.api.get<number[]>('vehicles/years', { model_id: modelId });
  }

  getVariants(modelId: number, year?: number | null): Observable<VehicleVariant[]> {
    return this.api.get<VehicleVariant[]>('vehicles/variants', { model_id: modelId, year: year ?? undefined });
  }

  getVariant(id: number): Observable<VehicleVariant> {
    return this.api.get<VehicleVariant>(`vehicles/variants/${id}`);
  }
}
