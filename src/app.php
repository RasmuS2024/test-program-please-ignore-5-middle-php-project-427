<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpException;
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
        $response->getBody()->write(json_encode([], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    });

    return $app;
}