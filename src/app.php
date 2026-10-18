<?php

use Hexlet\Code\Repository\BookingRepository;
use Hexlet\Code\Repository\CityRepository;
use Hexlet\Code\Repository\FlightRepository;
use Hexlet\Code\Serializer\BookingSerializer;
use Hexlet\Code\Serializer\CitySerializer;
use Hexlet\Code\Serializer\FlightSerializer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

function createApp(): App
{
    $app = AppFactory::create();

    $app->addRoutingMiddleware();
    $app->addBodyParsingMiddleware();

    $errorMiddleware = $app->addErrorMiddleware(true, true, true);
    $errorMiddleware->setDefaultErrorHandler(
        function (Request $request, Throwable $exception) use ($app): Response {
            $statusCode = $exception instanceof HttpException ? $exception->getCode() : 500;
            $code = match ($statusCode) {
                400 => 'validation_error',
                404 => 'not_found',
                405 => 'method_not_allowed',
                default => 'internal_server_error',
            };

            $payload = ['code' => $code, 'message' => $exception->getMessage()];
            $response = $app->getResponseFactory()->createResponse($statusCode);
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
    );

    $app->get('/api/health', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    $app->get('/api/cities', function (Request $request, Response $response) {
        $cities = CitySerializer::many((new CityRepository(pdo()))->all());

        $response->getBody()->write(json_encode($cities, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    $isValidDate = static function (string $value): bool {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    };

    $parsePassengers = static function (mixed $value): ?int {
        if ($value === null || $value === '') {
            return 1;
        }

        if (!is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        $passengers = (int) $value;

        return $passengers >= 1 ? $passengers : null;
    };

    $app->get('/api/flights', function (Request $request, Response $response) use ($isValidDate, $parsePassengers) {
        $params = $request->getQueryParams();

        $origin = trim((string) ($params['origin'] ?? ''));
        $destination = trim((string) ($params['destination'] ?? ''));
        $date = trim((string) ($params['date'] ?? ''));

        if ($origin === '' || $destination === '' || $date === '') {
            throw new HttpBadRequestException($request, 'Параметры origin, destination и date обязательны.');
        }

        if (!$isValidDate($date)) {
            throw new HttpBadRequestException($request, 'Параметр date должен быть в формате YYYY-MM-DD.');
        }

        $passengers = $parsePassengers($params['passengers'] ?? null);

        if ($passengers === null) {
            throw new HttpBadRequestException($request, 'Параметр passengers должен быть целым числом не меньше 1.');
        }

        $flights = FlightSerializer::many(
            (new FlightRepository(pdo()))->search($origin, $destination, $date, $passengers)
        );

        $response->getBody()->write(json_encode($flights, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    $app->get('/api/flights/{id}', function (Request $request, Response $response, array $args) {
        $flight = (new FlightRepository(pdo()))->find((string) $args['id']);

        if ($flight === null) {
            throw new HttpNotFoundException($request, 'Рейс не найден.');
        }

        $response->getBody()->write(json_encode(FlightSerializer::serialize($flight), JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    $nonEmptyString = static function (mixed $value): bool {
        return is_string($value) && trim($value) !== '';
    };

    $normalizeDate = static function (mixed $value): ?string {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $strict = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($strict !== false && $strict->format('Y-m-d') === $value) {
            return $value;
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    };

    $app->post('/api/bookings', function (Request $request, Response $response) use ($nonEmptyString, $normalizeDate) {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            throw new HttpBadRequestException($request, 'Тело запроса должно быть JSON-объектом.');
        }

        $flightId = $body['flightId'] ?? null;

        if (!$nonEmptyString($flightId)) {
            throw new HttpBadRequestException($request, 'Поле flightId обязательно.');
        }

        $contact = $body['contact'] ?? null;

        if (!is_array($contact) || !$nonEmptyString($contact['email'] ?? null) || !$nonEmptyString($contact['phone'] ?? null)) {
            throw new HttpBadRequestException($request, 'Поля contact.email и contact.phone обязательны.');
        }

        $passengersBody = $body['passengers'] ?? null;

        if (!is_array($passengersBody) || $passengersBody === []) {
            throw new HttpBadRequestException($request, 'Нужен хотя бы один пассажир.');
        }

        $passengers = [];

        foreach ($passengersBody as $passenger) {
            if (!is_array($passenger)) {
                throw new HttpBadRequestException($request, 'Каждый пассажир должен быть объектом.');
            }

            $firstName = $passenger['firstName'] ?? null;
            $lastName = $passenger['lastName'] ?? null;
            $documentNumber = $passenger['documentNumber'] ?? null;
            $dateOfBirth = $normalizeDate($passenger['dateOfBirth'] ?? null);

            if (!$nonEmptyString($firstName) || !$nonEmptyString($lastName) || !$nonEmptyString($documentNumber)) {
                throw new HttpBadRequestException(
                    $request,
                    'У каждого пассажира обязательны firstName, lastName и documentNumber.'
                );
            }

            if ($dateOfBirth === null) {
                throw new HttpBadRequestException(
                    $request,
                    'У каждого пассажира обязательна корректная дата рождения dateOfBirth.'
                );
            }

            $passengers[] = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'dateOfBirth' => $dateOfBirth,
                'documentNumber' => $documentNumber,
            ];
        }

        $normalizedContact = ['email' => $contact['email'], 'phone' => $contact['phone']];

        $flight = (new FlightRepository(pdo()))->find((string) $flightId);

        if ($flight === null) {
            throw new HttpBadRequestException($request, 'Рейс с указанным flightId не найден.');
        }

        $booking = (new BookingRepository(pdo()))->create($flight, $normalizedContact, $passengers);

        $payload = BookingSerializer::serialize(
            $booking,
            FlightSerializer::serialize($flight),
            $passengers,
            $normalizedContact
        );

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(201);
    });

    return $app;
}