<?php

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
}
