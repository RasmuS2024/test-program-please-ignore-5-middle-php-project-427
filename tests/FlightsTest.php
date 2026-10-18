<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class FlightsTest extends TestCase
{
    /**
     * Seeded flights span 30 days from today, so two days out is always inside
     * the window.
     */
    private static function travelDate(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('UTC')))
            ->modify('+2 days')
            ->format('Y-m-d');
    }

    /**
     * @return array{status: int, body: mixed, contentType: string}
     */
    private static function request(string $uri): array
    {
        $app = createApp();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', $uri)
            ->withHeader('Accept', 'application/json');

        $response = $app->handle($request);

        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode((string) $response->getBody(), true),
            'contentType' => $response->getHeaderLine('Content-Type'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstFlight(string $origin, string $destination): array
    {
        $result = self::request("/api/flights?origin={$origin}&destination={$destination}&date=" . self::travelDate());

        self::assertSame(200, $result['status']);
        self::assertIsArray($result['body']);
        self::assertNotEmpty($result['body']);

        return $result['body'][0];
    }

    public function testSearchReturnsFlightsForRouteAndDay(): void
    {
        $date = self::travelDate();
        $result = self::request("/api/flights?origin=MOW&destination=LED&date={$date}");

        self::assertSame(200, $result['status']);
        self::assertStringContainsString('application/json', $result['contentType']);
        self::assertIsArray($result['body']);
        self::assertNotEmpty($result['body']);

        foreach ($result['body'] as $flight) {
            self::assertIsString($flight['id']);
            self::assertNotSame('', $flight['id']);
            self::assertIsString($flight['flightNumber']);

            self::assertSame('MOW', $flight['origin']['code']);
            self::assertSame('LED', $flight['destination']['code']);
            self::assertIsString($flight['origin']['name']);
            self::assertIsString($flight['destination']['name']);

            self::assertIsString($flight['airline']['code']);
            self::assertIsString($flight['airline']['name']);

            // The filter day and the rendered departure are the same UTC day.
            self::assertStringStartsWith($date . 'T', $flight['departureAt']);
            self::assertStringEndsWith('Z', $flight['departureAt']);
            self::assertStringEndsWith('Z', $flight['arrivalAt']);

            self::assertIsInt($flight['durationMinutes']);
            self::assertIsInt($flight['seatsAvailable']);

            self::assertIsInt($flight['price']['amount']);
            self::assertSame('RUB', $flight['price']['currency']);
        }
    }

    public function testSearchFiltersByAvailableSeats(): void
    {
        $date = self::travelDate();
        $all = self::request("/api/flights?origin=MOW&destination=LED&date={$date}")['body'];

        $threshold = max(array_column($all, 'seatsAvailable'));

        $filtered = self::request(
            "/api/flights?origin=MOW&destination=LED&date={$date}&passengers={$threshold}"
        );

        self::assertSame(200, $filtered['status']);
        self::assertNotEmpty($filtered['body']);

        foreach ($filtered['body'] as $flight) {
            self::assertGreaterThanOrEqual($threshold, $flight['seatsAvailable']);
        }
    }

    public function testSameCityReturnsEmptyArrayNotAnError(): void
    {
        $date = self::travelDate();
        $result = self::request("/api/flights?origin=MOW&destination=MOW&date={$date}");

        self::assertSame(200, $result['status']);
        self::assertSame([], $result['body']);
    }

    public function testUnknownRouteReturnsEmptyArrayNotAnError(): void
    {
        $date = self::travelDate();
        $result = self::request("/api/flights?origin=MOW&destination=XXX&date={$date}");

        self::assertSame(200, $result['status']);
        self::assertSame([], $result['body']);
    }

    public function testMissingDateIsValidationError(): void
    {
        $result = self::request('/api/flights?origin=MOW&destination=LED');

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
        self::assertNotSame('', $result['body']['message']);
    }

    public function testMissingOriginIsValidationError(): void
    {
        $date = self::travelDate();
        $result = self::request("/api/flights?destination=LED&date={$date}");

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    #[DataProvider('invalidDates')]
    public function testInvalidDateFormatIsValidationError(string $date): void
    {
        $result = self::request("/api/flights?origin=MOW&destination=LED&date={$date}");

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'not a date' => ['tomorrow'],
            'wrong separator' => ['15.10.2026'],
            'impossible day' => ['2026-02-30'],
            'bare year-month' => ['2026-10'],
        ];
    }

    #[DataProvider('invalidPassengerCounts')]
    public function testInvalidPassengersIsValidationError(string $passengers): void
    {
        $date = self::travelDate();
        $result = self::request("/api/flights?origin=MOW&destination=LED&date={$date}&passengers={$passengers}");

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPassengerCounts(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'not a number' => ['many'],
            'fraction' => ['1.5'],
        ];
    }

    public function testFlightByIdReturnsTheSameShapeAsSearch(): void
    {
        $fromSearch = self::firstFlight('MOW', 'LED');

        $result = self::request('/api/flights/' . $fromSearch['id']);

        self::assertSame(200, $result['status']);
        self::assertStringContainsString('application/json', $result['contentType']);
        self::assertSame($fromSearch, $result['body']);
    }

    public function testUnknownFlightIdReturnsNotFound(): void
    {
        $result = self::request('/api/flights/NOPE');

        self::assertSame(404, $result['status']);
        self::assertSame('not_found', $result['body']['code']);
        self::assertNotSame('', $result['body']['message']);
    }

    public function testMissingFlightIdReturnsNotFound(): void
    {
        $result = self::request('/api/flights/999999999');

        self::assertSame(404, $result['status']);
        self::assertSame('not_found', $result['body']['code']);
    }
}
