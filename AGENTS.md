# iBox Lab Flight Search Aggregator — AI Assistant Guide

## Mental Model

This is a **flight search aggregation backend**. When a user searches, the service fans out to multiple provider adapters, normalizes their heterogeneous schemas into a single canonical flight model, deduplicates overlapping offers, and returns a unified, sortable, filterable result set.

The first question is always *"which provider is the source of truth for this offer?"* — not *"how do I store it?"*. The second question is *"what does the consumer need to know about result completeness?"* Aggregation is fundamentally about trust and transparency.

This is a time-boxed take-home exercise (4–8 hours). Prefer working fundamentals over breadth. If a feature threatens the deadline, document the trade-off and move on.

## Tech Stack

PHP ^8.3 | Laravel 13 | Pest 4 | Vite 7 | SQLite (test)

No frontend, authentication, payments, or admin panel is required. Build a clean HTTP API and the supporting architecture.

## Core Rules

1. **`php artisan make:` before anything else** — Let Laravel scaffold, then modify. Don't hand-write files that Artisan can generate.
2. **`vendor/bin/pint --dirty --format agent` after every PHP change** — Formatting is not optional; `--format agent` structures the output for tooling.
3. **TDD with Pest** — `php artisan test --parallel --compact` for quick feedback. Green before you ship. Red is fine while you work — it means the test exists.
4. **Provider-first, then API** — Define the adapter contract and mock providers before wiring the search endpoint. Data shape drives the contract.
5. **KISS over clever** — Fancy abstractions are technical debt waiting to happen. A conditional is cheaper than a strategy pattern you don't need yet.

## Key Patterns

- **Provider Adapter Contract**: Every provider implements `ProviderContract` with `normalize(array $raw): FlightOffer`. Adapters own schema translation; the aggregator never knows provider-specific keys.
- **Canonical Flight Model**: A single internal `FlightOffer` value object / DTO with normalized fields: carrier, origin, destination, departure, arrival, stops, price (normalized currency), flight number, provider, and a stable identity.
- **Stable Identity**: Generate a deterministic flight identifier from normalized immutable attributes (e.g., carrier + flight number + origin + destination + departure UTC). This lets downstream booking refer to a consistent key even when the same physical flight appears from multiple providers.
- **Deduplication**: Two offers represent the same flight when their stable identifiers match. Keep the best-priced offer or merge provider attributions — document the chosen strategy.
- **Result Completeness**: The search response must expose per-provider status (`success`, `timeout`, `error`, `partial`) so consumers understand what they are looking at.
- **Booking by Stable ID**: Booking accepts the canonical flight identifier and passenger details; the service resolves the identifier back to the stored or re-fetched offer.

## Navigation / Domain Map

| Domain | Concepts |
|--------|----------|
| Search | FlightOffer, SearchService, ProviderContract |
| Providers | ProviderA, ProviderB, ProviderC |
| Booking | Booking, BookingResource |

## Domain Models

| Model / Class | Responsibility & Notes |
|---------------|------------------------|
| `FlightOffer` | Readonly DTO: `id`, `carrier`, `origin`, `destination`, `departure`, `arrival`, `stops`, `price`, `currency`, `flightNumber`, `provider`, `providerRawId`. |
| `Booking` | Eloquent model: `reference`, `flight_id`, `flight_snapshot` (JSON), `passengers` (JSON), `status`. |
| `BookingResource` | JsonResource: formats booking response, computes `total_price` from passengers × snapshot price. |
| `ProviderContract` | Interface: `name()`, `endpoint()`, `responseKey()`, `fixtures()`, `normalize(array $raw): FlightOffer`. |
| `ProviderA` / `ProviderB` / `ProviderC` | Concrete adapters — fixtures + normalize. One class per provider schema. |
| `SearchService` | Orchestrates provider dispatch, normalization, deduplication, filtering, sorting, and caching. |

## API Endpoints

### `GET /api/flights/search`

Query params: `from`, `to`, `date`, `passengers` (optional: `sort`, `filter[max_stops]`, `filter[carriers]` (comma-separated), `filter[max_price]`).

Validation rules:
- `from`, `to`: required, 3-letter IATA airport codes, uppercased.
- `date`: required, `Y-m-d`, must be today or in the future.
- `passengers`: required, integer, minimum 1.
- `sort`: optional, format `field:direction` (e.g., `price:asc`, `departure:asc`).
- `filter[*]`: optional, applied against the canonical `FlightOffer` fields.

Pricing semantics:
- Provider prices are treated as **per-passenger**. The consumer multiplies by `passengers` for a total; the API keeps `price` per-passenger for consistency across providers.

Behavior:
- Validate query params; return `422` on invalid input.
- Dispatch searches to all registered providers concurrently with a timeout.
- Normalize every provider result to `FlightOffer`.
- Deduplicate by stable flight id; merge or pick best price.
- Apply requested sorting/filtering.
- Return unified list plus a `meta.providers` block describing completeness.

### `POST /api/bookings`

Body: `flight_id`, `passengers[]`.

Behavior:
- Validate flight id and passenger data.
- Resolve the flight (re-fetch or cached snapshot).
- Create a `Booking` with a unique reference.
- Return `201` with booking details.

### `GET /api/bookings/{reference}`

Behavior:
- Look up booking by reference.
- Return `404` if not found.
- Return booking details including the flight snapshot stored at booking time.

## Example Response

### `GET /api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2`

```json
{
  "data": [
    {
      "id": "<stable-flight-id>",
      "carrier": "EK",
      "origin": "DAC",
      "destination": "DXB",
      "departure": "2026-07-01T03:45:00Z",
      "arrival": "2026-07-01T06:50:00Z",
      "duration_minutes": 185,
      "stops": 0,
      "price": 399.00,
      "total_price": 798.00,
      "currency": "USD",
      "flight_number": "EK585",
      "provider": "ProviderB"
    }
  ],
  "meta": {
    "providers": [
      { "name": "ProviderA", "status": "success", "offers": 2, "duration_ms": 12 },
      { "name": "ProviderB", "status": "success", "offers": 3, "duration_ms": 18 },
      { "name": "ProviderC", "status": "success", "offers": 1, "duration_ms": 9 }
    ],
    "total_flights": 10,
    "unique_flights": 6,
    "passengers": 2,
    "currency": "USD",
    "price_unit": "per_passenger"
  }
}
```

## Services

- **SearchService**: `search(array $params): array` — runs providers, normalizes, deduplicates, sorts/filters. Dispatches local adapters in-process, remote adapters via HTTP pool with per-provider timing.

## Mock Providers

Three local providers must be simulated. Keep the exact flights below — duplicates and price differences matter — but you may add additional flights for richer test scenarios.

| Provider | Response Key | Time Format | Price Format | Stops Field |
|----------|--------------|-------------|--------------|-------------|
| A | `flights` | ISO-8601 | `fare_usd` float | `stops` int |
| B | `data` | `Y-m-d H:i` | `price.amount` + `price.currency` | `segments` int |
| C | `results` | Unix timestamp | `total_price` + `currency` | `layovers` int |

### Provider Fixtures

Use these payloads for the local mocks. All flights are on the `DAC → DXB` route.

**Provider A**

```json
{
  "flights": [
    { "carrier": "AA", "from": "DAC", "to": "DXB", "depart": "2026-07-01T08:00:00", "arrive": "2026-07-01T12:30:00", "stops": 0, "fare_usd": 320.00, "flight_no": "AA101" },
    { "carrier": "AA", "from": "DAC", "to": "DXB", "depart": "2026-07-01T22:10:00", "arrive": "2026-07-02T02:40:00", "stops": 0, "fare_usd": 280.00, "flight_no": "AA205" },
    { "carrier": "BS", "from": "DAC", "to": "DXB", "depart": "2026-07-01T09:15:00", "arrive": "2026-07-01T15:00:00", "stops": 1, "fare_usd": 310.00, "flight_no": "BS220" },
    { "carrier": "EK", "from": "DAC", "to": "DXB", "depart": "2026-07-01T03:45:00", "arrive": "2026-07-01T06:50:00", "stops": 0, "fare_usd": 410.00, "flight_no": "EK585" }
  ]
}
```

**Provider B**

```json
{
  "data": [
    { "airline_code": "BS", "origin": "DAC", "destination": "DXB", "departure_time": "2026-07-01 09:15", "arrival_time": "2026-07-01 15:00", "segments": 1, "price": { "amount": 295, "currency": "USD" }, "number": "BS220" },
    { "airline_code": "BS", "origin": "DAC", "destination": "DXB", "departure_time": "2026-07-01 14:30", "arrival_time": "2026-07-01 19:20", "segments": 1, "price": { "amount": 265, "currency": "USD" }, "number": "BS118" },
    { "airline_code": "EK", "origin": "DAC", "destination": "DXB", "departure_time": "2026-07-01 03:45", "arrival_time": "2026-07-01 06:50", "segments": 0, "price": { "amount": 399, "currency": "USD" }, "number": "EK585" }
  ]
}
```

**Provider C**

```json
{
  "results": [
    { "iata": "AA", "route": { "src": "DAC", "dst": "DXB" }, "times": { "dep": 1782892800, "arr": 1782909000 }, "layovers": 0, "total_price": 335, "currency": "USD", "code": "AA101" },
    { "iata": "CJ", "route": { "src": "DAC", "dst": "DXB" }, "times": { "dep": 1782885600, "arr": 1782903600 }, "layovers": 2, "total_price": 270, "currency": "USD", "code": "CJ300" },
    { "iata": "EK", "route": { "src": "DAC", "dst": "DXB" }, "times": { "dep": 1782877500, "arr": 1782888600 }, "layovers": 0, "total_price": 405, "currency": "USD", "code": "EK585" }
  ]
}
```

Implement each as a service class that can be swapped between a real HTTP client and a local mock. For this exercise, local mocks returning static fixture data are sufficient, but the architecture should allow real HTTP endpoints later.

## Enums

| Enum | Values |
|------|--------|
| `ProviderStatus` | `SUCCESS`, `TIMEOUT`, `ERROR`, `PARTIAL` |
| `BookingStatus` | `CONFIRMED`, `CANCELLED` |
| `SortField` | `PRICE`, `DEPARTURE`, `ARRIVAL`, `STOPS`, `DURATION` |
| `SortDirection` | `ASC`, `DESC` |

## Routes

API routes live in `routes/api.php`. The Swagger UI page is in `routes/web.php`.

```
GET  /api/flights/search
POST /api/bookings
GET  /api/bookings/{reference}
GET  /api/docs/openapi.yaml
GET  /apidocs              (Swagger UI)
```

## Controllers

- **FlightSearchController**: `search(Request $request)` — validates input, delegates to `SearchService`, returns JSON.
- **BookingController**: `store(Request $request)`, `show(string $reference)` — validates input, resolves flight from cache, creates/retrieves booking, returns JSON.

## Other Key Files

- **Config**: `config/providers.php` — list of enabled providers, timeout, base URLs for future real integrations.
- **Factories**: `FlightOfferFactory` (for test fixtures), `BookingFactory`.
- **Tests**: Feature tests for search, booking, and provider adapters; unit tests for deduplication, filtering, and sorting.

## Testing

- **Framework**: Pest 4, SQLite in-memory.
- **Structure**: Feature/API tests for endpoints, Unit tests for services and normalizers.
- **Pattern**: Arrange → Act → Assert. Mock providers at the adapter level, not the HTTP client, unless testing the HTTP adapter itself.
- **Must cover**: provider normalization, deduplication, sorting/filtering, completeness metadata, booking creation and retrieval, invalid input handling.

## Static Analysis

- **Tool**: Larastan / PHPStan level 6.
- **Run**: `vendor/bin/phpstan analyse --memory-limit=1G`
- Keep baseline minimal; don't add new errors.

## Submission

Push the completed project to a **public GitHub or BitBucket repository** and send the link to `career@iboxlab.com`.

Include `README.md` (setup, run, test, API usage) and `ARCHITECTURE.md` (design decisions, trade-offs, extension points) in the repository root.

## Important Notes

- **No auth, no payments, no frontend** — do not build them.
- **Currency normalization**: All providers in this exercise return USD; still normalize and store currency explicitly.
- **Timezone handling**: Convert all provider times to UTC internally; return ISO-8601 in responses.
- **Timeouts**: Each provider call should have a bounded timeout; failures must not crash the whole search.
- **Deterministic IDs**: The stable flight id must be deterministic so the same flight from different providers collapses cleanly and booking references remain consistent.
- **Booking snapshot**: Store a snapshot of the flight at booking time so price and details remain immutable even if provider data changes.
- **Documentation**: Include `README.md` (setup, run, test, API usage) and `ARCHITECTURE.md` (design decisions, trade-offs, extension points) in the submission.

## Common Traps

1. **Leaking provider schemas outside the adapter**. Controllers and services should only speak the canonical model.
2. **Deduplicating by flight number alone**. Use origin, destination, carrier, and departure time in the identity to avoid collapsing different days or routes.
3. **Ignoring partial failures**. A provider timing out is not a 500 for the whole request; surface it in `meta.providers`.
4. **Storing only the flight id in the booking**. Snapshot the full offer so historical bookings remain accurate.
5. **Over-engineering the provider dispatch**. Start with sequential or simple concurrent calls; add queues or circuit breakers only if justified.

## Before You Code — Checklist

1. `php artisan make:model -mf` for `Booking` (and `Passenger` if modeled separately). Let Artisan scaffold.
2. Create `app/FlightSearch/` directory structure: `Contracts/`, `Adapters/`, `ValueObjects/`.
3. Write the `ProviderContract` and the three adapters BEFORE the controller.
4. Write the `SearchService` unit tests BEFORE the endpoints (TDD).
5. `vendor/bin/pint --dirty --format agent` before committing.

# Project Rules: Strict Anti-Slop Policy

These aren't arbitrary rules. They exist because every violation creates pain for someone reading or debugging your code later. Treat them as craftsmanship standards, not bureaucracy.

## 1. Output Constraints

- **Absolute Completeness**: Never write `// TODO` or stubs. Either implement fully or don't commit the code. If blocked, state the blocker in ≤2 bullets and move on.
- **No Obvious Comments**: `// increment i` is noise. Comments should explain *why*, not *what*. Reserve them for non-obvious intent (e.g., "must flush here to avoid deadlock").
- **Zero Conversational Slop**: No preambles, no apologies, no "here's what I did" summaries in output. Write code or write a dense bullet. The work speaks for itself.
- **No Speculative Dependencies**: Don't add packages you "might need". Every dependency is a maintenance burden. Use what exists in composer.json.

## 2. Refactoring & Tool Protocol

- **Surgical Edits**: Change only the specific blocks the task requires. Don't reformat the whole file, rename unrelated variables, or "clean up" adjacent code. If the file needs that, do it in a separate change.
- **LSP Compliance**: Clear local LSP diagnostics before concluding. A warning you ignore today is a bug someone bisects tomorrow.
- **Error Hygiene**: Wrap I/O, API calls, and unsafe state in defensive error handling. Null is not a valid return where an array is expected. Fail loudly where you should, handle gracefully where you must.
