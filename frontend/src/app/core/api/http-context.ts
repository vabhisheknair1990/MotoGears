import { HttpContext, HttpContextToken } from '@angular/common/http';

/** Set on requests whose errors the calling component handles itself (no global toast). */
export const SILENT_ERRORS = new HttpContextToken<boolean>(() => false);
/** Set on requests where a 401 must not redirect to the login page (e.g. the login call itself). */
export const SKIP_AUTH_REDIRECT = new HttpContextToken<boolean>(() => false);

export const silent = () => new HttpContext().set(SILENT_ERRORS, true);
export const silentNoRedirect = () => new HttpContext().set(SILENT_ERRORS, true).set(SKIP_AUTH_REDIRECT, true);
