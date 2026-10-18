<?php

use Hexlet\Code\Repository\CityRepository;
use Hexlet\Code\Repository\FlightRepository;
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

    return $app;
}