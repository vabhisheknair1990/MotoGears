import { Injectable, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, map, of } from 'rxjs';
import { CmsService } from './cms.service';

/**
 * What the store sells right now (Admin → Settings → "Sell car parts"). The API already leaves
 * car products, categories and makes out; this lets the UI drop its car wording and controls.
 * Until settings load, car UI stays hidden (no flash of a menu that then disappears).
 */
@Injectable({ providedIn: 'root' })
export class CatalogScopeService {
  private readonly settings = toSignal(inject(CmsService).getSettings().pipe(map((s) => !!s.sell_car_parts), catchError(() => of(false))), { initialValue: undefined });

  readonly sellsCars = computed(() => this.settings() === true);
  /** Settings have arrived (before that, sellsCars() is false by default). */
  readonly known = computed(() => this.settings() !== undefined);
  /** "car & bike" or "bike", for sentences. */
  readonly vehicleWords = computed(() => (this.sellsCars() ? 'car & bike' : 'bike'));
}
