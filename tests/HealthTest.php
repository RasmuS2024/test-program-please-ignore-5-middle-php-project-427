<?php

namespace Hexlet\Code\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HealthTest extends TestCase
{
    private function makeRequest(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest($method, $path)
            ->withHeader('Accept', 'application/json');
    }

    public function testHealthReturnsOk(): void
    {
        $app = createApp();
        $response = $app->handle($this->makeRequest('GET', '/api/health'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['status' => 'ok'], json_decode((string) $response->getBody(), true));
    }

    public function testUnknownApiPathReturns404Json(): void
    {
        $app = createApp();
        $response = $app->handle($this->makeRequest('GET', '/api/unknown-path'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('not_found', $body['code']);
        self::assertNotEmpty($body['message']);
    }

    public function testApiRootWithoutSlashReturns404Json(): void
    {
        $app = createApp();
        $response = $app->handle($this->makeRequest('GET', '/api'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('not_found', json_decode((string) $response->getBody(), true)['code']);
    }

    public function testMethodNotAllowedReturns405Json(): void
    {
        $app = createApp();
        $response = $app->handle($this->makeRequest('POST', '/api/cities'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('method_not_allowed', json_decode((string) $response->getBody(), true)['code']);
    }
}
