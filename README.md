# Mini ERP

Inventory & Purchase Order management built with Laravel, MySQL, Blade and Sanctum.

## Setup

Requires PHP 8.3+, Composer and MySQL running locally (`root` with no password by default, change `DB_*` in `.env` if needed).

```bash
git clone https://github.com/SandyIntrithm/mini-erp.git
cd mini-erp
composer setup
php artisan serve
```

`composer setup` runs `composer install`, creates `.env`, runs `php artisan key:generate` and `php artisan migrate --seed` (the `mini_erp` database is created automatically).

Open http://127.0.0.1:8000

## Login

Web and API: `admin@example.com` / `password`

## Tests

```bash
php artisan test
```

## API

Postman collection: `docs/MiniERP.postman_collection.json` (run **Login** first, the token is saved automatically).

```bash
curl -X POST http://127.0.0.1:8000/api/login -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"password"}'
curl http://127.0.0.1:8000/api/inventory -H "Authorization: Bearer <token>"
```

## Indexing notes

- `purchase_orders (status, supplier_id, total_amount)` covers the total expenditure and supplier spend queries.
- `products (is_active, stock_quantity)` covers active product and low stock counts.
- Order lists eager load suppliers and item counts to avoid N+1 queries.
