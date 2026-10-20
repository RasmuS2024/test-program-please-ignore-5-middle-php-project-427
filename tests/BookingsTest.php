<?php

namespace Hexlet\Code\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class BookingsTest extends TestCase
{
    private static function travelDate(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('UTC')))
            ->modify('+2 days')
            ->format('Y-m-d');
    }

    /**
     * @return array{status: int, body: mixed, contentType: string}
     */
    private static function send(string $method, string $uri, ?array $body = null): array
    {
        $app = createApp();

        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $uri)
            ->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream((string) json_encode($body)));
        }

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
    private static function firstFlight(): array
    {
        $result = self::send('GET', '/api/flights?origin=MOW&destination=LED&date=' . self::travelDate());

        self::assertSame(200, $result['status']);
        self::assertNotEmpty($result['body']);

        return $result['body'][0];
    }

    /**
     * @return array<string, string>
     */
    private static function contact(): array
    {
        return ['email' => 'traveler@example.com', 'phone' => '+7 900 000-00-00'];
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private static function passenger(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Иван',
            'lastName' => 'Петров',
            'dateOfBirth' => '2000-01-01',
            // Format is NOT validated: "1" must pass.
            'documentNumber' => '1',
        ], $overrides);
    }

    /**
     * @param list<array<string, string>> $passengers
     *
     * @return array{status: int, body: mixed, contentType: string}
     */
    private static function createBooking(string $flightId, array $passengers): array
    {
        return self::send('POST', '/api/bookings', [
            'flightId' => $flightId,
            'contact' => self::contact(),
            'passengers' => $passengers,
        ]);
    }

    public function testCreateBookingWithOnePassenger(): void
    {
        $flight = self::firstFlight();
        $result = self::createBooking($flight['id'], [self::passenger()]);

        self::assertSame(201, $result['status']);
        self::assertStringContainsString('application/json', $result['contentType']);

        $booking = $result['body'];

        self::assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $booking['code']);
        self::assertSame('confirmed', $booking['status']);
        self::assertSame($flight, $booking['flight']);
        self::assertSame(self::contact(), $booking['contact']);
        self::assertCount(1, $booking['passengers']);
        self::assertSame('1', $booking['passengers'][0]['documentNumber']);
        self::assertSame('2000-01-01', $booking['passengers'][0]['dateOfBirth']);
        self::assertSame($flight['price']['amount'], $booking['totalPrice']['amount']);
        self::assertSame('RUB', $booking['totalPrice']['currency']);
        self::assertStringEndsWith('Z', $booking['createdAt']);
    }

    public function testTwoPassengersDoubleTheTotalPrice(): void
    {
        $flight = self::firstFlight();
        $result = self::createBooking($flight['id'], [self::passenger(), self::passenger(['lastName' => 'Сидоров'])]);

        self::assertSame(201, $result['status']);
        self::assertCount(2, $result['body']['passengers']);
        self::assertSame($flight['price']['amount'] * 2, $result['body']['totalPrice']['amount']);
    }

    public function testEachBookingGetsItsOwnCode(): void
    {
        $flight = self::firstFlight();

        $first = self::createBooking($flight['id'], [self::passenger()]);
        $second = self::createBooking($flight['id'], [self::passenger()]);

        self::assertSame(201, $first['status']);
        self::assertSame(201, $second['status']);
        self::assertNotSame($first['body']['code'], $second['body']['code']);
    }

    public function testBookingAndPassengersArePersisted(): void
    {
        $flight = self::firstFlight();
        $result = self::createBooking($flight['id'], [self::passenger(), self::passenger(['lastName' => 'Сидоров'])]);
        $code = $result['body']['code'];

        $pdo = pdo();

        $bookingStmt = $pdo->prepare('SELECT flight_id, status, total_price FROM bookings WHERE code = :code');
        $bookingStmt->execute(['code' => $code]);
        $row = $bookingStmt->fetch();

        self::assertNotFalse($row);
        self::assertSame('confirmed', $row['status']);
        self::assertSame((string) $flight['id'], (string) $row['flight_id']);
        self::assertSame($result['body']['totalPrice']['amount'], (int) $row['total_price']);

        $passengerStmt = $pdo->prepare('SELECT count(*) FROM booking_passengers WHERE booking_code = :code');
        $passengerStmt->execute(['code' => $code]);

        self::assertSame(2, (int) $passengerStmt->fetchColumn());
    }

    public function testMissingFlightIdIsValidationError(): void
    {
        $result = self::send('POST', '/api/bookings', [
            'contact' => self::contact(),
            'passengers' => [self::passenger()],
        ]);

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    public function testUnknownFlightIdIsValidationError(): void
    {
        $result = self::createBooking('999999999', [self::passenger()]);

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    public function testNonNumericFlightIdIsValidationError(): void
    {
        $result = self::createBooking('NOPE', [self::passenger()]);

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    public function testEmptyBodyIsValidationError(): void
    {
        $result = self::send('POST', '/api/bookings');

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    public function testMalformedJsonIsValidationError(): void
    {
        $app = createApp();

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/bookings')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream('{"flightId":'));

        $response = $app->handle($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('validation_error', json_decode((string) $response->getBody(), true)['code']);
    }

    /**
     * @param array<string, mixed> $defect
     */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodyIsValidationError(array $defect): void
    {
        $flight = self::firstFlight();

        $body = array_merge([
            'flightId' => $flight['id'],
            'contact' => self::contact(),
            'passengers' => [self::passenger()],
        ], $defect);

        $result = self::send('POST', '/api/bookings', $body);

        self::assertSame(400, $result['status']);
        self::assertSame('validation_error', $result['body']['code']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidBodies(): array
    {
        return [
            'missing contact' => [['contact' => null]],
            'contact without email' => [['contact' => ['phone' => '1']]],
            'contact with empty email' => [['contact' => ['email' => '', 'phone' => '1']]],
            'contact with blank phone' => [['contact' => ['email' => 'a@b', 'phone' => '   ']]],
            'missing passengers' => [['passengers' => null]],
            'empty passengers' => [['passengers' => []]],
            'passengers not a list' => [['passengers' => 'Иван']],
            'passenger missing lastName' => [['passengers' => [['firstName' => 'Иван', 'dateOfBirth' => '2000-01-01', 'documentNumber' => '1']]]],
            'passenger blank firstName' => [['passengers' => [['firstName' => '  ', 'lastName' => 'Петров', 'dateOfBirth' => '2000-01-01', 'documentNumber' => '1']]]],
            'passenger missing documentNumber' => [['passengers' => [['firstName' => 'Иван', 'lastName' => 'Петров', 'dateOfBirth' => '2000-01-01']]]],
            'passenger empty dateOfBirth' => [['passengers' => [['firstName' => 'Иван', 'lastName' => 'Петров', 'dateOfBirth' => '', 'documentNumber' => '1']]]],
            'passenger invalid dateOfBirth' => [['passengers' => [['firstName' => 'Иван', 'lastName' => 'Петров', 'dateOfBirth' => 'not-a-date', 'documentNumber' => '1']]]],
        ];
    }

    // --- booking lookup & cancellation -----------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function createdBooking(): array
    {
        $flight = self::firstFlight();
        $result = self::createBooking($flight['id'], [self::passenger()]);

        self::assertSame(201, $result['status']);

        return $result['body'];
    }

    private static function bookingUrl(string $code, string $lastName): string
    {
        return '/api/bookings/' . rawurlencode($code) . '?' . http_build_query(['lastName' => $lastName]);
    }

    private static function unusedCode(): string
    {
        $pdo = pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM bookings WHERE code = :code');

        for ($i = 0; $i < 100; $i++) {
            $candidate = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt->execute(['code' => $candidate]);

            if ($stmt->fetch() === false) {
                return $candidate;
            }
        }

        self::fail('Could not find an unused booking code');
    }

    public function testFindBookingByCodeAndLastName(): void
    {
        $booking = self::createdBooking();

        $result = self::send('GET', self::bookingUrl($booking['code'], 'Петров'));

        self::assertSame(200, $result['status']);
        self::assertSame($booking, $result['body']);
    }

    public function testFindBookingIgnoresLastNameCaseAndSurroundingSpaces(): void
    {
        $booking = self::createdBooking();

        foreach (['петров', 'ПЕТРОВ', '  Петров  ', 'пЕтРоВ'] as $variant) {
            $result = self::send('GET', self::bookingUrl($booking['code'], $variant));

            self::assertSame(200, $result['status'], "lastName variant '{$variant}' should match");
            self::assertSame($booking['code'], $result['body']['code']);
        }
    }

    public function testFindBookingMatchesAnyPassengerLastName(): void
    {
        $flight = self::firstFlight();
        $result = self::createBooking($flight['id'], [self::passenger(), self::passenger(['lastName' => 'Сидоров'])]);
        $code = $result['body']['code'];

        self::assertSame(200, self::send('GET', self::bookingUrl($code, 'Петров'))['status']);
        self::assertSame(200, self::send('GET', self::bookingUrl($code, 'сидоров'))['status']);
    }

    public function testFindBookingAcceptsLowerCaseCode(): void
    {
        $booking = self::createdBooking();

        $result = self::send('GET', self::bookingUrl(strtolower($booking['code']), 'Петров'));

        self::assertSame(200, $result['status']);
        self::assertSame($booking['code'], $result['body']['code']);
    }

    public function testFindBookingWithWrongLastNameReturnsNotFound(): void
    {
        $booking = self::createdBooking();

        $result = self::send('GET', self::bookingUrl($booking['code'], 'Иванов'));

        self::assertSame(404, $result['status']);
        self::assertSame('not_found', $result['body']['code']);
    }

    public function testFindBookingWithoutLastNameReturnsNotFound(): void
    {
        $booking = self::createdBooking();

        $missing = self::send('GET', '/api/bookings/' . $booking['code']);
        $empty = self::send('GET', '/api/bookings/' . $booking['code'] . '?lastName=');

        self::assertSame(404, $missing['status']);
        self::assertSame(404, $empty['status']);
    }

    public function testFindBookingWithUnknownCodeReturnsNotFound(): void
    {
        $result = self::send('GET', self::bookingUrl(self::unusedCode(), 'Петров'));

        self::assertSame(404, $result['status']);
        self::assertSame('not_found', $result['body']['code']);
    }

    public function testAllNotFoundBodiesAreIdentical(): void
    {
        $booking = self::createdBooking();

        $wrongCode = self::send('GET', self::bookingUrl(self::unusedCode(), 'Петров'));
        $wrongName = self::send('GET', self::bookingUrl($booking['code'], 'Иванов'));
        $noName = self::send('GET', '/api/bookings/' . $booking['code']);
        $cancelNoName = self::send('POST', '/api/bookings/' . $booking['code'] . '/cancel', []);
        $cancelWrongCode = self::send('POST', '/api/bookings/' . self::unusedCode() . '/cancel', ['lastName' => 'Петров']);

        foreach ([$wrongCode, $wrongName, $noName, $cancelNoName, $cancelWrongCode] as $result) {
            self::assertSame(404, $result['status']);
            self::assertSame($wrongCode['body'], $result['body']);
        }
    }

    public function testCancelBookingReturnsCancelledStatus(): void
    {
        $booking = self::createdBooking();

        $result = self::send('POST', '/api/bookings/' . $booking['code'] . '/cancel', ['lastName' => 'петров']);

        self::assertSame(200, $result['status']);
        self::assertSame($booking['code'], $result['body']['code']);
        self::assertSame('cancelled', $result['body']['status']);
        self::assertSame($booking['flight'], $result['body']['flight']);
        self::assertSame($booking['passengers'], $result['body']['passengers']);
    }

    public function testRepeatedCancelStaysCancelled(): void
    {
        $booking = self::createdBooking();
        $url = '/api/bookings/' . $booking['code'] . '/cancel';

        $first = self::send('POST', $url, ['lastName' => 'Петров']);
        $second = self::send('POST', $url, ['lastName' => 'Петров']);

        self::assertSame(200, $first['status']);
        self::assertSame(200, $second['status']);
        self::assertSame('cancelled', $first['body']['status']);
        self::assertSame('cancelled', $second['body']['status']);
    }

    public function testCancelWithWrongLastNameReturnsNotFoundAndKeepsConfirmed(): void
    {
        $booking = self::createdBooking();

        $result = self::send('POST', '/api/bookings/' . $booking['code'] . '/cancel', ['lastName' => 'Иванов']);

        self::assertSame(404, $result['status']);
        self::assertSame('not_found', $result['body']['code']);

        $stmt = pdo()->prepare('SELECT status FROM bookings WHERE code = :code');
        $stmt->execute(['code' => $booking['code']]);

        self::assertSame('confirmed', $stmt->fetch()['status']);
    }

    public function testCancelWithoutLastNameReturnsNotFound(): void
    {
        $booking = self::createdBooking();

        $result = self::send('POST', '/api/bookings/' . $booking['code'] . '/cancel', []);

        self::assertSame(404, $result['status']);
        self::assertSame('not_found', $result['body']['code']);
    }
}
