# Build the Angular app, then serve the static files with nginx.
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY . .
# Address of the Laravel API the browser calls. Empty = use the value in
# src/environments/environment.prod.ts (https://api.themotogears.in/v1).
ARG API_URL=""
RUN if [ -n "$API_URL" ]; then \
      sed -i "s#apiUrl: '[^']*'#apiUrl: '${API_URL}'#" src/environments/environment.prod.ts; \
    fi \
    && grep -n "apiUrl" src/environments/environment.prod.ts \
    && npx ng build --configuration production

FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/automotive-shop-frontend/browser /usr/share/nginx/html
EXPOSE 80
