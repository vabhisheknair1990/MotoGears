# MotoGears API (Laravel)

REST API for the MotoGears automobile parts store. See the [root README](../README.md) for setup,
demo credentials and architecture.

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve        # http://localhost:8000/api/v1 — docs at /api/docs
php artisan test
```
