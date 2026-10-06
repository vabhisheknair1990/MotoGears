import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Address, AddressInput } from '../../../core/models/api.models';
import { ConfirmService } from '../../../core/services/confirm.service';
import { CustomerService } from '../../../core/services/customer.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage, fieldErrors } from '../../../core/utils/http-errors';
import { AddressFormComponent } from '../../../shared/components/address-form.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { ModalComponent } from '../../../shared/components/modal.component';

@Component({
  selector: 'app-addresses-page',
  imports: [AddressFormComponent, EmptyStateComponent, IconComponent, ModalComponent],
  template: `
    <div class="row between mb-16"><h1 class="h1">Addresses</h1><button class="btn btn-primary" (click)="openNew()"><app-icon name="plus" [size]="16" /> Add address</button></div>
    @if (loading()) {
      <div class="skeleton" style="height: 160px"></div>
    } @else {
      <div class="grid md-grid-2 gap-16">
        @for (a of addresses(); track a.id) {
          <div class="card card-body addr" [class.def]="a.is_default">
            <div class="row gap-8 mb-8"><strong>{{ a.name }}</strong><span class="badge">{{ a.label }}</span>@if (a.is_default) { <span class="badge badge-info">Default</span> }</div>
            <p>{{ a.line1 }}@if (a.line2) {, {{ a.line2 }}}@if (a.landmark) {<br />Near {{ a.landmark }}}<br />{{ a.city }}, {{ a.state }} – {{ a.postal_code }}<br />Phone: {{ a.phone }}</p>
            <div class="row gap-8">
              <button class="btn btn-sm" (click)="edit(a)"><app-icon name="edit" [size]="14" /> Edit</button>
              @if (!a.is_default) { <button class="btn btn-sm btn-ghost" (click)="makeDefault(a)">Set default</button> }
              <button class="btn btn-sm btn-ghost text-danger" (click)="remove(a)"><app-icon name="trash" [size]="14" /> Delete</button>
            </div>
          </div>
        } @empty {
          <div class="card" style="grid-column: 1 / -1"><app-empty-state icon="pin" title="No saved addresses" message="Add an address for faster checkout." /></div>
        }
      </div>
    }
    <app-modal [open]="formOpen()" [title]="editing() ? 'Edit address' : 'Add address'" size="lg" (close)="formOpen.set(false)">
      @if (formOpen()) {
        <app-address-form [value]="editing()" [busy]="busy()" [serverErrors]="errors()" (saved)="save($event)" (cancelled)="formOpen.set(false)" />
      }
    </app-modal>
  `,
  styles: `.h1 { font-size: 28px; margin: 0; } .addr p { font-size: 14px; color: var(--ink-2); } .addr.def { border-color: var(--info); }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AddressesPage {
  private readonly customer = inject(CustomerService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  protected readonly addresses = signal<Address[]>([]);
  protected readonly loading = signal(true);
  protected readonly formOpen = signal(false);
  protected readonly editing = signal<Address | null>(null);
  protected readonly busy = signal(false);
  protected readonly errors = signal<Record<string, string[]>>({});

  constructor() {
    this.load();
  }

  load(): void {
    this.customer.getAddresses().subscribe({ next: (a) => { this.addresses.set(a); this.loading.set(false); }, error: () => this.loading.set(false) });
  }

  openNew(): void {
    this.editing.set(null);
    this.errors.set({});
    this.formOpen.set(true);
  }

  edit(a: Address): void {
    this.editing.set(a);
    this.errors.set({});
    this.formOpen.set(true);
  }

  save(input: AddressInput): void {
    this.busy.set(true);
    const current = this.editing();
    const req = current ? this.customer.updateAddress(current.id, input) : this.customer.createAddress(input);
    req.subscribe({
      next: () => { this.busy.set(false); this.formOpen.set(false); this.toast.success(current ? 'Address updated' : 'Address added'); this.load(); },
      error: (e) => { this.busy.set(false); this.errors.set(fieldErrors(e)); this.toast.error(errorMessage(e)); },
    });
  }

  makeDefault(a: Address): void {
    this.customer.updateAddress(a.id, { is_default: true }).subscribe(() => { this.toast.success('Default address updated'); this.load(); });
  }

  async remove(a: Address): Promise<void> {
    const ok = await this.confirm.ask({ title: 'Delete address?', message: `Delete the ${a.label.toLowerCase()} address for ${a.name}?`, confirmLabel: 'Delete', danger: true });
    if (!ok) return;
    this.customer.deleteAddress(a.id).subscribe(() => { this.toast.info('Address deleted'); this.load(); });
  }
}
