import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subscription, catchError, of, switchMap, timer } from 'rxjs';
import { ConfirmService } from '../../../core/services/confirm.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { AppDatePipe, TimeAgoPipe } from '../../../shared/pipes/inr.pipe';
import { AdminApiService } from '../data/admin-api.service';
import { ImportColumn, ImportIssue, ImportMode, ImportStatus, ProductImportDetail, ProductImportJob } from '../data/admin.models';
import { ACTIVE_STATUSES, ProductImportService, formatBytes } from '../data/product-import.service';
import { categoryOptions } from '../crud/crud.configs';
import { PageHeaderComponent } from '../shared/page-header.component';
import { PageMeta } from '../../../core/models/api.models';

const MAX_MB = 10;
const ACCEPT = ['xlsx', 'xls', 'csv'];

interface ModeOption { value: ImportMode; label: string; hint: string }

@Component({
  selector: 'adm-product-import-page',
  imports: [RouterLink, IconComponent, PaginationComponent, PageHeaderComponent, AppDatePipe, TimeAgoPipe],
  templateUrl: './product-import.page.html',
  styleUrl: './product-import.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductImportPage {
  private readonly imports = inject(ProductImportService);
  private readonly admin = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly modes: ModeOption[] = [
    { value: 'upsert', label: 'Add new and update existing', hint: 'Products are matched by SKU. New SKUs are added, existing ones are updated.' },
    { value: 'create', label: 'Add new products only', hint: 'Rows whose SKU already exists are skipped — nothing is changed.' },
    { value: 'update', label: 'Update existing products only', hint: 'Rows with a new SKU are skipped — handy for price or stock updates.' },
  ];
  protected readonly steps = [
    { key: 'upload', label: 'Upload' },
    { key: 'check', label: 'Check' },
    { key: 'review', label: 'Review' },
    { key: 'import', label: 'Import' },
    { key: 'done', label: 'Done' },
  ];
  protected readonly maxMb = MAX_MB;

  // Template download
  protected readonly lookups = toSignal(this.admin.lookups().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly categories = computed(() => categoryOptions(this.lookups()));
  protected readonly exportCategory = signal<number | null>(null);
  protected readonly exportBrand = signal<number | null>(null);
  protected readonly downloading = signal<'blank' | 'products' | null>(null);

  // Upload form
  protected readonly file = signal<File | null>(null);
  protected readonly fileError = signal<string | null>(null);
  protected readonly dragging = signal(false);
  protected readonly mode = signal<ImportMode>('upsert');
  protected readonly autoStart = signal(false);
  protected readonly uploading = signal(false);

  // Selected import
  protected readonly job = signal<ProductImportDetail | null>(null);
  protected readonly issueLevel = signal<'error' | 'warning'>('error');
  protected readonly busy = signal(false);
  private poll?: Subscription;

  // History & guide
  protected readonly history = signal<ProductImportJob[]>([]);
  protected readonly historyMeta = signal<PageMeta | null>(null);
  protected readonly historyLoading = signal(true);
  protected readonly columns = signal<ImportColumn[]>([]);
  protected readonly columnSheets = computed(() => {
    const groups = new Map<string, ImportColumn[]>();
    for (const c of this.columns()) groups.set(c.sheet, [...(groups.get(c.sheet) ?? []), c]);
    return [...groups.entries()].map(([sheet, cols]) => ({ sheet, cols }));
  });

  protected readonly isActive = computed(() => !!this.job() && (ACTIVE_STATUSES as readonly string[]).includes(this.job()!.status));
  protected readonly issues = computed<ImportIssue[]>(() => this.job()?.issues ?? []);
  protected readonly currentStep = computed(() => stepFor(this.job()?.status ?? null));
  protected readonly toImport = computed(() => {
    const s = this.job()?.summary;
    return s ? s.will_create + s.will_update : 0;
  });

  constructor() {
    inject(SeoService).set({ title: 'Import products' });
    this.imports.columns().pipe(catchError(() => of([] as ImportColumn[])), takeUntilDestroyed()).subscribe((c) => this.columns.set(c));
    this.destroyRef.onDestroy(() => this.poll?.unsubscribe());

    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((q) => {
      const id = Number(q.get('id'));
      if (id && id !== this.job()?.id) this.select(id);
    });
    this.loadHistory(1, !this.route.snapshot.queryParamMap.get('id'));
  }

  // ── Template ──────────────────────────────────────────────────
  protected download(kind: 'blank' | 'products'): void {
    this.downloading.set(kind);
    this.imports.downloadTemplate(kind === 'products', { category: this.exportCategory(), brand: this.exportBrand() }).subscribe({
      next: () => {
        this.downloading.set(null);
        this.toast.success(kind === 'blank' ? 'Template downloaded' : 'Your products were exported — edit and upload the file');
      },
      error: (err) => {
        this.downloading.set(null);
        this.toast.error(errorMessage(err, 'Could not download the file'));
      },
    });
  }

  protected setFilter(kind: 'category' | 'brand', value: string): void {
    (kind === 'category' ? this.exportCategory : this.exportBrand).set(value ? Number(value) : null);
  }

  // ── File picking ──────────────────────────────────────────────
  protected onPick(input: HTMLInputElement): void {
    this.pick(input.files?.[0] ?? null);
    input.value = '';
  }

  protected onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
    this.pick(event.dataTransfer?.files?.[0] ?? null);
  }

  protected onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(true);
  }

  protected pick(file: File | null): void {
    this.fileError.set(null);
    if (!file) return;
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!ACCEPT.includes(ext)) {
      this.file.set(null);
      this.fileError.set('Please choose an Excel file (.xlsx or .xls) or a .csv file.');
      return;
    }
    if (file.size > MAX_MB * 1048576) {
      this.file.set(null);
      this.fileError.set(`This file is ${formatBytes(file.size)} — the limit is ${MAX_MB} MB. Split it into smaller files.`);
      return;
    }
    this.file.set(file);
  }

  protected clearFile(): void {
    this.file.set(null);
    this.fileError.set(null);
  }

  protected size(bytes: number): string {
    return formatBytes(bytes);
  }

  // ── Upload / start / cancel ───────────────────────────────────
  protected upload(): void {
    const file = this.file();
    if (!file || this.uploading()) return;
    this.uploading.set(true);
    this.imports.upload(file, this.mode(), this.autoStart()).subscribe({
      next: (res) => {
        this.uploading.set(false);
        this.file.set(null);
        this.toast.success(res.message || 'File uploaded');
        this.open(res.data.id);
        this.loadHistory(1);
      },
      error: (err) => {
        this.uploading.set(false);
        this.fileError.set(errorMessage(err, 'Upload failed'));
      },
    });
  }

  protected async start(): Promise<void> {
    const job = this.job();
    if (!job?.summary) return;
    const s = job.summary;
    const parts = [s.will_create && `add ${s.will_create} new`, s.will_update && `update ${s.will_update} existing`].filter(Boolean).join(' and ');
    const ok = await this.confirm.ask({
      title: 'Start the import?',
      message: `This will ${parts} product(s).` + (s.invalid ? ` ${s.invalid} product(s) with problems will be skipped.` : '') + (s.images ? ` ${s.images} image(s) will be downloaded.` : '') + ' It runs in the background — you can leave this page.',
      confirmLabel: 'Start import',
    });
    if (!ok) return;
    this.busy.set(true);
    this.imports.start(job.id).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.toast.success(res.message || 'Import started');
        this.refresh(job.id);
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
        this.refresh(job.id);
      },
    });
  }

  protected async cancel(): Promise<void> {
    const job = this.job();
    if (!job) return;
    const importing = job.status === 'importing';
    const ok = await this.confirm.ask(
      job.status === 'validated'
        ? { title: 'Discard this file?', message: 'Nothing has been imported yet. The file will be marked as cancelled.', confirmLabel: 'Discard', danger: true }
        : { title: importing ? 'Stop the import?' : 'Cancel?', message: importing ? 'The import stops after the current product. Products already saved stay saved.' : 'The file will not be processed.', confirmLabel: importing ? 'Stop import' : 'Cancel it', danger: true },
    );
    if (!ok) return;
    this.busy.set(true);
    this.imports.cancel(job.id).subscribe({
      next: (res) => {
        this.busy.set(false);
        this.toast.info(res.message || 'Cancelled');
        this.refresh(job.id);
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected async remove(row: ProductImportJob): Promise<void> {
    const ok = await this.confirm.ask({ title: 'Remove from history?', message: `“${row.original_name}” and its uploaded file will be deleted. Imported products are not affected.`, confirmLabel: 'Remove', danger: true });
    if (!ok) return;
    this.imports.remove(row.id).subscribe({
      next: () => {
        if (this.job()?.id === row.id) this.close();
        this.loadHistory(this.historyMeta()?.current_page ?? 1);
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }

  protected report(job: ProductImportJob): void {
    this.imports.downloadReport(job).subscribe({ error: (err) => this.toast.error(errorMessage(err)) });
  }

  protected original(job: ProductImportJob): void {
    this.imports.downloadFile(job).subscribe({ error: (err) => this.toast.error(errorMessage(err, 'The file is no longer available')) });
  }

  // ── Selection & polling ───────────────────────────────────────
  protected open(id: number): void {
    this.router.navigate([], { relativeTo: this.route, queryParams: { id }, replaceUrl: true });
    if (this.job()?.id !== id) this.select(id);
  }

  protected close(): void {
    this.poll?.unsubscribe();
    this.job.set(null);
    this.router.navigate([], { relativeTo: this.route, queryParams: {}, replaceUrl: true });
  }

  protected setLevel(level: 'error' | 'warning'): void {
    this.issueLevel.set(level);
    const job = this.job();
    if (job) this.refresh(job.id);
  }

  private select(id: number): void {
    this.job.set(null);
    this.issueLevel.set('error');
    this.refresh(id);
  }

  /** Loads the import, then keeps polling every 1.5 s while it is still running. */
  private refresh(id: number): void {
    this.poll?.unsubscribe();
    let first = true;
    let wasActive = false;
    this.poll = timer(0, 1500)
      .pipe(switchMap(() => this.imports.get(id, this.issueLevel()).pipe(catchError((err) => {
        this.toast.error(errorMessage(err, 'Could not load the import'));
        return of(null);
      }))))
      .subscribe((job) => {
        if (!job) {
          this.poll?.unsubscribe();
          return;
        }
        this.job.set(job);
        const active = (ACTIVE_STATUSES as readonly string[]).includes(job.status);
        const justFinished = wasActive && !active;
        wasActive ||= active;
        if (justFinished) {
          this.loadHistory(this.historyMeta()?.current_page ?? 1);
          this.announce(job);
        }
        // Nothing failed but there are warnings → show those instead of an empty error list.
        if ((first || justFinished) && !active && job.error_count === 0 && job.warning_count > 0 && this.issueLevel() === 'error') {
          this.issueLevel.set('warning');
          this.refresh(id);
          return;
        }
        first = false;
        if (!active) this.poll?.unsubscribe();
      });
  }

  private announce(job: ProductImportJob): void {
    switch (job.status) {
      case 'validated': this.toast.success(job.can_start ? 'Check finished — review the result and start the import' : 'Check finished — please fix the problems in your file'); break;
      case 'completed': this.toast.success(`Import finished: ${job.created_count} added, ${job.updated_count} updated`); break;
      case 'completed_with_errors': this.toast.info(`Import finished with ${job.failed_count} problem product(s)`); break;
      case 'failed': this.toast.error(job.message || 'The import failed'); break;
    }
  }

  protected loadHistory(page: number, selectActive = false): void {
    this.historyLoading.set(true);
    this.imports.history(page).subscribe({
      next: (res) => {
        this.history.set(res.items);
        this.historyMeta.set(res.meta);
        this.historyLoading.set(false);
        if (selectActive && !this.job()) {
          const running = res.items.find((j) => j.is_active || j.status === 'validated');
          if (running) this.open(running.id);
        }
      },
      error: () => this.historyLoading.set(false),
    });
  }

  // ── View helpers ──────────────────────────────────────────────
  protected tone(status: ImportStatus): string {
    return ({ completed: 'success', validated: 'info', completed_with_errors: 'warning', failed: 'danger', cancelled: 'plain', importing: 'info', validating: 'info', pending: 'warning', queued: 'warning' } as Record<string, string>)[status] ?? 'plain';
  }

  protected stepState(index: number): 'done' | 'current' | 'todo' | 'failed' {
    const job = this.job();
    const current = this.currentStep();
    if (job && (job.status === 'failed' || job.status === 'cancelled') && index === current) return 'failed';
    if (index < current || (index === current && job?.status !== undefined && ['completed', 'completed_with_errors'].includes(job.status))) return 'done';
    return index === current ? 'current' : 'todo';
  }

  protected result(j: ProductImportJob): string {
    if (j.status === 'validated' && j.summary) {
      return [`${j.summary.will_create} new`, `${j.summary.will_update} to update`, j.summary.invalid ? `${j.summary.invalid} with problems` : ''].filter(Boolean).join(' · ');
    }
    if (j.phase === 'check' || j.status === 'failed' && !j.started_at) return j.message ?? '—';
    return [`${j.created_count} added`, `${j.updated_count} updated`, j.skipped_count ? `${j.skipped_count} skipped` : '', j.failed_count ? `${j.failed_count} failed` : ''].filter(Boolean).join(' · ');
  }

  protected issueLocation(i: ImportIssue): string {
    return [i.sheet, i.row ? `row ${i.row}` : ''].filter(Boolean).join(' · ');
  }
}

/** Index into `steps` for a status: 0 upload, 1 check, 2 review, 3 import, 4 done. */
export function stepFor(status: ImportStatus | null): number {
  switch (status) {
    case null: return 0;
    case 'pending':
    case 'validating': return 1;
    case 'validated': return 2;
    case 'queued':
    case 'importing': return 3;
    default: return 4;
  }
}
