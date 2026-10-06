import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../../environments/environment';
import { stepFor } from '../catalog/product-import.page';
import { ProductImportService, formatBytes } from './product-import.service';

describe('ProductImportService', () => {
  let service: ProductImportService;
  let http: HttpTestingController;
  const url = (p: string) => `${environment.apiUrl}/admin/${p}`;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ProductImportService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('uploads the file as multipart with the chosen mode and auto-start flag', () => {
    const file = new File(['x'], 'products.xlsx');
    service.upload(file, 'update', true).subscribe();
    const req = http.expectOne(url('product-imports'));
    expect(req.request.method).toBe('POST');
    const body = req.request.body as FormData;
    expect((body.get('file') as File).name).toBe('products.xlsx');
    expect(body.get('mode')).toBe('update');
    expect(body.get('auto_start')).toBe('1');
    req.flush({ success: true, message: 'ok', data: { id: 7 } });
  });

  it('asks only for the requested issue level when polling an import', () => {
    service.get(7, 'warning').subscribe();
    const req = http.expectOne((r) => r.url === url('product-imports/7'));
    expect(req.request.params.get('level')).toBe('warning');
    req.flush({ success: true, message: 'ok', data: { id: 7 } });
  });

  it('requests a pre-filled template with the export filters', () => {
    service.downloadTemplate(true, { category: 3, brand: null }).subscribe();
    const req = http.expectOne((r) => r.url === url('product-imports/template'));
    expect(req.request.params.get('with_products')).toBe('1');
    expect(req.request.params.get('category')).toBe('3');
    expect(req.request.params.has('brand')).toBe(false);
    expect(req.request.responseType).toBe('blob');
    req.flush(new Blob(['x']));
  });

  it('starts and cancels imports with POST', () => {
    service.start(5).subscribe();
    expect(http.expectOne(url('product-imports/5/start')).request.method).toBe('POST');
    service.cancel(5).subscribe();
    expect(http.expectOne(url('product-imports/5/cancel')).request.method).toBe('POST');
  });
});

describe('product import helpers', () => {
  it('maps every status to a step of the stepper', () => {
    expect(stepFor(null)).toBe(0);
    expect(stepFor('pending')).toBe(1);
    expect(stepFor('validating')).toBe(1);
    expect(stepFor('validated')).toBe(2);
    expect(stepFor('queued')).toBe(3);
    expect(stepFor('importing')).toBe(3);
    expect(stepFor('completed')).toBe(4);
    expect(stepFor('completed_with_errors')).toBe(4);
    expect(stepFor('failed')).toBe(4);
  });

  it('formats file sizes for people', () => {
    expect(formatBytes(512)).toBe('512 B');
    expect(formatBytes(20480)).toBe('20 KB');
    expect(formatBytes(3.5 * 1048576)).toBe('3.5 MB');
  });
});
