<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger\Tests;

use PHPUnit\Framework\TestCase;
use Pfefferle\WebFinger\Client;
use Pfefferle\WebFinger\WebFingerException;

final class ClientTest extends TestCase
{
    /** @var list<string> */
    private array $requested = [];

    private function client(callable $responder): Client
    {
        return new Client(function (string $url) use ($responder): string {
            $this->requested[] = $url;

            return $responder($url);
        });
    }

    public function testQueriesWellKnownEndpointOnResourceHost(): void
    {
        $client = $this->client(fn () => '{"subject":"acct:alice@example.com"}');

        $resource = $client->finger('acct:alice@example.com');

        self::assertSame(
            ['https://example.com/.well-known/webfinger?resource=acct%3Aalice%40example.com'],
            $this->requested
        );
        self::assertSame('acct:alice@example.com', $resource->subject);
        self::assertSame($this->requested[0], $resource->url);
        self::assertTrue($resource->secure);
    }

    public function testBareUserAtHostIsTreatedAsAcct(): void
    {
        $this->client(fn () => '{}')->finger('alice@example.com');

        self::assertSame(
            ['https://example.com/.well-known/webfinger?resource=acct%3Aalice%40example.com'],
            $this->requested
        );
    }

    public function testStripsLeadingAtSign(): void
    {
        $this->client(fn () => '{}')->finger('@alice@example.com');

        self::assertStringContainsString('resource=acct%3Aalice%40example.com', $this->requested[0]);
    }

    public function testUrlResourceUsesItsOwnHost(): void
    {
        $this->client(fn () => '{}')->finger('https://example.org/@alice');

        self::assertSame(
            ['https://example.org/.well-known/webfinger?resource=https%3A%2F%2Fexample.org%2F%40alice'],
            $this->requested
        );
    }

    public function testFallsBackToHttpWhenAllowed(): void
    {
        $client = $this->client(function (string $url): string {
            if (str_starts_with($url, 'https://')) {
                throw new WebFingerException('TLS handshake failed');
            }

            return '{"subject":"acct:alice@example.com"}';
        });

        $resource = $client->finger('acct:alice@example.com', fallbackToHttp: true);

        self::assertCount(2, $this->requested);
        self::assertStringStartsWith('http://example.com/', $this->requested[1]);
        self::assertFalse($resource->secure);
        self::assertSame($this->requested[1], $resource->url);
    }

    public function testDoesNotFallBackByDefault(): void
    {
        $client = $this->client(function (): string {
            throw new WebFingerException('TLS handshake failed');
        });

        $this->expectException(WebFingerException::class);
        $this->expectExceptionMessage('TLS handshake failed');

        try {
            $client->finger('acct:alice@example.com');
        } finally {
            self::assertCount(1, $this->requested);
        }
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(WebFingerException::class);
        $this->expectExceptionMessageMatches('/JSON/');

        $this->client(fn () => '<html>')->finger('acct:alice@example.com');
    }

    public function testRejectsResourceWithoutHost(): void
    {
        $this->expectException(WebFingerException::class);

        $this->client(fn () => '{}')->finger('alice');
    }
}
