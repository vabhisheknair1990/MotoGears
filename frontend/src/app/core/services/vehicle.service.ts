import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../api/api.service';
import { Manufacturer, VehicleModel, VehicleVariant } from '../models/api.models';

/** Data for the Make → Model → Variant selector. */
@Injectable({ providedIn: 'root' })
export class VehicleService {
  private readonly api = inject(ApiService);

  getManufacturers(vehicleType?: 'car' | 'motorcycle' | null): Observable<Manufacturer[]> {
    return this.api.get<Manufacturer[]>('vehicles/manufacturers', { vehicle_type: vehicleType ?? undefined });
  }

  getModels(manufacturerId: number): Observable<VehicleModel[]> {
    return this.api.get<VehicleModel[]>('vehicles/models', { manufacturer_id: manufacturerId });
  }

  getVariants(modelId: number): Observable<VehicleVariant[]> {
    return this.api.get<VehicleVariant[]>('vehicles/variants', { model_id: modelId });
  }

  getVariant(id: number): Observable<VehicleVariant> {
    return this.api.get<VehicleVariant>(`vehicles/variants/${id}`);
  }
}
