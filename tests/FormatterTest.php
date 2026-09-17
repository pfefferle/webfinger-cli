<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger\Tests;

use PHPUnit\Framework\TestCase;
use Pfefferle\WebFinger\Formatter;
use Pfefferle\WebFinger\Resource;

final class FormatterTest extends TestCase
{
    public function testLinkRowsUseNicenamesAndTruncateLongTargets(): void
    {
        $long = 'https://example.com/' . str_repeat('a', 200);
        $resource = Resource::fromArray(['links' => [
            ['rel' => 'http://webfinger.net/rel/profile-page', 'href' => 'https://example.com/@alice'],
            ['rel' => 'http://ostatus.org/schema/1.0/subscribe', 'template' => 'https://example.com/authorize?uri={uri}'],
            ['rel' => 'http://webfinger.net/rel/avatar', 'href' => $long],
            ['rel' => 'nothing'],
        ]], 'https://example.com/x', true);

        $rows = (new Formatter(fn () => null))->linkRows($resource);

        self::assertSame(['Profile Page', 'https://example.com/@alice'], $rows[0]);
        self::assertSame(['Subscribe', 'https://example.com/authorize?uri={uri}'], $rows[1]);
        self::assertSame('Avatar', $rows[2][0]);
        self::assertSame(100, mb_strlen($rows[2][1]));
        self::assertStringEndsWith('...', $rows[2][1]);
        self::assertSame(['nothing', ''], $rows[3]);
    }

    public function testProfileRowsFromRepresentativeHCard(): void
    {
        $profileUrl = 'https://example.com/@alice';
        $mf2 = [
            'items' => [[
                'type' => ['h-card'],
                'properties' => [
                    'name' => ['Alice'],
                    'url' => [$profileUrl],
                    'note' => ['Hello', 'World'],
                    'photo' => [['value' => 'https://example.com/alice.jpg', 'alt' => 'Alice']],
                ],
            ]],
            'rels' => [],
            'rel-urls' => [],
        ];
        $fetched = [];
        $formatter = new Formatter(function (string $url) use (&$fetched, $mf2): ?array {
            $fetched[] = $url;

            return $mf2;
        });

        $resource = Resource::fromArray(['links' => [
            ['rel' => 'self', 'href' => 'https://example.com/users/alice'],
            ['rel' => 'http://webfinger.net/rel/profile-page', 'href' => $profileUrl],
        ]], 'https://example.com/x', true);

        $rows = $formatter->profileRows($resource);

        self::assertSame([$profileUrl], $fetched, 'only profile-page links are fetched');
        self::assertSame([
            ['Name:', 'Alice'],
            ['Url:', $profileUrl],
            ['Note:', "Hello\nWorld"],
            ['Photo:', 'https://example.com/alice.jpg'],
        ], $rows);
    }

    public function testProfileRowsAreEmptyWithoutHCard(): void
    {
        $formatter = new Formatter(fn () => ['items' => [], 'rels' => [], 'rel-urls' => []]);
        $resource = Resource::fromArray(['links' => [
            ['rel' => 'http://webfinger.net/rel/profile-page', 'href' => 'https://example.com/@alice'],
        ]], 'https://example.com/x', true);

        self::assertSame([], $formatter->profileRows($resource));
    }

    public function testProfileRowsSurviveFetchFailure(): void
    {
        $formatter = new Formatter(function (): ?array {
            throw new \RuntimeException('boom');
        });
        $resource = Resource::fromArray(['links' => [
            ['rel' => 'http://webfinger.net/rel/profile-page', 'href' => 'https://example.com/@alice'],
        ]], 'https://example.com/x', true);

        self::assertSame([], $formatter->profileRows($resource));
    }
}
