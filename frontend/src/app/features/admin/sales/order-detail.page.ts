import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { AuthStore } from '../../../core/state/auth.store';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { AppDatePipe, InrPipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { OrderTimelineComponent } from '../../../shared/components/order-timeline.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminOrder } from '../data/admin.models';
import { PageHeaderComponent } from '../shared/page-header.component';

@Component({
  selector: 'adm-order-detail-page',
  imports: [ReactiveFormsModule, RouterLink, AppDatePipe, InrPipe, EmptyStateComponent, IconComponent, OrderTimelineComponent, StatusBadgeComponent, PageHeaderComponent],
  templateUrl: './order-detail.page.html',
  styleUrl: './order-detail.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrderDetailPage {
  readonly id = input.required<string>();
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly seo = inject(SeoService);
  private readonly fb = inject(FormBuilder);
  protected readonly auth = inject(AuthStore);
  protected readonly order = signal<AdminOrder | null>(null);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');
  protected readonly savingStatus = signal(false);
  protected readonly savingNotes = signal(false);
  protected readonly canManage = computed(() => this.auth.hasPermission('orders.manage'));

  protected readonly statusForm = this.fb.nonNullable.group({
    status: ['', Validators.required],
    comment: ['', Validators.maxLength(500)],
    tracking_number: ['', Validators.maxLength(60)],
    carrier: ['', Validators.maxLength(60)],
    notify_customer: [true],
  });
  protected readonly notesForm = this.fb.nonNullable.group({
    admin_notes: ['', Validators.maxLength(2000)],
    tracking_number: ['', Validators.maxLength(60)],
    carrier: ['', Validators.maxLength(60)],
  });
  protected readonly nextStatus = signal('');
  protected readonly needsTracking = computed(() => this.nextStatus() === 'shipped');

  constructor() {
    effect(() => this.load(this.id()));
    this.statusForm.controls.status.valueChanges.subscribe((v) => this.nextStatus.set(v));
  }

  protected load(id: string): void {
    this.state.set('loading');
    this.api.get<AdminOrder>(`orders/${id}`).subscribe({
      next: (o) => this.setOrder(o),
      error: () => this.state.set('missing'),
    });
  }

  private setOrder(o: AdminOrder): void {
    this.order.set(o);
    this.state.set('ready');
    this.seo.set({ title: `Order ${o.order_number}` });
    this.statusForm.reset({ status: '', comment: '', tracking_number: o.tracking_number ?? '', carrier: o.carrier ?? '', notify_customer: true });
    this.notesForm.reset({ admin_notes: o.admin_notes ?? '', tracking_number: o.tracking_number ?? '', carrier: o.carrier ?? '' });
  }

  protected updateStatus(): void {
    const o = this.order();
    this.statusForm.markAllAsTouched();
    if (!o || this.statusForm.invalid || this.savingStatus()) return;
    const v = this.statusForm.getRawValue();
    this.savingStatus.set(true);
    this.api
      .patch<AdminOrder>(`orders/${o.id}/status`, {
        status: v.status,
        comment: v.comment || null,
        tracking_number: v.tracking_number || null,
        carrier: v.carrier || null,
        notify_customer: v.notify_customer,
      })
      .subscribe({
        next: (res) => {
          this.savingStatus.set(false);
          this.toast.success(res.message || 'Order status updated');
          if (res.data?.id) this.setOrder(res.data);
          else this.load(String(o.id));
        },
        error: (err) => {
          this.savingStatus.set(false);
          applyServerErrors(this.statusForm, err);
          this.toast.error(errorMessage(err));
        },
      });
  }

  protected saveNotes(): void {
    const o = this.order();
    if (!o || this.notesForm.invalid || this.savingNotes()) return;
    const v = this.notesForm.getRawValue();
    this.savingNotes.set(true);
    this.api.patch<AdminOrder>(`orders/${o.id}`, { admin_notes: v.admin_notes || null, tracking_number: v.tracking_number || null, carrier: v.carrier || null }).subscribe({
      next: (res) => {
        this.savingNotes.set(false);
        this.toast.success('Order details saved');
        if (res.data?.id) this.setOrder(res.data);
      },
      error: (err) => {
        this.savingNotes.set(false);
        applyServerErrors(this.notesForm, err);
      },
    });
  }

  protected copy(text: string): void {
    void navigator.clipboard?.writeText(text).then(() => this.toast.success('Copied'));
  }
}
