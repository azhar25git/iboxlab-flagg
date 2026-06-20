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

The provider dispatcher makes concurrent HTTP calls back to the same host to fetch provider fixtures. The built-in PHP development server is single-threaded by default, so use multiple workers:

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8000 -t public
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

## Future Roadmap

Prioritized next steps if this moves beyond the exercise:

### P0 — Provider health and observability

**What:** Add per-request timing, structured logs, retries with backoff, and circuit-breaker logic for each provider. Replace the averaged duration metric with true per-provider latency.

**Why:** Aggregation is only trustworthy when consumers can see exactly which providers responded, how long they took, and whether retries occurred. This is the highest-risk production gap today.

### P1 — Persistent flight-offer storage for bookings

**What:** Move the flight-offer cache from the array cache to Redis or a database table keyed by stable flight ID.

**Why:** Booking currently only works if the offer is still in the same cache store and within the TTL. A booking reference must be resolvable long after the original search, possibly from a different app instance.

### P1 — Real provider integrations

**What:** Swap the internal fixture endpoints for configurable external base URLs, per-provider secrets read from environment variables, request signing, and provider-specific timeout/retry config. Keep `fixtures()` for tests.

**Why:** The current implementation validates the architecture with mocks; production value depends on calling real provider APIs safely.

### P2 — Input validation hardening

**What:** Enforce IATA format on `filter[carrier]`, add a regex for `flight_id` in bookings, and tighten passenger validation (e.g. date-of-birth in the past, name length).

**Why:** Fail fast at the boundary with clear 422 responses instead of letting bad data reach the services or providers.

### P2 — Rate limiting and abuse prevention

**What:** Add request throttling on `/api/flights/search` and `/api/bookings`, plus per-provider call quotas.

**Why:** Both endpoints trigger external calls or side effects; unguarded, they become easy DoS vectors and can burn provider rate limits.

### P2 — Async provider refresh and result caching

**What:** Cache normalized provider results for a short TTL and refresh them in the background; keep a stale-while-revalidate fallback.

**Why:** Reduces average search latency and provider load without returning completely stale data.

### P3 — Multi-currency price normalization

**What:** Integrate an exchange-rate service and normalize all prices to the requested currency.

**Why:** The spec currently assumes USD, but a real aggregator must compare prices across currencies.

### P3 — Pagination and response caching

**What:** Currently the search endpoint returns all unique flights in a single response with no limit, offset, or page controls. Add cursor pagination and cache final result sets.

**Why:** Keeps response sizes bounded (e.g. 10 per page) for busy routes and further reduces provider calls. A route with many providers can produce payloads that are too large or slow to serialize.
