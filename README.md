# Flight Search Aggregator

Backend service that fans out flight searches to multiple providers, normalizes heterogeneous response schemas into a canonical model, deduplicates overlapping offers, and returns a unified result set with completeness metadata.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Requirements: PHP ^8.3, Composer, SQLite (or any database — SQLite is zero-config).

## Run

```bash
php artisan serve
```

The provider dispatcher uses concurrent HTTP requests to internal fixture endpoints. The built-in PHP development server is single-threaded by default, so use multiple workers to avoid self-deadlock:

```bash
PHP_CLI_SERVER_WORKERS=4 php artisan serve
```

## Test

```bash
php artisan test --parallel --compact
```

Format code:

```bash
vendor/bin/pint --dirty --format agent
```

Static analysis (Larastan level 6):

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

## API

### Search flights

```
GET /api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2
```

Optional query params: `sort` (format `field:direction`, e.g. `price:asc`), `filter[stops]`, `filter[carrier]`, `filter[max_price]`.

### Create booking

```
POST /api/bookings
Content-Type: application/json

{
  "flight_id": "<stable-flight-id>",
  "passengers": [
    { "name": "John Doe", "email": "john@example.com", "date_of_birth": "1990-01-15" }
  ]
}
```

### Get booking

```
GET /api/bookings/{reference}
```

## API Documentation

OpenAPI 3.0 spec: [storage/api-docs/api.yaml](storage/api-docs/api.yaml)

View in Swagger UI:

```
http://localhost:8000/api/docs/openapi.yaml
```

Paste the URL above into [Swagger Editor](https://editor.swagger.io) or append `?url=` to any Swagger UI instance.

## Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for design decisions, trade-offs, and extension points.
