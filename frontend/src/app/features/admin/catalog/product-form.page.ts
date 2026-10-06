import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, FormArray, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, concatMap, from, last, of } from 'rxjs';
import { ConfirmService } from '../../../core/services/confirm.service';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { formatInr } from '../../../core/utils/format';
import { applyServerErrors, errorMessage, fieldErrors } from '../../../core/utils/http-errors';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';
import { IconComponent } from '../../../shared/components/icon.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge.component';
import { AdminApiService } from '../data/admin-api.service';
import { AdminProduct, CompatibilityRow } from '../data/admin.models';
import { categoryOptions } from '../crud/crud.configs';
import { PageHeaderComponent } from '../shared/page-header.component';
import { TagInputComponent } from '../shared/tag-input.component';
import { CompatibilityEditorComponent, toCompatibilityInput } from './compatibility-editor.component';
import { ManagedImage, ProductImagesComponent } from './product-images.component';

/** Selling price may not exceed MRP (mirrors the Laravel rule). */
function priceWithinMrp(group: AbstractControl): ValidationErrors | null {
  const mrp = Number(group.get('mrp')?.value);
  const price = Number(group.get('price')?.value);
  return mrp > 0 && price > mrp ? { priceAboveMrp: true } : null;
}

const SECTIONS = [
  { id: 'basics', label: 'Basics' },
  { id: 'pricing', label: 'Pricing' },
  { id: 'images', label: 'Images' },
  { id: 'fitment', label: 'Fitment' },
  { id: 'specs', label: 'Specifications' },
  { id: 'attributes', label: 'Attributes' },
  { id: 'variants', label: 'Variants' },
  { id: 'faqs', label: 'FAQs' },
  { id: 'seo', label: 'SEO' },
];

@Component({
  selector: 'adm-product-form-page',
  imports: [ReactiveFormsModule, RouterLink, EmptyStateComponent, IconComponent, StatusBadgeComponent, PageHeaderComponent, TagInputComponent, CompatibilityEditorComponent, ProductImagesComponent],
  templateUrl: './product-form.page.html',
  styleUrl: './product-form.page.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductFormPage {
  /** Route param; absent on /admin/products/create. */
  readonly id = input<string | undefined>(undefined);

  private readonly api = inject(AdminApiService);
  private readonly fb = inject(FormBuilder);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly router = inject(Router);
  private readonly seo = inject(SeoService);

  protected readonly sections = SECTIONS;
  protected readonly lookups = toSignal(this.api.lookups().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly categories = computed(() => categoryOptions(this.lookups()));
  protected readonly isNew = computed(() => !this.id() || this.id() === 'new');

  protected readonly product = signal<AdminProduct | null>(null);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');
  protected readonly saving = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly serverErrors = signal<string[]>([]);

  // Non-FormControl collections (edited through custom widgets).
  protected readonly tags = signal<string[]>([]);
  protected readonly included = signal<string[]>([]);
  protected readonly attributeIds = signal<Set<number>>(new Set());
  protected readonly compat = signal<CompatibilityRow[]>([]);
  protected readonly images = signal<ManagedImage[]>([]);
  private queuedFiles: File[] = [];
  private extrasDirty = false;

  protected readonly form = this.fb.group(
    {
      name: ['', [Validators.required, Validators.maxLength(190)]],
      slug: ['', [Validators.maxLength(190), Validators.pattern(/^[a-z0-9-_]*$/)]],
      sku: ['', [Validators.required, Validators.maxLength(64), Validators.pattern(/^[A-Za-z0-9\-_]+$/)]],
      part_number: ['', Validators.maxLength(100)],
      category_id: [null as number | null, Validators.required],
      brand_id: [null as number | null, Validators.required],
      vehicle_type: ['car', Validators.required],
      is_universal: [false],
      position: [''],
      short_description: ['', Validators.maxLength(500)],
      description: ['', Validators.maxLength(20000)],
      mrp: [null as number | null, [Validators.required, Validators.min(0), Validators.max(9999999)]],
      price: [null as number | null, [Validators.required, Validators.min(0), Validators.max(9999999)]],
      cost_price: [null as number | null, [Validators.min(0), Validators.max(9999999)]],
      tax_rate: [18],
      initial_stock: [0, [Validators.min(0), Validators.max(1000000)]],
      low_stock_threshold: [5, [Validators.min(0), Validators.max(100000)]],
      allow_backorder: [false],
      material: ['', Validators.maxLength(100)],
      dimensions: ['', Validators.maxLength(100)],
      weight_kg: [null as number | null, [Validators.min(0), Validators.max(9999)]],
      warranty: ['', Validators.maxLength(150)],
      installation_info: ['', Validators.maxLength(5000)],
      video_url: ['', [Validators.maxLength(255), Validators.pattern(/^(https?:\/\/\S+)?$/)]],
      meta_title: ['', Validators.maxLength(190)],
      meta_description: ['', Validators.maxLength(500)],
      is_active: [true],
      is_featured: [false],
      specifications: this.fb.array<FormGroup>([]),
      faqs: this.fb.array<FormGroup>([]),
      variants: this.fb.array<FormGroup>([]),
    },
    { validators: priceWithinMrp },
  );

  protected readonly value = toSignal(this.form.valueChanges, { initialValue: this.form.getRawValue() });
  protected readonly discount = computed(() => {
    const v = this.value();
    const mrp = Number(v.mrp) || 0;
    const price = Number(v.price) || 0;
    return mrp > 0 && price <= mrp ? Math.round(((mrp - price) / mrp) * 100) : 0;
  });
  protected readonly margin = computed(() => {
    const v = this.value();
    const price = Number(v.price) || 0;
    const cost = Number(v.cost_price) || 0;
    return price > 0 && cost > 0 ? Math.round(((price - cost) / price) * 100) : null;
  });
  protected readonly priceInclGst = computed(() => {
    const v = this.value();
    return formatInr((Number(v.price) || 0) * (1 + (Number(v.tax_rate) || 0) / 100));
  });

  get specs(): FormArray<FormGroup> {
    return this.form.controls.specifications;
  }
  get faqs(): FormArray<FormGroup> {
    return this.form.controls.faqs;
  }
  get variantsArr(): FormArray<FormGroup> {
    return this.form.controls.variants;
  }

  constructor() {
    effect(() => {
      const id = this.id();
      untracked(() => {
        if (!id || id === 'create') {
          this.state.set('ready');
          this.seo.set({ title: 'New product' });
          return;
        }
        this.state.set('loading');
        this.api.get<AdminProduct>(`products/${id}`).subscribe({
          next: (p) => this.patch(p),
          error: () => this.state.set('missing'),
        });
      });
    });
  }

  private patch(p: AdminProduct): void {
    this.product.set(p);
    this.seo.set({ title: `Edit · ${p.name}` });
    this.form.patchValue({
      name: p.name, slug: p.slug, sku: p.sku, part_number: p.part_number ?? '', category_id: p.category_id, brand_id: p.brand_id,
      vehicle_type: p.vehicle_type, is_universal: p.is_universal, position: p.position ?? '', short_description: p.short_description ?? '',
      description: p.description ?? '', mrp: p.mrp, price: p.price, cost_price: p.cost_price || null, tax_rate: p.tax_rate,
      material: p.material ?? '', dimensions: p.dimensions ?? '', weight_kg: p.weight_kg, warranty: p.warranty ?? '',
      installation_info: p.installation_info ?? '', video_url: p.video_url ?? '', meta_title: p.meta_title ?? '', meta_description: p.meta_description ?? '',
      is_active: p.is_active, is_featured: p.is_featured,
    });
    this.specs.clear();
    (p.specifications ?? []).forEach((s) => this.specs.push(this.specGroup(s.label, s.value)));
    this.faqs.clear();
    (p.faqs ?? []).forEach((f) => this.faqs.push(this.faqGroup(f.question, f.answer)));
    this.variantsArr.clear();
    (p.variants ?? []).forEach((v) => this.variantsArr.push(this.variantGroup(v)));
    this.tags.set([...(p.tags ?? [])]);
    this.included.set([...(p.whats_included ?? [])]);
    this.attributeIds.set(new Set(p.attribute_value_ids ?? []));
    this.compat.set((p.compatibility ?? []).map((c) => ({ id: c.id, manufacturer: { id: c.manufacturer?.id ?? 0, name: c.manufacturer?.name ?? '' }, model: c.model, variant: c.variant ? { id: c.variant.id, name: c.variant.name } : null, year_from: c.year_from, year_to: c.year_to, notes: c.notes })));
    this.images.set(p.images.map((i) => ({ id: i.id, url: i.url, alt: i.alt, is_primary: i.is_primary })));
    this.form.markAsPristine();
    this.extrasDirty = false;
    this.state.set('ready');
  }

  // ---- form arrays ----
  protected specGroup(label = '', value = ''): FormGroup {
    return this.fb.nonNullable.group({ label: [label, [Validators.required, Validators.maxLength(100)]], value: [value, [Validators.required, Validators.maxLength(255)]] });
  }
  protected faqGroup(question = '', answer = ''): FormGroup {
    return this.fb.nonNullable.group({ question: [question, [Validators.required, Validators.maxLength(255)]], answer: [answer, [Validators.required, Validators.maxLength(2000)]] });
  }
  protected variantGroup(v?: { id: number; sku: string; name: string; price_adjustment: number; is_active: boolean }): FormGroup {
    return this.fb.group({
      id: [v?.id ?? null],
      name: [v?.name ?? '', [Validators.required, Validators.maxLength(190)]],
      sku: [v?.sku ?? '', [Validators.required, Validators.maxLength(64)]],
      price_adjustment: [v?.price_adjustment ?? 0],
      is_active: [v?.is_active ?? true],
    });
  }
  protected addSpec(): void {
    this.specs.push(this.specGroup());
  }
  protected addFaq(): void {
    this.faqs.push(this.faqGroup());
  }
  protected addVariant(): void {
    this.variantsArr.push(this.variantGroup());
  }
  protected removeAt(arr: FormArray, i: number): void {
    arr.removeAt(i);
    arr.markAsDirty();
  }

  // ---- custom widgets ----
  protected setTags(v: string[]): void {
    this.tags.set(v);
    this.extrasDirty = true;
  }
  protected setIncluded(v: string[]): void {
    this.included.set(v);
    this.extrasDirty = true;
  }
  protected setCompat(rows: CompatibilityRow[]): void {
    this.compat.set(rows);
    this.extrasDirty = true;
  }
  protected toggleAttr(id: number): void {
    this.attributeIds.update((s) => {
      const n = new Set(s);
      if (n.has(id)) n.delete(id);
      else n.add(id);
      return n;
    });
    this.extrasDirty = true;
  }
  protected onQueued(files: File[]): void {
    this.queuedFiles = files;
  }

  protected invalid(name: string): boolean {
    const c = this.form.get(name);
    return !!c && c.invalid && (c.touched || c.dirty);
  }
  protected err(name: string, fallback: string): string {
    return (this.form.get(name)?.errors?.['server'] as string | undefined) ?? fallback;
  }

  /** Used by the unsaved-changes guard. */
  hasUnsavedChanges(): boolean {
    return !this.saving() && (this.form.dirty || this.extrasDirty || this.queuedFiles.length > 0);
  }

  protected save(): void {
    this.form.markAllAsTouched();
    this.serverErrors.set([]);
    if (this.form.invalid) {
      this.formError.set(this.form.errors?.['priceAboveMrp'] ? 'Selling price cannot be higher than MRP.' : 'Please fix the highlighted fields.');
      queueMicrotask(() => document.querySelector('.ng-invalid:not(form)')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
      return;
    }
    if (this.saving()) return;
    this.formError.set(null);
    const v = this.form.getRawValue();
    const payload: Record<string, unknown> = {
      ...v,
      sku: (v.sku ?? '').trim().toUpperCase(),
      slug: v.slug?.trim() || null,
      part_number: v.part_number || null,
      position: v.position || null,
      cost_price: v.cost_price ?? null,
      weight_kg: v.weight_kg ?? null,
      video_url: v.video_url || null,
      tags: this.tags(),
      whats_included: this.included(),
      attribute_value_ids: [...this.attributeIds()],
      compatibilities: toCompatibilityInput(this.compat()),
      specifications: v.specifications,
      faqs: v.faqs,
      variants: (v.variants as { id: number | null; name: string; sku: string; price_adjustment: number; is_active: boolean }[]).map((x) => ({ ...x, sku: x.sku.toUpperCase(), price_adjustment: Number(x.price_adjustment) || 0 })),
    };
    if (!this.isNew()) {
      delete payload['initial_stock'];
      delete payload['low_stock_threshold'];
      delete payload['allow_backorder'];
    }

    this.saving.set(true);
    const existing = this.product();
    const req$ = existing ? this.api.patch<AdminProduct>(`products/${existing.id}`, payload) : this.api.post<AdminProduct>('products', payload);
    req$.subscribe({
      next: (res) => {
        const p = res.data;
        if (!existing && this.queuedFiles.length) {
          this.uploadQueued(p.id, res.message);
          return;
        }
        this.saving.set(false);
        this.toast.success(res.message || 'Product saved');
        if (existing) this.patch(p);
        else void this.router.navigate(['/admin/products', p.id, 'edit'], { replaceUrl: true });
      },
      error: (err) => {
        this.saving.set(false);
        const unmatched = applyServerErrors(this.form, err);
        const nested = Object.entries(fieldErrors(err)).filter(([k]) => k.includes('.')).map(([k, m]) => `${k}: ${m[0]}`);
        this.serverErrors.set([...unmatched, ...nested].slice(0, 6));
        const msg = errorMessage(err);
        this.formError.set(msg === 'Validation failed' ? 'Please fix the highlighted fields.' : msg);
      },
    });
  }

  private uploadQueued(productId: number, message: string): void {
    const files = this.queuedFiles;
    // Upload in batches of 10 (API limit per request).
    const batches: File[][] = [];
    for (let i = 0; i < files.length; i += 10) batches.push(files.slice(i, i + 10));
    from(batches)
      .pipe(
        concatMap((batch) => {
          const fd = new FormData();
          batch.forEach((f) => fd.append('images[]', f));
          return this.api.uploadForm(`products/${productId}/images`, fd);
        }),
        last(),
      )
      .subscribe({
        next: () => this.finishCreate(productId, message || 'Product created'),
        error: (err) => {
          this.toast.error(errorMessage(err, 'Product created, but some images failed to upload.'));
          this.finishCreate(productId, 'Product created');
        },
      });
  }

  private finishCreate(id: number, message: string): void {
    this.queuedFiles = [];
    this.saving.set(false);
    this.form.markAsPristine();
    this.extrasDirty = false;
    this.toast.success(message);
    void this.router.navigate(['/admin/products', id, 'edit'], { replaceUrl: true });
  }

  protected async remove(): Promise<void> {
    const p = this.product();
    if (!p) return;
    const ok = await this.confirm.ask({ title: 'Delete product?', message: `“${p.name}” will be hidden from the store. You can restore it later from the product list.`, confirmLabel: 'Delete', danger: true });
    if (!ok) return;
    this.api.delete(`products/${p.id}`).subscribe({
      next: () => {
        this.form.markAsPristine();
        this.extrasDirty = false;
        this.toast.success('Product deleted');
        void this.router.navigate(['/admin/products']);
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }

  protected scrollTo(id: string): void {
    document.getElementById('sec-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
