# Build the Angular app, then serve it with nginx (which also proxies /api to Laravel).
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY . .
RUN npx ng build --configuration production

FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/automotive-shop-frontend/browser /usr/share/nginx/html
EXPOSE 80
