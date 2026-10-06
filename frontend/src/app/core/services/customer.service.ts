import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiService } from '../api/api.service';
import { silent } from '../api/http-context';
import { AccountDashboard, Address, AddressInput, AppNotification, CustomerVehicle, Page, User } from '../models/api.models';
import { AuthStore } from '../state/auth.store';

/** The signed-in customer's own data: profile, address book, garage and notifications. */
@Injectable({ providedIn: 'root' })
export class CustomerService {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthStore);

  getDashboard(): Observable<AccountDashboard> {
    return this.api.get<AccountDashboard>('me/dashboard');
  }

  updateProfile(input: Partial<Pick<User, 'name' | 'email' | 'phone' | 'marketing_opt_in'>>): Observable<User> {
    return this.api.patch<User>('me', input, silent()).pipe(map((r) => { this.auth.setUser(r.data); return r.data; }));
  }

  changePassword(input: { current_password: string; password: string; password_confirmation: string }): Observable<string> {
    return this.api.put<null>('me/password', input, silent()).pipe(map((r) => r.message));
  }

  getAddresses(): Observable<Address[]> {
    return this.api.get<Address[]>('me/addresses');
  }

  createAddress(input: AddressInput): Observable<Address> {
    return this.api.post<Address>('me/addresses', input, silent()).pipe(map((r) => r.data));
  }

  updateAddress(id: number, input: Partial<AddressInput>): Observable<Address> {
    return this.api.patch<Address>(`me/addresses/${id}`, input, silent()).pipe(map((r) => r.data));
  }

  deleteAddress(id: number): Observable<unknown> {
    return this.api.delete(`me/addresses/${id}`);
  }

  getVehicles(): Observable<CustomerVehicle[]> {
    return this.api.get<CustomerVehicle[]>('me/vehicles');
  }

  saveVehicle(input: { vehicle_variant_id: number; year?: number | null; nickname?: string | null; registration_number?: string | null; is_default?: boolean }): Observable<CustomerVehicle> {
    return this.api.post<CustomerVehicle>('me/vehicles', input, silent()).pipe(map((r) => r.data));
  }

  setDefaultVehicle(id: number): Observable<CustomerVehicle> {
    return this.api.patch<CustomerVehicle>(`me/vehicles/${id}`, { is_default: true }).pipe(map((r) => r.data));
  }

  deleteVehicle(id: number): Observable<unknown> {
    return this.api.delete(`me/vehicles/${id}`);
  }

  getNotifications(page = 1): Observable<Page<AppNotification, { unread: number }>> {
    return this.api.page<AppNotification, { unread: number }>('me/notifications', { page });
  }

  markNotificationsRead(ids: string[] = []): Observable<unknown> {
    return this.api.post('me/notifications/read', { ids });
  }
}
