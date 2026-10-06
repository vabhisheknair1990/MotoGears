import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { PageMeta } from '../../core/models/api.models';
import { IconComponent } from './icon.component';

/** Consumes the API pagination meta ({ current_page, last_page, total, from, to }). */
@Component({
  selector: 'app-pagination',
  imports: [IconComponent],
  template: `
    @if (meta(); as m) {
      @if (m.last_page > 1 || showSummary()) {
        <nav class="pager" aria-label="Pagination">
          @if (showSummary()) {
            <span class="summary">Showing {{ m.from ?? 0 }}–{{ m.to ?? 0 }} of {{ m.total }}</span>
          }
          @if (m.last_page > 1) {
            <div class="pages">
              <button class="btn btn-sm" [disabled]="m.current_page <= 1" (click)="go(m.current_page - 1)" aria-label="Previous page">
                <app-icon name="chevron-left" [size]="16" />
              </button>
              @for (p of pages(); track $index) {
                @if (p === null) {
                  <span class="gap">…</span>
                } @else {
                  <button class="btn btn-sm" [class.current]="p === m.current_page" [attr.aria-current]="p === m.current_page ? 'page' : null" (click)="go(p)">{{ p }}</button>
                }
              }
              <button class="btn btn-sm" [disabled]="m.current_page >= m.last_page" (click)="go(m.current_page + 1)" aria-label="Next page">
                <app-icon name="chevron-right" [size]="16" />
              </button>
            </div>
          }
        </nav>
      }
    }
  `,
  styles: `
    .pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 0; }
    .summary { color: var(--muted); font-size: 13px; }
    .pages { display: flex; gap: 6px; flex-wrap: wrap; }
    .pages .btn { min-width: 36px; padding: 0 10px; }
    .current { background: var(--ink); color: #fff; border-color: var(--ink); }
    .current:hover { color: #fff; }
    .gap { align-self: center; color: var(--muted); padding: 0 4px; }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaginationComponent {
  readonly meta = input<PageMeta | null>(null);
  readonly showSummary = input(true);
  readonly pageChange = output<number>();

  protected readonly pages = computed<(number | null)[]>(() => {
    const m = this.meta();
    if (!m) return [];
    const { current_page: cur, last_page: last } = m;
    const set = new Set([1, last, cur - 1, cur, cur + 1].filter((p) => p >= 1 && p <= last));
    const sorted = [...set].sort((a, b) => a - b);
    const out: (number | null)[] = [];
    sorted.forEach((p, i) => {
      if (i > 0 && p - sorted[i - 1] > 1) out.push(null);
      out.push(p);
    });
    return out;
  });

  go(page: number): void {
    this.pageChange.emit(page);
  }
}
