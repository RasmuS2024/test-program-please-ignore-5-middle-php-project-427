<?php

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CitiesTest extends TestCase
{
    public function testCitiesAreServedFromDatabaseInRequiredOrder(): void
    {
        $app = createApp();

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/cities')
            ->withHeader('Accept', 'application/json');

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $cities = json_decode((string) $response->getBody(), true);

        self::assertIsArray($cities);
        self::assertCount(7, $cities);

        self::assertSame('MOW', $cities[0]['code']);
        self::assertSame('Москва', $cities[0]['name']);
        self::assertSame('LED', $cities[1]['code']);
        self::assertSame('Санкт-Петербург', $cities[1]['name']);

        foreach ($cities as $city) {
            self::assertArrayHasKey('code', $city);
            self::assertArrayHasKey('name', $city);
            self::assertArrayHasKey('country', $city);
            self::assertNotSame('', $city['code']);
            self::assertNotSame('', $city['name']);
        }
    }
}