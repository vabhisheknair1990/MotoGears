import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { envelope } from '../../../testing/fixtures';
import { ApiService, toFormData, toParams } from './api.service';

describe('ApiService', () => {
  let api: ApiService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    api = TestBed.inject(ApiService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('unwraps the { success, message, data } envelope', () => {
    let result: unknown;
    api.get<{ id: number }>('products/abc').subscribe((r) => (result = r));
    http.expectOne(`${environment.apiUrl}/products/abc`).flush(envelope({ id: 3 }));
    expect(result).toEqual({ id: 3 });
  });

  it('maps paginated responses to { items, meta }', () => {
    let page: { items: unknown[]; meta: { total: number; last_page: number } } | undefined;
    api.page('products', { page: 2, search: 'brake', empty: '', none: null }).subscribe((p) => (page = p));
    const req = http.expectOne((r) => r.url === `${environment.apiUrl}/products`);
    expect(req.request.params.get('page')).toBe('2');
    expect(req.request.params.get('search')).toBe('brake');
    expect(req.request.params.has('empty')).toBe(false);
    expect(req.request.params.has('none')).toBe(false);
    req.flush(envelope([{ id: 1 }], 'ok', { current_page: 2, last_page: 5, per_page: 20, total: 90, from: 21, to: 40 }));
    expect(page?.items.length).toBe(1);
    expect(page?.meta.total).toBe(90);
  });

  it('spoofs PUT for multipart uploads (PHP only parses multipart on POST)', () => {
    const form = new FormData();
    api.upload('admin/brands/1', form, 'PUT').subscribe();
    const req = http.expectOne(`${environment.apiUrl}/admin/brands/1`);
    expect(req.request.method).toBe('POST');
    expect((req.request.body as FormData).get('_method')).toBe('PUT');
    req.flush(envelope(null));
  });
});

describe('toParams / toFormData', () => {
  it('serialises arrays, booleans and nested objects', () => {
    const p = toParams({ brand: ['bosch', 'hella'], in_stock: true, attributes: { colour: ['red', 'blue'] } });
    expect(p.get('brand')).toBe('bosch,hella');
    expect(p.get('in_stock')).toBe('1');
    expect(p.get('attributes[colour]')).toBe('red,blue');
  });

  it('builds multipart bodies with booleans as 1/0 and nulls as empty strings', () => {
    const file = new File(['x'], 'logo.png', { type: 'image/png' });
    const fd = toFormData({ name: 'Bosch', is_active: false, logo: file, website: null, skipped: undefined, tags: ['a'] });
    expect(fd.get('name')).toBe('Bosch');
    expect(fd.get('is_active')).toBe('0');
    expect(fd.get('website')).toBe('');
    expect(fd.has('skipped')).toBe(false);
    expect((fd.get('logo') as File).name).toBe('logo.png');
    expect(fd.get('tags')).toBe('["a"]');
  });
});
