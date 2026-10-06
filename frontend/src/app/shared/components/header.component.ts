import { A11yModule } from '@angular/cdk/a11y';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { catchError, filter, of } from 'rxjs';
import { AuthService } from '../../core/services/auth.service';
import { CategoryService } from '../../core/services/category.service';
import { WishlistService } from '../../core/services/wishlist.service';
import { AuthStore } from '../../core/state/auth.store';
import { CartStore } from '../../core/state/cart.store';
import { UiStore } from '../../core/state/ui.store';
import { VehicleStore } from '../../core/state/vehicle.store';
import { SelectedVehicle } from '../../core/models/api.models';
import { IconComponent } from './icon.component';
import { ModalComponent } from './modal.component';
import { SearchBarComponent } from './search-bar.component';
import { VehicleSelectorComponent } from './vehicle-selector.component';

@Component({
  selector: 'app-header',
  imports: [A11yModule, RouterLink, RouterLinkActive, IconComponent, SearchBarComponent, ModalComponent, VehicleSelectorComponent],
  templateUrl: './header.component.html',
  styleUrl: './header.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HeaderComponent {
  protected readonly auth = inject(AuthStore);
  private readonly authService = inject(AuthService);
  protected readonly cart = inject(CartStore);
  protected readonly wishlist = inject(WishlistService);
  protected readonly vehicle = inject(VehicleStore);
  protected readonly ui = inject(UiStore);
  private readonly router = inject(Router);

  protected readonly categories = toSignal(inject(CategoryService).getTree().pipe(catchError(() => of([]))), { initialValue: [] });
  protected readonly carParts = computed(() => this.categories().find((c) => c.slug === 'car-parts')?.children ?? []);
  protected readonly bikeParts = computed(() => this.categories().find((c) => c.slug === 'motorcycle-parts')?.children ?? []);
  protected readonly accessories = computed(() => [
    ...(this.categories().find((c) => c.slug === 'accessories')?.children ?? []),
    ...(this.categories().find((c) => c.slug === 'oils-maintenance')?.children ?? []),
  ]);
  protected readonly accountOpen = signal(false);
  protected readonly openMenu = signal<string | null>(null);

  constructor() {
    this.router.events.pipe(filter((e) => e instanceof NavigationEnd), takeUntilDestroyed()).subscribe(() => {
      this.ui.mobileMenuOpen.set(false);
      this.accountOpen.set(false);
      this.openMenu.set(null);
    });
  }

  onVehicleSelected(v: SelectedVehicle): void {
    this.ui.vehicleModalOpen.set(false);
    void this.router.navigate(['/vehicles'], { queryParams: { variant: v.variantId } });
  }

  logout(): void {
    this.authService.logout('/');
  }
}
