<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger;

/**
 * A JSON Resource Descriptor (JRD) as returned by a WebFinger endpoint.
 */
final class Resource
{
    /**
     * @param list<string>                $aliases
     * @param list<Link>                  $links
     * @param array<string, string|null>  $properties
     * @param string                      $url    the endpoint URL the descriptor was fetched from
     * @param bool                        $secure whether it was fetched over HTTPS
     * @param array<string, mixed>        $raw    the decoded JRD document as received
     */
    public function __construct(
        public readonly ?string $subject,
        public readonly array $aliases,
        public readonly array $links,
        public readonly array $properties,
        public readonly string $url,
        public readonly bool $secure,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data decoded JRD document
     */
    public static function fromArray(array $data, string $url, bool $secure): self
    {
        $links = [];
        foreach (($data['links'] ?? []) as $link) {
            if (\is_array($link)) {
                $links[] = Link::fromArray($link);
            }
        }

        $aliases = array_values(array_filter(
            \is_array($data['aliases'] ?? null) ? $data['aliases'] : [],
            'is_string'
        ));

        return new self(
            subject: isset($data['subject']) ? (string) $data['subject'] : null,
            aliases: $aliases,
            links: $links,
            properties: \is_array($data['properties'] ?? null) ? $data['properties'] : [],
            url: $url,
            secure: $secure,
            raw: $data,
        );
    }

    /**
     * @return list<Link>
     */
    public function linksByRel(string $rel): array
    {
        return array_values(array_filter($this->links, fn (Link $link) => $link->rel === $rel));
    }
}
