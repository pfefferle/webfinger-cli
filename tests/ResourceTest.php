<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger\Tests;

use PHPUnit\Framework\TestCase;
use Pfefferle\WebFinger\Resource;

final class ResourceTest extends TestCase
{
    public function testBuildsFromJrdArray(): void
    {
        $resource = Resource::fromArray([
            'subject' => 'acct:alice@example.com',
            'aliases' => ['https://example.com/@alice'],
            'links' => [
                ['rel' => 'self', 'type' => 'application/activity+json', 'href' => 'https://example.com/users/alice'],
                ['rel' => 'http://ostatus.org/schema/1.0/subscribe', 'template' => 'https://example.com/authorize?uri={uri}'],
            ],
        ], 'https://example.com/.well-known/webfinger?resource=acct%3Aalice%40example.com', true);

        self::assertSame('acct:alice@example.com', $resource->subject);
        self::assertSame(['https://example.com/@alice'], $resource->aliases);
        self::assertTrue($resource->secure);
        self::assertCount(2, $resource->links);
        self::assertSame('self', $resource->links[0]->rel);
        self::assertSame('https://example.com/users/alice', $resource->links[0]->href);
        self::assertSame('application/activity+json', $resource->links[0]->type);
        self::assertNull($resource->links[1]->href);
        self::assertSame('https://example.com/authorize?uri={uri}', $resource->links[1]->template);
    }

    public function testMissingFieldsDefaultToEmpty(): void
    {
        $resource = Resource::fromArray(['subject' => 'acct:bob@example.org'], 'https://example.org/x', false);

        self::assertSame([], $resource->aliases);
        self::assertSame([], $resource->links);
        self::assertFalse($resource->secure);
    }

    public function testLinkTargetPrefersHrefOverTemplate(): void
    {
        $resource = Resource::fromArray(['links' => [
            ['rel' => 'a', 'href' => 'https://a.example', 'template' => 'https://t.example/{uri}'],
            ['rel' => 'b', 'template' => 'https://t.example/{uri}'],
            ['rel' => 'c'],
        ]], 'https://example.org/x', true);

        self::assertSame('https://a.example', $resource->links[0]->target());
        self::assertSame('https://t.example/{uri}', $resource->links[1]->target());
        self::assertNull($resource->links[2]->target());
    }

    public function testFindsLinksByRel(): void
    {
        $resource = Resource::fromArray(['links' => [
            ['rel' => 'self', 'href' => 'https://a.example/1'],
            ['rel' => 'other', 'href' => 'https://a.example/2'],
            ['rel' => 'self', 'href' => 'https://a.example/3'],
        ]], 'https://example.org/x', true);

        self::assertSame(
            ['https://a.example/1', 'https://a.example/3'],
            array_map(fn ($l) => $l->href, $resource->linksByRel('self'))
        );
    }

    public function testKeepsRawDocument(): void
    {
        $data = ['subject' => 'acct:bob@example.org', 'links' => [['rel' => 'self', 'href' => 'https://x']]];

        self::assertSame($data, Resource::fromArray($data, 'https://example.org/x', true)->raw);
    }
}
