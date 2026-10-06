import { ChangeDetectionStrategy, Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { Banner, Homepage, SelectedVehicle } from '../../../core/models/api.models';
import { CmsService } from '../../../core/services/cms.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { VehicleStore } from '../../../core/state/vehicle.store';
import { BrandCardComponent } from '../../../shared/components/brand-card.component';
import { CategoryCardComponent } from '../../../shared/components/category-card.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { ProductGridComponent } from '../../../shared/components/product-grid.component';
import { RatingComponent } from '../../../shared/components/rating.component';
import { VehicleSelectorComponent } from '../../../shared/components/vehicle-selector.component';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';

@Component({
  selector: 'app-home-page',
  imports: [RouterLink, IconComponent, VehicleSelectorComponent, ProductGridComponent, CategoryCardComponent, BrandCardComponent, RatingComponent, InrPipe, AppDatePipe],
  templateUrl: './home.page.html',
  styleUrl: './home.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePage implements OnInit {
  private readonly cms = inject(CmsService);
  private readonly seo = inject(SeoService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly vehicle = inject(VehicleStore);

  protected readonly data = signal<Homepage | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly slide = signal(0);
  protected readonly hero = computed(() => this.data()?.hero_banners ?? []);
  protected readonly current = computed<Banner | null>(() => this.hero()[this.slide()] ?? null);

  constructor() {
    // Reload personalised picks whenever the selected vehicle changes.
    toObservable(this.vehicle.variantId).pipe(
      switchMap((variant) => {
        this.loading.set(!this.data());
        return this.cms.getHomepage(variant).pipe(catchError(() => { this.error.set(true); return of(null); }));
      }),
      takeUntilDestroyed(),
    ).subscribe((d) => {
      if (d) this.data.set(d);
      this.loading.set(false);
    });
  }

  ngOnInit(): void {
    this.seo.set({ title: null, description: 'Find the right parts for your vehicle. Genuine car & motorcycle parts, accessories and oils with guaranteed fitment and fast delivery across India.' });
    const timer = setInterval(() => this.next(), 7000);
    this.destroyRef.onDestroy(() => clearInterval(timer));
  }

  next(): void {
    const n = this.hero().length;
    if (n > 1) this.slide.set((this.slide() + 1) % n);
  }

  prev(): void {
    const n = this.hero().length;
    if (n > 1) this.slide.set((this.slide() - 1 + n) % n);
  }

  onVehicle(v: SelectedVehicle): void {
    void this.router.navigate(['/vehicles'], { queryParams: { variant: v.variantId } });
  }

  copyCode(code: string): void {
    void navigator.clipboard?.writeText(code).then(() => this.toast.success(`Code ${code} copied — apply it in your cart`));
  }
}
