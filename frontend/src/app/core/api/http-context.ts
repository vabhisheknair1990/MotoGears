import { HttpContext, HttpContextToken } from '@angular/common/http';

/** Set on requests whose errors the calling component handles itself (no global toast). */
export const SILENT_ERRORS = new HttpContextToken<boolean>(() => false);
/** Set on requests where a 401 must not redirect to the login page (e.g. the login call itself). */
export const SKIP_AUTH_REDIRECT = new HttpContextToken<boolean>(() => false);

/** Secret of a guest order; the auth interceptor sends it as the X-Order-Token header. */
export const ORDER_TOKEN = new HttpContextToken<string | null>(() => null);

export const silent = () => new HttpContext().set(SILENT_ERRORS, true);
export const silentNoRedirect = () => new HttpContext().set(SILENT_ERRORS, true).set(SKIP_AUTH_REDIRECT, true);

/** Silent request that proves access to a guest order. */
export const withOrderToken = (token: string) => new HttpContext().set(SILENT_ERRORS, true).set(SKIP_AUTH_REDIRECT, true).set(ORDER_TOKEN, token);
