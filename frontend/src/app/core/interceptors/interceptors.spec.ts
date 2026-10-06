import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { user } from '../../../testing/fixtures';
import { silent } from '../api/http-context';
import { CART_TOKEN_KEY } from '../services/cart.service';
import { ToastService } from '../services/toast.service';
import { AuthStore } from '../state/auth.store';
import { authInterceptor } from './auth.interceptor';
import { errorInterceptor } from './error.interceptor';

describe('HTTP interceptors', () => {
  let http: HttpClient;
  let ctrl: HttpTestingController;
  let store: AuthStore;
  let toast: ToastService;
  let router: Router;
  const api = `${environment.apiUrl}/products`;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(withInterceptors([authInterceptor, errorInterceptor])), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpClient);
    ctrl = TestBed.inject(HttpTestingController);
    store = TestBed.inject(AuthStore);
    toast = TestBed.inject(ToastService);
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  afterEach(() => ctrl.verify());

  it('attaches the bearer token and guest cart token to API calls', () => {
    store.setSession('1|secret', user());
    localStorage.setItem(CART_TOKEN_KEY, 'cart-123');
    http.get(api).subscribe();
    const req = ctrl.expectOne(api);
    expect(req.request.headers.get('Authorization')).toBe('Bearer 1|secret');
    expect(req.request.headers.get('X-Cart-Token')).toBe('cart-123');
    expect(req.request.headers.get('Accept')).toBe('application/json');
    req.flush({});
  });

  it('never leaks the token to third-party hosts', () => {
    store.setSession('1|secret', user());
    http.get('https://example.org/data.json').subscribe();
    const req = ctrl.expectOne('https://example.org/data.json');
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush({});
  });

  it('401 clears the session and redirects to login', () => {
    store.setSession('1|expired', user());
    http.get(api).subscribe({ error: () => undefined });
    ctrl.expectOne(api).flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    expect(store.isLoggedIn()).toBe(false);
    expect(router.navigate).toHaveBeenCalledWith(['/login'], expect.anything());
  });

  it('403, 429 and 5xx show friendly toasts; silent requests stay quiet', () => {
    const spy = vi.spyOn(toast, 'error');
    http.get(api).subscribe({ error: () => undefined });
    ctrl.expectOne(api).flush({ message: 'Forbidden' }, { status: 403, statusText: 'Forbidden' });
    http.get(api).subscribe({ error: () => undefined });
    ctrl.expectOne(api).flush({}, { status: 429, statusText: 'Too Many' });
    http.get(api).subscribe({ error: () => undefined });
    ctrl.expectOne(api).flush({ trace: 'secret stack' }, { status: 500, statusText: 'Server Error' });
    expect(spy).toHaveBeenCalledTimes(3);
    expect(spy.mock.calls[2][0]).not.toContain('stack');

    spy.mockClear();
    http.get(api, { context: silent() }).subscribe({ error: () => undefined });
    ctrl.expectOne(api).flush({}, { status: 500, statusText: 'Server Error' });
    expect(spy).not.toHaveBeenCalled();
  });
});
