<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger;

/**
 * Turns a Resource into rows for Symfony's Table helper.
 */
final class Formatter
{
    private const MAX_LINK_WIDTH = 100;

    private const PROFILE_RELS = [
        'http://microformats.org/profile/hcard',
        'http://webfinger.net/rel/profile-page',
    ];

    private const REL_NAMES = [
        'http://webfinger.net/rel/profile-page' => 'Profile Page',
        'http://webfinger.net/rel/avatar' => 'Avatar',
        'http://ostatus.org/schema/1.0/subscribe' => 'Subscribe',
        'http://schemas.google.com/g/2010#updates-from' => 'Feed',
    ];

    /** @var \Closure(string): ?array */
    private \Closure $mf2Fetcher;

    /**
     * @param null|callable(string $url): ?array $mf2Fetcher returns a parsed microformats2 document
     */
    public function __construct(?callable $mf2Fetcher = null)
    {
        $this->mf2Fetcher = $mf2Fetcher
            ? \Closure::fromCallable($mf2Fetcher)
            : static fn (string $url): ?array => \Mf2\fetch($url) ?: null;
    }

    public static function relName(string $rel): string
    {
        return self::REL_NAMES[$rel] ?? $rel;
    }

    /**
     * @return list<array{string, string}>
     */
    public function linkRows(Resource $resource): array
    {
        return array_map(
            static fn (Link $link): array => [
                self::relName($link->rel),
                mb_strimwidth($link->target() ?? '', 0, self::MAX_LINK_WIDTH, '...'),
            ],
            $resource->links
        );
    }

    /**
     * Rows of the representative h-card found on the profile page, if any.
     *
     * @return list<array{string, string}>
     */
    public function profileRows(Resource $resource): array
    {
        $hCard = $this->representativeHCard($resource);
        if ($hCard === null) {
            return [];
        }

        $rows = [];
        foreach ($hCard['properties'] ?? [] as $key => $values) {
            $value = self::flattenProperty($values);
            if ($value === null) {
                continue;
            }

            $rows[] = [ucfirst((string) $key) . ':', $value];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function representativeHCard(Resource $resource): ?array
    {
        foreach ($resource->links as $link) {
            if ($link->href === null || !\in_array($link->rel, self::PROFILE_RELS, true)) {
                continue;
            }

            try {
                $mf2 = ($this->mf2Fetcher)($link->href);
            } catch (\Throwable) {
                continue;
            }

            if ($mf2 && ($hCard = \BarnabyWalters\Mf2\getRepresentativeHCard($mf2, $link->href))) {
                return $hCard;
            }
        }

        return null;
    }

    /**
     * Collapses an mf2 property value list into a single printable string.
     */
    private static function flattenProperty(mixed $values): ?string
    {
        if (!\is_array($values)) {
            return null;
        }

        $strings = [];
        foreach ($values as $value) {
            if (\is_array($value)) {
                $value = $value['value'] ?? ($value['properties']['name'][0] ?? null);
            }
            if (\is_scalar($value)) {
                $strings[] = (string) $value;
            }
        }

        return $strings ? implode(PHP_EOL, $strings) : null;
    }
}
