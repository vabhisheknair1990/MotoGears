// Production configuration (swapped in by angular.json fileReplacements).
// A relative URL means "same origin": the bundled nginx (Docker) proxies /api to Laravel.
// For a separate API domain use e.g. 'https://api.motogears.in/api/v1' and add the
// storefront origin to CORS_ALLOWED_ORIGINS in the backend .env.
export const environment = {
  production: true,
  apiUrl: '/api/v1',
  storeName: 'MotoGears',
};
