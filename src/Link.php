<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger;

/**
 * A single JRD link object (RFC 7033, section 4.4.4).
 */
final class Link
{
    /**
     * @param array<string, string>      $titles     language => title
     * @param array<string, string|null> $properties uri => value
     */
    public function __construct(
        public readonly string $rel,
        public readonly ?string $href = null,
        public readonly ?string $template = null,
        public readonly ?string $type = null,
        public readonly array $titles = [],
        public readonly array $properties = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rel: (string) ($data['rel'] ?? ''),
            href: isset($data['href']) ? (string) $data['href'] : null,
            template: isset($data['template']) ? (string) $data['template'] : null,
            type: isset($data['type']) ? (string) $data['type'] : null,
            titles: \is_array($data['titles'] ?? null) ? $data['titles'] : [],
            properties: \is_array($data['properties'] ?? null) ? $data['properties'] : [],
        );
    }

    /**
     * The URL this link points to: href, or the template for templated links.
     */
    public function target(): ?string
    {
        return $this->href ?? $this->template;
    }
}
