// Production configuration (swapped in by angular.json fileReplacements).
// The API runs on its own host. The Docker build can override apiUrl with the API_URL
// build argument (see Dockerfile / docker-compose.yml); the storefront's origin must be
// listed in CORS_ALLOWED_ORIGINS in the backend .env.
export const environment = {
  production: true,
  apiUrl: 'https://api.themotogears.in/v1',
  storeName: 'MotoGears',
};
