<?php

use App\FlightSearch\Adapters\ProviderB;
use App\FlightSearch\Adapters\ProviderC;
use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\FlightIdGenerator;
use App\FlightSearch\Services\ProviderDispatcher;
use App\FlightSearch\Services\ProviderRegistry;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\SearchRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function dispatcher(): ProviderDispatcher
{
    return app(ProviderDispatcher::class);
}

function fixtureA(): array
{
    return [
        [
            'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
            'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00',
            'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101',
        ],
    ];
}

function fixtureB(): array
{
    return [
        [
            'airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
            'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50',
            'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585',
        ],
    ];
}

function fixtureC(): array
{
    return [
        [
            'iata' => 'CJ', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
            'times' => ['dep' => 1782885600, 'arr' => 1782903600],
            'layovers' => 2, 'total_price' => 270, 'currency' => 'USD', 'code' => 'CJ300',
        ],
    ];
}

test('dispatches all providers concurrently and normalizes offers', function () {
    Http::fake([
        '*api/internal/providers/ProviderA/fixtures*' => Http::response(fixtureA()),
        '*api/internal/providers/ProviderB/fixtures*' => Http::response(fixtureB()),
        '*api/internal/providers/ProviderC/fixtures*' => Http::response(fixtureC()),
    ]);

    $results = dispatcher()->dispatch(new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 1,
    ));

    expect($results)->toHaveCount(3);

    $names = array_map(fn ($r) => $r->providerName, $results);
    expect($names)->toContain('ProviderA')
        ->and($names)->toContain('ProviderB')
        ->and($names)->toContain('ProviderC');

    $successResults = array_filter($results, fn ($r) => $r->status === ProviderStatus::SUCCESS);
    expect($successResults)->toHaveCount(3);
});

test('marks a remote provider as error on non-successful http response', function () {
    $registry = app(ProviderRegistry::class);
    $registry->register(new ExternalTestProvider('TestRemote'));

    Http::fake([
        '*api/external/fixtures*' => Http::response('Internal Server Error', 500),
    ]);

    $results = dispatcher()->dispatch(new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 1,
    ));

    $found = collect($results)->first(fn ($r) => $r->providerName === 'TestRemote');

    expect($found->status)->toBe(ProviderStatus::ERROR)
        ->and($found->errorMessage)->toBe('Provider request failed.');
});

test('isolates normalization failures via remote provider', function () {
    $registry = app(ProviderRegistry::class);
    $registry->register(new ExternalTestProvider(
        'RemoteBadData',
        new ProviderB(new FlightIdGenerator),
    ));

    Http::fake([
        '*api/external/fixtures*' => Http::response([
            ['airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => 'not-a-date', 'arrival_time' => '2026-07-01 06:50',
                'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585'],
        ]),
    ]);

    $results = dispatcher()->dispatch(new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 1,
    ));

    $found = collect($results)->first(fn ($r) => $r->providerName === 'RemoteBadData');

    expect($found->status)->toBe(ProviderStatus::ERROR);
});

test('marks remote provider partial when some offers normalize and others fail', function () {
    $registry = app(ProviderRegistry::class);
    $registry->register(new ExternalTestProvider('RemotePartial'));

    Http::fake([
        '*api/external/fixtures*' => Http::response([
            ['iata' => 'AA', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782892800, 'arr' => 1782909000],
                'layovers' => 0, 'total_price' => 335, 'currency' => 'USD', 'code' => 'AA101'],
            'this is not a valid flight array',
        ]),
    ]);

    $results = dispatcher()->dispatch(new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 1,
    ));

    $found = collect($results)->first(fn ($r) => $r->providerName === 'RemotePartial');

    expect($found->status)->toBe(ProviderStatus::PARTIAL)
        ->and($found->offers)->toHaveCount(1)
        ->and($found->errorMessage)->toBe('Some provider offers could not be normalized.');
});

class ExternalTestProvider implements ProviderContract
{
    public function __construct(
        private readonly string $name,
        private readonly ?ProviderContract $delegate = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function endpoint(): string
    {
        return '/api/external/fixtures';
    }

    public function fixtures(): array
    {
        return [];
    }

    public function normalize(array $raw): FlightOffer
    {
        $adapter = $this->delegate ?? new ProviderC(new FlightIdGenerator);

        return $adapter->normalize($raw);
    }
}
