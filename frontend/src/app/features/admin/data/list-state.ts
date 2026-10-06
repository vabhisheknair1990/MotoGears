import { DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Observable, Subject, catchError, of, switchMap, tap } from 'rxjs';
import { Query } from '../../../core/api/api.service';
import { Page, PageMeta } from '../../../core/models/api.models';
import { errorMessage } from '../../../core/utils/http-errors';

/**
 * Signal-backed state for a paginated admin list: query, rows, meta, loading
 * and error. Must be created in an injection context (field initialiser).
 * Requests are switch-mapped so a fast typist never sees stale results.
 */
export class ListState<T, M = Record<string, unknown>> {
  readonly query = signal<Query>({});
  readonly items = signal<T[]>([]);
  readonly meta = signal<(PageMeta & M) | null>(null);
  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  private readonly trigger = new Subject<Query>();

  constructor(loader: (q: Query) => Observable<Page<T, M>>, initial: Query = {}) {
    this.query.set({ page: 1, ...initial });
    this.trigger
      .pipe(
        tap(() => {
          this.loading.set(true);
          this.error.set(null);
        }),
        switchMap((q) =>
          loader(q).pipe(
            catchError((err: unknown) => {
              this.error.set(errorMessage(err, 'Could not load data.'));
              return of(null);
            }),
          ),
        ),
        takeUntilDestroyed(inject(DestroyRef)),
      )
      .subscribe((res) => {
        this.loading.set(false);
        if (res) {
          this.items.set(res.items);
          this.meta.set(res.meta);
        }
      });
  }

  load(): void {
    this.trigger.next(this.query());
  }

  /** Merge filters and go back to page 1. */
  set(patch: Query): void {
    this.query.update((q) => ({ ...q, ...patch, page: 1 }));
    this.load();
  }

  goTo(page: number): void {
    this.query.update((q) => ({ ...q, page }));
    this.load();
  }

  /** Replace a row after an inline update (e.g. toggle) without refetching. */
  patchRow(match: (row: T) => boolean, next: T): void {
    this.items.update((rows) => rows.map((r) => (match(r) ? next : r)));
  }

  value<K extends string>(key: K): string {
    const v = this.query()[key];
    return v === null || v === undefined ? '' : String(v);
  }
}
