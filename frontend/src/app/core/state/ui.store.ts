import { Injectable, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class UiStore {
  readonly mobileMenuOpen = signal(false);
  readonly cartDrawerOpen = signal(false);
  readonly vehicleModalOpen = signal(false);
  readonly adminSidebarOpen = signal(false);
}
