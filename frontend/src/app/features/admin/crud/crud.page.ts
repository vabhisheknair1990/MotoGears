import { ChangeDetectionStrategy, Component, DestroyRef, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, FormControl, FormGroup, ReactiveFormsModule, ValidatorFn, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Observable, catchError, forkJoin, map, of } from 'rxjs';
import { Query } from '../../../core/api/api.service';
import { ApiEnvelope, Page } from '../../../core/models/api.models';
import { ConfirmService } from '../../../core/services/confirm.service';
import { ToastService } from '../../../core/services/toast.service';
import { formatDate, formatInr, formatNumber } from '../../../core/utils/format';
import { applyServerErrors, errorMessage } from '../../../core/utils/http-errors';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { ModalComponent } from '../../../shared/components/modal.component';
import { PaginationComponent } from '../../../shared/components/pagination.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { Option } from '../data/admin.models';
import { ListState } from '../data/list-state';
import { CrudColumn, CrudConfig, CrudCtx, CrudField, CrudFilter, FormValue, Row } from '../shared/crud.types';
import { ImageInputComponent } from '../shared/image-input.component';
import { PageHeaderComponent } from '../shared/page-header.component';
import { PickedProduct, ProductPickerComponent } from '../shared/product-picker.component';
import { SearchInputComponent } from '../shared/search-input.component';
import { TagInputComponent } from '../shared/tag-input.component';
import { CRUD_CONFIGS } from './crud.configs';

/**
 * Config-driven list + create/edit dialog used for the simpler admin modules
 * (brands, categories, vehicles, coupons, CMS content, staff…). Validation
 * mirrors the Laravel Form Requests; server 422s are mapped back to fields.
 */
@Component({
  selector: 'adm-crud-page',
  imports: [ReactiveFormsModule, RouterLink, EmptyStateComponent, IconComponent, ModalComponent, PaginationComponent, StatusBadgeComponent, ImageInputComponent, PageHeaderComponent, ProductPickerComponent, SearchInputComponent, TagInputComponent],
  templateUrl: './crud.page.html',
  styleUrl: './crud.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CrudPage {
  /** Route data key into CRUD_CONFIGS. */
  readonly crud = input.required<string>();

  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly cfg = computed<CrudConfig>(() => {
    const c = CRUD_CONFIGS[this.crud()];
    if (!c) throw new Error(`Unknown CRUD config "${this.crud()}"`);
    return c;
  });

  protected readonly lookups = toSignal(this.api.lookups().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly asyncOpts = signal<Record<string, Option[]>>({});
  protected readonly ctx = computed<CrudCtx>(() => ({ lookups: this.lookups(), async: this.asyncOpts() }));

  protected readonly list = new ListState<Row>((q) => this.fetch(q));

  // Dialog state: undefined = closed, null = creating, Row = editing.
  protected readonly editing = signal<Row | null | undefined>(undefined);
  protected readonly form = signal<FormGroup>(new FormGroup({}));
  protected readonly formValue = signal<FormValue>({});
  protected readonly saving = signal(false);
  protected readonly formError = signal<string | null>(null);
  private files: Record<string, File | null> = {};
  private removed = new Set<string>();
  protected readonly busyRow = signal<number | null>(null);

  protected readonly visibleFields = computed(() => {
    const value = this.formValue();
    const row = this.editing() ?? null;
    return this.cfg().fields.filter((f) => (!f.createOnly || !row) && (!f.visible || f.visible(value, row)));
  });

  constructor() {
    effect(() => {
      const cfg = this.cfg();
      untracked(() => {
        const qp = this.route.snapshot.queryParamMap;
        const fromUrl: Query = {};
        for (const f of cfg.filters ?? []) {
          const v = qp.get(f.key);
          if (v) fromUrl[f.key] = v;
        }
        if (qp.get('search')) fromUrl['search'] = qp.get('search');
        this.list.query.set({ page: 1, per_page: cfg.perPage ?? 20, ...(cfg.initialQuery ?? {}), ...fromUrl });
        this.list.load();
        this.loadAsyncOptions(cfg);
      });
    });
  }

  private fetch(q: Query): Observable<Page<Row>> {
    const cfg = this.cfg();
    if (cfg.paginated === false) {
      return this.api.get<Row[]>(cfg.resource, q).pipe(map((items) => ({ items, meta: { current_page: 1, last_page: 1, per_page: items.length, total: items.length, from: items.length ? 1 : null, to: items.length } })));
    }
    return this.api.page<Row>(cfg.resource, q);
  }

  private loadAsyncOptions(cfg: CrudConfig): void {
    const entries = Object.entries(cfg.asyncOptions ?? {});
    if (!entries.length) return;
    forkJoin(Object.fromEntries(entries.map(([k, fn]) => [k, fn(this.api).pipe(catchError(() => of([] as Option[])))])))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((res) => this.asyncOpts.set(res as Record<string, Option[]>));
  }

  // ---- table helpers -------------------------------------------------------

  protected cell(row: Row, c: CrudColumn): unknown {
    if (c.value) return c.value(row);
    return c.key.split('.').reduce<unknown>((acc, k) => (acc && typeof acc === 'object' ? (acc as Row)[k] : undefined), row);
  }

  protected text(row: Row, c: CrudColumn): string {
    const v = this.cell(row, c);
    switch (c.type) {
      case 'money': return v === null || v === undefined ? '—' : formatInr(Number(v));
      case 'number': return v === null || v === undefined ? '—' : formatNumber(Number(v));
      case 'date': return formatDate(v as string);
      case 'datetime': return formatDate(v as string, true);
      default: return v === null || v === undefined || v === '' ? '—' : String(v);
    }
  }

  protected filterOptions(f: CrudFilter): Option[] {
    return typeof f.options === 'function' ? f.options(this.ctx()) : f.options;
  }

  protected setFilter(key: string, value: string): void {
    this.list.set({ [key]: value || null });
  }

  // ---- dialog --------------------------------------------------------------

  protected openCreate(): void {
    this.openForm(null);
  }

  protected openEdit(row: Row): void {
    this.openForm(row);
  }

  private openForm(row: Row | null): void {
    const cfg = this.cfg();
    const initial: FormValue = row ? (cfg.fromRow ? cfg.fromRow(row) : { ...row }) : {};
    const controls: Record<string, AbstractControl> = {};
    for (const f of cfg.fields) {
      if (f.type === 'image' || f.type === 'readonly') continue;
      let value = row ? initial[f.key] : f.default;
      if (value === undefined) value = defaultFor(f);
      if (f.type === 'datetime') value = toLocalInput(value as string | null);
      if (f.type === 'date' && typeof value === 'string') value = value.slice(0, 10);
      controls[f.key] = new FormControl(value, validatorsFor(f, !row));
    }
    const group = new FormGroup(controls);
    this.files = {};
    this.removed = new Set();
    this.formError.set(null);
    this.form.set(group);
    this.formValue.set(group.getRawValue());
    group.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.formValue.set(group.getRawValue()));
    this.editing.set(row);
  }

  protected closeForm(): void {
    this.editing.set(undefined);
  }

  protected ctrl(key: string): FormControl {
    return this.form().get(key) as FormControl;
  }

  protected options(f: CrudField): Option[] {
    const o = f.options ?? [];
    return typeof o === 'function' ? o(this.ctx(), this.formValue()) : o;
  }

  protected groups(f: CrudField): { group: string; options: Option[] }[] {
    const out: { group: string; options: Option[] }[] = [];
    for (const o of this.options(f)) {
      const g = o.group ?? '';
      let bucket = out.find((b) => b.group === g);
      if (!bucket) out.push((bucket = { group: g, options: [] }));
      bucket.options.push(o);
    }
    return out;
  }

  protected onSelect(f: CrudField): void {
    for (const k of f.resets ?? []) this.form().get(k)?.setValue(null);
  }

  protected isChecked(f: CrudField, value: unknown): boolean {
    return ((this.ctrl(f.key).value as unknown[]) ?? []).includes(value);
  }

  protected toggleMulti(f: CrudField, value: unknown, on: boolean): void {
    const c = this.ctrl(f.key);
    const cur = ((c.value as unknown[]) ?? []).filter((v) => v !== value);
    c.setValue(on ? [...cur, value] : cur);
    c.markAsDirty();
  }

  protected setList(f: CrudField, value: string[] | PickedProduct[]): void {
    const c = this.ctrl(f.key);
    c.setValue(value);
    c.markAsDirty();
  }

  protected setFile(f: CrudField, file: File | null): void {
    this.files[f.key] = file;
    if (file) this.removed.delete(f.key);
  }

  protected markRemoved(f: CrudField): void {
    this.files[f.key] = null;
    this.removed.add(f.key);
  }

  protected show(f: CrudField): boolean {
    const c = this.form().get(f.key);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  protected errorFor(f: CrudField): string {
    const e = this.form().get(f.key)?.errors ?? {};
    if (e['server']) return e['server'] as string;
    if (e['required']) return `${f.label} is required.`;
    if (e['email']) return 'Enter a valid email address.';
    if (e['maxlength']) return `Maximum ${f.maxLength} characters.`;
    if (e['min']) return `Must be at least ${f.min}.`;
    if (e['max']) return `Must be at most ${f.max}.`;
    if (e['pattern']) return f.type === 'url' ? 'Enter a full URL starting with http:// or https://' : (f.patternMessage ?? 'Invalid format.');
    return 'Invalid value.';
  }

  protected render(f: CrudField): string {
    const row = this.editing();
    return row && f.render ? f.render(row) : '';
  }

  protected imageUrl(f: CrudField): string | null {
    const row = this.editing();
    return row && f.imageKey ? ((row[f.imageKey] as string | null) ?? null) : null;
  }

  protected save(): void {
    const group = this.form();
    group.markAllAsTouched();
    if (group.invalid || this.saving()) {
      this.formError.set('Please fix the highlighted fields.');
      return;
    }
    const cfg = this.cfg();
    const row = this.editing() ?? null;
    const raw = group.getRawValue() as FormValue;
    let payload: FormValue = {};
    for (const f of this.visibleFields()) {
      if (f.type === 'readonly') continue;
      if (f.type === 'image') {
        if (this.files[f.key]) payload[f.key] = this.files[f.key];
        else if (this.removed.has(f.key) && f.removeKey) payload[f.removeKey] = true;
        continue;
      }
      let v = raw[f.key];
      if (typeof v === 'string') {
        v = v.trim();
        if (f.uppercase) v = (v as string).toUpperCase();
      }
      if (v === '' || v === undefined) v = null;
      if (f.type === 'number' && v !== null) v = Number(v);
      // datetime-local is naive; the store and its admins share one timezone (APP_TIMEZONE).
      if (f.type === 'datetime' && typeof v === 'string') v = `${v.replace('T', ' ')}:00`;
      if (f.type === 'products') v = ((v as PickedProduct[] | null) ?? []).map((p) => p.id);
      if (f.type === 'password' && v === null) continue;
      payload[f.key] = v;
    }
    if (cfg.toPayload) payload = cfg.toPayload(payload, row);

    const hasFile = Object.values(payload).some((v) => v instanceof File);
    const path = row ? `${cfg.resource}/${row['id']}` : cfg.resource;
    const req$: Observable<ApiEnvelope<Row>> =
      cfg.multipart && hasFile
        ? this.api.upload<Row>(path, payload, row ? 'PUT' : 'POST')
        : row
          ? cfg.updateMethod === 'put' ? this.api.put<Row>(path, payload) : this.api.patch<Row>(path, payload)
          : this.api.post<Row>(path, payload);

    this.saving.set(true);
    this.formError.set(null);
    req$.subscribe({
      next: (res) => {
        this.saving.set(false);
        this.toast.success(res.message || `${cfg.singular} saved`);
        this.editing.set(undefined);
        if (cfg.refreshesLookups) this.api.refreshLookups();
        this.list.load();
      },
      error: (err) => {
        this.saving.set(false);
        const unmatched = applyServerErrors(group, err);
        const msg = errorMessage(err);
        this.formError.set(unmatched[0] ?? (msg === 'Validation failed' ? 'Please fix the highlighted fields.' : msg));
      },
    });
  }

  protected toggle(row: Row): void {
    const cfg = this.cfg();
    const key = cfg.toggle;
    if (!key) return;
    this.busyRow.set(row['id'] as number);
    this.api.patch<Row>(`${cfg.resource}/${row['id']}`, { [key]: !row[key] }).subscribe({
      next: (res) => {
        this.busyRow.set(null);
        const next = res.data && typeof res.data === 'object' && 'id' in res.data ? res.data : { ...row, [key]: !row[key] };
        this.list.patchRow((r) => r['id'] === row['id'], { ...row, ...next });
        this.toast.success(`${cfg.rowTitle(row)} ${!row[key] ? 'enabled' : 'disabled'}`);
        if (cfg.refreshesLookups) this.api.refreshLookups();
      },
      error: () => this.busyRow.set(null),
    });
  }

  protected async remove(row: Row): Promise<void> {
    const cfg = this.cfg();
    const ok = await this.confirm.ask({
      title: `Delete ${cfg.singular.toLowerCase()}?`,
      message: cfg.deleteMessage?.(row) ?? `“${cfg.rowTitle(row)}” will be permanently deleted. This cannot be undone.`,
      confirmLabel: 'Delete',
      danger: true,
    });
    if (!ok) return;
    this.busyRow.set(row['id'] as number);
    this.api.delete(`${cfg.resource}/${row['id']}`).subscribe({
      next: (res) => {
        this.busyRow.set(null);
        this.toast.success(res.message || `${cfg.singular} deleted`);
        if (cfg.refreshesLookups) this.api.refreshLookups();
        this.list.load();
      },
      error: (err) => {
        this.busyRow.set(null);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected exportFile(): void {
    const e = this.cfg().exportFile;
    if (!e) return;
    this.api.download(e.path, e.filename).subscribe({ error: (err) => this.toast.error(errorMessage(err, 'Export failed.')) });
  }

  protected linkQuery(l: { query?: (row: Row) => Record<string, unknown> }, row: Row): Record<string, unknown> | null {
    return l.query ? l.query(row) : null;
  }

  protected readonly skeletonRows = [0, 1, 2, 3, 4, 5];
}

function defaultFor(f: CrudField): unknown {
  switch (f.type) {
    case 'checkbox': return false;
    case 'tags':
    case 'multiselect':
    case 'products': return [];
    case 'select': return null;
    default: return '';
  }
}

function validatorsFor(f: CrudField, creating: boolean): ValidatorFn[] {
  const v: ValidatorFn[] = [];
  if (f.required || (creating && f.requiredOnCreate)) v.push(f.type === 'checkbox' ? Validators.requiredTrue : Validators.required);
  if (f.type === 'email') v.push(Validators.email);
  if (f.type === 'url') v.push(Validators.pattern(/^https?:\/\/\S+$/i));
  if (f.maxLength) v.push(Validators.maxLength(f.maxLength));
  if (f.min !== undefined) v.push(Validators.min(f.min));
  if (f.max !== undefined) v.push(Validators.max(f.max));
  if (f.pattern) v.push(Validators.pattern(f.pattern));
  return v;
}

/** ISO string → value for <input type="datetime-local"> in the browser's zone. */
function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
