# Architecture

## Directory layout

```
app/FlightSearch/
  Contracts/        One interface that every provider implements
  Adapters/         ProviderA, ProviderB, ProviderC — mock adapters
  ValueObjects/     Readonly DTOs: FlightOffer, SearchRequest, SearchResponse, etc.
  Enums/            ProviderStatus, BookingStatus, SortField, SortDirection
  Services/         FlightNormalizer, FlightIdGenerator, ProviderRegistry,
                    SearchService, BookingService, ReferenceGenerator
app/Models/         Booking (the only Eloquent model)
app/Http/           FlightSearchController, BookingController
config/providers.php
routes/api.php
```

No `app/Services/` dumping ground. The domain lives under `app/FlightSearch/` so every file has one obvious home. A developer looking for "how ProviderB normalizes its payload" opens `Adapters/ProviderB.php` and finds it next to the contract it implements, not buried in a 40-file flat directory.

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

Every adapter converts its provider's time format to UTC ISO-8601 before constructing a `FlightOffer`. The normalizer is the only place that touches raw provider timestamps.

**ProviderB times are UTC, not Asia/Dhaka.** The fixture `departure_time: "2026-07-01 03:45"` for EK585, when treated as UTC, produces the same canonical identity as ProviderA's `"2026-07-01T03:45:00"` and ProviderC's Unix timestamp `1782877500` (which is 2026-07-01T03:45:00Z). Treating it as Asia/Dhaka (UTC+6) breaks deduplication and contradicts the example response in the spec which shows `"departure": "2026-07-01T03:45:00Z"`.

**Risk if a real provider sends local times.** That adapter would need an explicit timezone config. The contract allows it — each adapter owns its timezone logic. The normalizer doesn't guess.

### 4. Provider dispatch is concurrent with a bounded timeout

`SearchService` delegates fan-out to `ProviderDispatcher`, which sends async HTTP requests to each registered provider endpoint using Laravel's HTTP client and waits for all responses. The configured `providers.timeout` aborts any single provider that takes too long.

**Why HTTP endpoints for mocks.** True concurrency in PHP is easiest with I/O-bound async HTTP calls. Each mock adapter exposes its fixtures via an internal `/api/internal/providers/{name}/fixtures` route, so the dispatcher exercises the same concurrency path a real integration would use.

**Why not Fiber/Swoole/ReactPHP.** Laravel's `Http::async()` gives sufficient concurrency for HTTP-bound providers without adding non-standard extensions or runtime dependencies. CPU-bound work is negligible once the response is received.

### 5. Error isolation: one provider failing does not crash the search

If any provider times out or returns a non-successful HTTP response, `ProviderDispatcher` catches it, logs it server-side, and returns a `ProviderResultSet` with status `timeout`/`error` and a generic `error_message`. The consumer sees the failure in `meta.providers` without stack traces or connection details.

### 6. Flight snapshots are stored at booking time, not looked up later

When a booking is created, `BookingService` resolves the flight from a short-lived cache populated during search, then serializes the full `FlightOffer` into `flight_snapshot` (JSON column on `bookings`).

**Why cache.** A real search → booking flow might have seconds between search and purchase. Provider prices can change. By resolving from the cached search result and then storing a snapshot, the booking is immutable regardless of what the provider returns later. The snapshot is the source of truth for that booking. Caching also removes the need for a hardcoded route/date when resolving a flight by its stable ID.

**Why not re-query providers.** The stable ID is a one-way hash; you cannot derive the original route or date from it. Re-querying would require either a hardcoded request (brittle) or re-running the original search. A short-lived cache keyed by flight ID is the simplest correct solution.

### 7. Passengers are embedded JSON, not a separate table

The `bookings.passengers` column stores a JSON array of `{name, email, date_of_birth}`. No `passengers` table, no pivot.

**Why.** There's no requirement to query passengers independently ("find all bookings for john@example.com"). Adding a separate table with a belongsToMany relationship creates 3 files (model, migration, pivot) and an extra JOIN on every booking lookup for zero value today. If passenger search becomes a requirement later, the JSON column can be migrated to a proper table without changing the API response shape.

**The one cost.** JSON columns have no referential integrity or unique constraints at the row level. A passenger can be "duplicated" across bookings. That's acceptable — bookings are independent contracts with the airline.

### 8. No API resource classes

Controllers return `response()->json($dto->toArray())`. No `JsonResource`, no `ResourceCollection`.

**Why.** Resources add value when you have multiple serialization contexts (admin panel vs. public API, v1 vs. v2) or need eager-loading control. This API has one consumer and one shape. The `toArray()` on the DTO is the canonical serialization. If a second consumer needs a different shape later, introducing a Resource at that point is straightforward — the DTO remains the internal model.

### 9. ProviderRegistry is explicit, not discovered

`AppServiceProvider::register()` manually instantiates and registers each provider adapter.

**Why not auto-discover from config.** The `config/providers.php` file lists FQCNs and exists for future wiring. Auto-discovery from config (e.g., `app()->make($fqcn)` for each entry) adds a layer of indirection that provides no value when there are 3 providers hardcoded in a single place. When providers move to separate packages or dynamic registration, the config file becomes the single source of truth and the registry can read from it. Today, the explicit registration is obviously correct — you can see exactly what's registered in one file.

### 10. No queues, no jobs, no horizon

The entire flow is synchronous: HTTP request → controller → service → providers → response.

**Why.** Queues solve two problems: long-running work and fan-out parallelism. The mock providers return in microseconds. Adding a `SearchProviders` job that fans out to 3 `QueryProvider` jobs and a `GatherResults` listener is a 10-file architecture for a problem that doesn't exist yet. When real providers with 2-second HTTP timeouts arrive, a queue-based approach becomes the right call.

---

## What I'd do with more time

1. **Circuit breaker / retry policy.** Add per-provider backoff and circuit breaking so a flaky provider doesn't waste timeouts on every request.

2. **Cache search results by query params.** A 60-second cache keyed by normalized search params (`from|to|date`) avoids redundant provider calls for rapid repeated searches. The current cache is keyed by flight ID for booking resolution.

3. **Structured error messages.** Add machine-readable error codes (`PROVIDER_TIMEOUT`, `PROVIDER_UNREACHABLE`, `PROVIDER_INVALID_RESPONSE`) alongside the generic provider error message.

4. **Test coverage.** Feature and unit tests cover normalizer output, deduplication, sorting, filtering, provider error isolation, and the booking lifecycle. Static analysis (Larastan level 6) is configured for the `app` directory.

5. **Input validation for filter enums.** `filter[carrier]` and other filters could be tightened further (e.g. enforce IATA codes).

---

## Invariants enforced by the architecture

- **No provider schema leaks past the adapter.** Controllers, services, and responses only speak `FlightOffer`. The `meta.providers` block exposes names and counts — never raw provider keys.
- **Flight identity is stable.** Same flight from different providers → same `id`. Always.
- **Booking data is immutable.** Once written, `flight_snapshot` and `passengers` never change. Status transitions (confirmed → cancelled) are the only allowed mutation.
- **Partial failure is surfaced, not hidden.** If one provider fails, the search continues and `meta.providers` tells the consumer exactly what happened.
