# Architecture

## Directory layout

```
app/FlightSearch/
  Contracts/        ProviderContract interface
  Adapters/         ProviderA, ProviderB, ProviderC
  ValueObjects/     FlightOffer (readonly DTO)
  Enums/            ProviderStatus, BookingStatus, SortField, SortDirection
  Services/         SearchService (dispatch, dedup, filter, sort)
app/Models/         Booking (the only Eloquent model)
app/Http/           FlightSearchController, BookingController, ProviderFixtureController
app/Http/Resources/ BookingResource
config/providers.php
routes/api.php
routes/web.php                  (apidocs Swagger UI route)
resources/views/api-docs.blade.php
```

No `app/Services/` dumping ground. The domain lives under `app/FlightSearch/` so every file has one obvious home. A developer looking for "how ProviderB normalizes its payload" opens `Adapters/ProviderB.php` and finds it next to the contract it implements, not buried in a 40-file flat directory.

The `resources/views/api-docs.blade.php` renders a Swagger UI page served at `/apidocs` (defined in `routes/web.php`). The raw OpenAPI YAML lives at `storage/api-docs/api.yaml` and is served at `/api/docs/openapi.yaml` (defined in `routes/api.php`).

---

## Decision log

### 1. Canonical flight identity is a SHA-256 hash

The stable flight ID is `hash('sha256', "CARRIER|FLIGHT_NO|ORIGIN|DEST|DEPARTURE_UTC")`.

**Why SHA-256, not a composite key.** Composite keys leak into query strings and logs. A 64-char hex string is opaque, collision-resistant for this domain, and deterministic — any consumer can recompute it from the canonical fields without a lookup table.

**Why departure time is in the identity.** Two flights with the same carrier, number, and route on different days are different flights. Carrier + flight number + route alone is insufficient. The AGENTS.md explicitly warns against this trap.

**Not included: price, provider, stops.** These are offer attributes, not flight attributes. A flight is the physical aircraft movement; an offer is a price from a seller for that movement.

### 2. Deduplication keeps the cheapest offer

Two providers returning the same flight (same `id`) collapse to the lowest-priced offer. Provider attribution on the surviving record is the provider that quoted the best price.

**Why not merge provider attributions.** Merging ("available from ProviderA and ProviderB") creates ambiguity about which price is which. The consumer wants a price they can act on. The `meta.providers` block already surfaces per-provider completeness; a consumer that needs multi-provider confirmation can inspect the raw count.

**What's lost.** A user might prefer ProviderA's UX for the same flight even at a higher price. That's a product decision, not an engineering one. The architecture supports it by switching the dedup comparator from `min(price)` to a configurable strategy.

### 3. Provider times are normalized to UTC at the adapter boundary

Every adapter converts its provider's time format to UTC ISO-8601 before constructing a `FlightOffer`. There is no central normalizer; the adapter is the only place that knows its raw schema.

**ProviderB times are UTC, not Asia/Dhaka.** The fixture `departure_time: "2026-07-01 03:45"` for EK585, when treated as UTC, produces the same canonical identity as ProviderA's `"2026-07-01T03:45:00"` and ProviderC's Unix timestamp `1782877500` (which is 2026-07-01T03:45:00Z). Treating it as Asia/Dhaka (UTC+6) breaks deduplication and contradicts the example response in the spec which shows `"departure": "2026-07-01T03:45:00Z"`.

**Risk if a real provider sends local times.** That adapter would need an explicit timezone config. The contract allows it — each adapter owns its timezone logic.

### 4. Provider dispatch is concurrent with a bounded timeout

`SearchService` fans out to all registered providers. Local adapters (endpoints starting with `/api/internal/`) are called in-process via `fixtures()`. Remote adapters use Laravel's HTTP pool for concurrent async requests. The configured `providers.timeout` aborts any pool request that takes too long.

**Why HTTP endpoints for mocks.** True concurrency in PHP is easiest with I/O-bound async HTTP calls. Each mock adapter exposes its fixtures via an internal `/api/internal/providers/{name}/fixtures` route, so the search service exercises the same concurrency path a real integration would use.

**Why not Fiber/Swoole/ReactPHP.** Laravel's `Http::async()` gives sufficient concurrency for HTTP-bound providers without adding non-standard extensions or runtime dependencies. CPU-bound work is negligible once the response is received.

### 5. Error isolation: one provider failing does not crash the search

If any provider times out or returns a non-successful HTTP response, `SearchService` catches it, logs it server-side, and builds a per-provider result with status `timeout`/`error` and a generic `error_message`. The consumer sees the failure in `meta.providers` without stack traces or connection details. Per-provider latency is measured individually via Guzzle's `TransferStats::getTransferTime()`, not averaged across the pool.

### 6. Flight snapshots are stored at booking time, not looked up later

When a booking is created, the controller resolves the flight from a short-lived cache populated during search, then serializes the full `FlightOffer` into `flight_snapshot` (JSON column on `bookings`).

**Why cache.** A real search → booking flow might have seconds between search and purchase. Provider prices can change. By resolving from the cached search result and then storing a snapshot, the booking is immutable regardless of what the provider returns later. The snapshot is the source of truth for that booking. Caching also removes the need for a hardcoded route/date when resolving a flight by its stable ID.

**Why not re-query providers.** The stable ID is a one-way hash; you cannot derive the original route or date from it. Re-querying would require either a hardcoded request (brittle) or re-running the original search. A short-lived cache keyed by flight ID is the simplest correct solution.

### 7. Passengers are embedded JSON, not a separate table

The `bookings.passengers` column stores a JSON array of `{name, email, date_of_birth}`. No `passengers` table, no pivot.

**Why.** There's no requirement to query passengers independently ("find all bookings for john@example.com"). Adding a separate table with a belongsToMany relationship creates 3 files (model, migration, pivot) and an extra JOIN on every booking lookup for zero value today. If passenger search becomes a requirement later, the JSON column can be migrated to a proper table without changing the API response shape.

**The one cost.** JSON columns have no referential integrity or unique constraints at the row level. A passenger can be "duplicated" across bookings. That's acceptable — bookings are independent contracts with the airline.

### 8. BookingResource for serialization control, but no ResourceCollection

The booking response uses a `BookingResource` (JsonResource) to compute `total_price` from `price × passengers` and format timestamps. The search response keeps a plain `response()->json()` array — no resource layer.

**Why a resource for booking but not search.** The booking response needs computed fields (`total_price`, `currency`) derived from the cached flight + passengers. A resource isolates that logic from the controller. The search response is a direct projection of `FlightOffer::toArray()` with one computed merge (`total_price`), which is simpler inline.

### 9. Provider registration is config-driven

`AppServiceProvider` reads the provider FQCN list from `config/providers.php`, resolves each through the container, and binds the resulting adapter instances as a `'providers'` singleton.

**Why config-driven.** The `config/providers.php` file is the single source of truth for which providers are active. Adding a new provider means creating the adapter class and adding its FQCN to the config array — no service provider changes. The registration loop calls `$app->make()` on each FQCN, so adapters with constructor dependencies (e.g., an HTTP client for real integrations) are resolved automatically.

### 10. No queues, no jobs, no horizon

The entire flow is synchronous: HTTP request → controller → service → providers → response.

**Why.** Queues solve two problems: long-running work and fan-out parallelism. The mock providers return in microseconds. Adding a `SearchProviders` job that fans out to 3 `QueryProvider` jobs and a `GatherResults` listener is a 10-file architecture for a problem that doesn't exist yet. When real providers with 2-second HTTP timeouts arrive, a queue-based approach becomes the right call.

---

## What I'd do with more time

The README's Future Roadmap has the full prioritized backlog. A few architecture-specific additions:

1. **Persist flight-offer cache.** Booking currently depends on a short-lived array cache. Move to Redis or a `flight_offers` table keyed by stable ID so bookings are resolvable long after the original search.

2. **Structured error codes.** Add machine-readable codes (`PROVIDER_TIMEOUT`, `PROVIDER_UNREACHABLE`, `PROVIDER_INVALID_RESPONSE`) alongside generic messages in `meta.providers[].error_message`.

---

## Invariants enforced by the architecture

- **No provider schema leaks past the adapter.** Controllers, services, and responses only speak `FlightOffer`. The `meta.providers` block exposes names and counts — never raw provider keys.
- **Flight identity is stable.** Same flight from different providers → same `id`. Always.
- **Booking data is immutable.** Once written, `flight_snapshot` and `passengers` never change. Status transitions (confirmed → cancelled) are the only allowed mutation.
- **Partial failure is surfaced, not hidden.** If one provider fails, the search continues and `meta.providers` tells the consumer exactly what happened.
