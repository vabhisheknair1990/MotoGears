import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ConfirmService } from '../../../core/services/confirm.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { AppDatePipe } from '../../../shared/pipes/inr.pipe';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { RatingComponent } from '../../../shared/components/rating.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminReview } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { PageHeaderComponent } from '../shared/page-header.component';
import { SearchInputComponent } from '../shared/search-input.component';

@Component({
  selector: 'adm-reviews-page',
  imports: [RouterLink, AppDatePipe, EmptyStateComponent, IconComponent, PaginationComponent, RatingComponent, StatusBadgeComponent, PageHeaderComponent, SearchInputComponent],
  template: `
    <adm-page-header title="Reviews" subtitle="Approve reviews before they appear on product pages. Featured reviews are pinned to the top." />
    <div class="tabs" role="tablist">
      @for (t of tabs; track t.value) {
        <button type="button" role="tab" [class.active]="list.value('status') === t.value" [attr.aria-selected]="list.value('status') === t.value" (click)="setStatus(t.value)">
          {{ t.label }} @if (t.value && counts()[t.value] !== undefined) { <span class="tab-count">{{ counts()[t.value] }}</span> }
        </button>
      }
    </div>
    <section class="card">
      <div class="toolbar">
        <adm-search placeholder="Search title, comment, product or customer…" [value]="list.value('search')" (search)="list.set({ search: $event || null })" />
        <select class="select input-sm" aria-label="Rating" [value]="list.value('rating')" (change)="list.set({ rating: $any($event.target).value || null })">
          <option value="">Any rating</option>@for (r of [5, 4, 3, 2, 1]; track r) { <option [value]="r">{{ r }} stars</option> }
        </select>
        <span class="grow"></span>
        @if (selected().size) {
          <span class="text-sm fw-600">{{ selected().size }} selected</span>
          <button type="button" class="btn btn-sm" (click)="bulk('approved')" [disabled]="busy()"><app-icon name="check" [size]="14" /> Approve</button>
          <button type="button" class="btn btn-sm" (click)="bulk('rejected')" [disabled]="busy()"><app-icon name="x" [size]="14" /> Reject</button>
        }
      </div>
      @if (list.items().length) {
        <label class="check selall"><input type="checkbox" [checked]="allSelected()" (change)="toggleAll($any($event.target).checked)" /> Select all on this page</label>
      }
      <ul class="reviews" [class.busy]="list.loading()">
        @for (r of list.items(); track r.id) {
          <li class="rv" [class.sel]="selected().has(r.id)">
            <input type="checkbox" class="pick" [checked]="selected().has(r.id)" (change)="toggle(r.id)" [attr.aria-label]="'Select review ' + r.title" />
            <div class="body">
              <div class="head">
                <app-rating [value]="r.rating" [size]="15" />
                <strong>{{ r.title }}</strong>
                <app-status-badge [status]="r.status" />
                @if (r.is_featured) { <span class="badge badge-accent">Featured</span> }
                @if (r.is_verified_purchase) { <span class="badge badge-success">Verified purchase</span> }
              </div>
              <p class="comment">{{ r.comment }}</p>
              @if (r.images?.length) {
                <div class="imgs">@for (im of r.images; track im.id) { <a [href]="im.url" target="_blank" rel="noopener"><img [src]="im.url" alt="Review photo" /></a> }</div>
              }
              <div class="meta text-sm text-muted">
                by <span class="fw-600">{{ r.author }}</span>@if (r.customer) { (<a class="link" [routerLink]="['/admin/customers', r.customer.id]">{{ r.customer.email }}</a>) }
                on @if (r.product) { <a class="link" [routerLink]="['/products', r.product.slug]" target="_blank">{{ r.product.name }}</a> } · {{ r.created_at | appDate }} · {{ r.helpful_count }} found helpful
              </div>
            </div>
            <div class="acts">
              @if (r.status !== 'approved') { <button type="button" class="btn btn-sm" (click)="update(r, { status: 'approved' })" [disabled]="busy()"><app-icon name="check" [size]="14" /> Approve</button> }
              @if (r.status !== 'rejected') { <button type="button" class="btn btn-sm btn-ghost" (click)="update(r, { status: 'rejected' })" [disabled]="busy()">Reject</button> }
              <button type="button" class="btn btn-sm btn-ghost" (click)="update(r, { is_featured: !r.is_featured })" [disabled]="busy() || r.status !== 'approved'" [title]="r.status !== 'approved' ? 'Approve first to feature' : ''">{{ r.is_featured ? 'Unfeature' : 'Feature' }}</button>
              <button type="button" class="icon-btn sm danger" (click)="remove(r)" aria-label="Delete review"><app-icon name="trash" [size]="16" /></button>
            </div>
          </li>
        } @empty {
          @if (!list.loading()) { <li><app-empty-state icon="star" title="No reviews here" [message]="list.value('status') === 'pending' ? 'Nothing waiting for moderation. Nice!' : 'Try another filter.'" /></li> }
          @else { @for (k of [1, 2, 3]; track k) { <li class="rv"><div class="skeleton" style="height:70px;width:100%"></div></li> } }
        }
      </ul>
      <div class="pg"><app-pagination [meta]="list.meta()" (pageChange)="list.goTo($event)" /></div>
    </section>
  `,
  styles: `
    .tabs { margin-bottom: 14px; }
    .tab-count { background: var(--line-2); border-radius: 999px; padding: 0 7px; font-size: 11px; margin-left: 4px; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .toolbar .select { width: auto; } .grow { flex: 1; }
    .selall { padding: 10px 16px 0; font-size: 13px; }
    .reviews { list-style: none; margin: 0; padding: 0; }
    .reviews.busy { opacity: .6; }
    .rv { display: grid; grid-template-columns: auto 1fr; gap: 12px 14px; padding: 16px; border-bottom: 1px solid var(--line); }
    .rv.sel { background: #fff7f8; }
    .pick { margin-top: 4px; width: 16px; height: 16px; accent-color: var(--brand); }
    .head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .comment { margin: 6px 0; color: var(--ink-2); line-height: 1.55; }
    .imgs { display: flex; gap: 6px; margin-bottom: 6px; }
    .imgs img { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); }
    .acts { grid-column: 2; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    @media (min-width: 900px) { .rv { grid-template-columns: auto 1fr auto; } .acts { grid-column: 3; justify-content: flex-end; align-self: start; } }
    .icon-btn.danger:hover { color: #dc2626; background: #fee2e2; }
    .pg { padding: 0 16px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReviewsPage {
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  protected readonly list = new ListState<AdminReview, { counts: Record<string, number> }>((q) => this.api.page('reviews', q), { status: 'pending', per_page: 15 });
  protected readonly counts = computed(() => this.list.meta()?.counts ?? {});
  protected readonly selected = signal(new Set<number>());
  protected readonly busy = signal(false);
  protected readonly allSelected = computed(() => this.list.items().length > 0 && this.list.items().every((r) => this.selected().has(r.id)));
  protected readonly tabs = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: '', label: 'All' },
  ];

  constructor() {
    inject(SeoService).set({ title: 'Reviews' });
    this.list.load();
  }

  protected setStatus(status: string): void {
    this.selected.set(new Set());
    this.list.set({ status: status || null });
  }

  protected toggle(id: number): void {
    this.selected.update((s) => {
      const n = new Set(s);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
  }

  protected toggleAll(on: boolean): void {
    this.selected.set(on ? new Set(this.list.items().map((r) => r.id)) : new Set());
  }

  protected update(r: AdminReview, patch: { status?: string; is_featured?: boolean }): void {
    this.busy.set(true);
    this.api.patch<AdminReview>(`reviews/${r.id}`, patch).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.toast.success(res.message || 'Review updated');
        this.list.load();
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected bulk(status: 'approved' | 'rejected'): void {
    const ids = [...this.selected()];
    if (!ids.length) return;
    this.busy.set(true);
    this.api.post('reviews/bulk', { ids, status }).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.selected.set(new Set());
        this.toast.success(res.message || `${ids.length} reviews updated`);
        this.list.load();
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected async remove(r: AdminReview): Promise<void> {
    const ok = await this.confirm.ask({ title: 'Delete review?', message: `“${r.title}” by ${r.author} will be permanently deleted and the product rating recalculated.`, confirmLabel: 'Delete', danger: true });
    if (!ok) return;
    this.api.delete(`reviews/${r.id}`).subscribe({
      next: (res) => {
        this.toast.success(res.message || 'Review deleted');
        this.list.load();
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }
}
