<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger;

/**
 * Minimal WebFinger client (RFC 7033): fetches the JRD for a resource.
 */
final class Client
{
    private const ENDPOINT = '/.well-known/webfinger';

    /** @var \Closure(string): string */
    private \Closure $fetcher;

    /**
     * @param null|callable(string $url): string $fetcher returns the response body or throws WebFingerException
     */
    public function __construct(?callable $fetcher = null, private readonly int $timeout = 10)
    {
        $this->fetcher = $fetcher ? \Closure::fromCallable($fetcher) : $this->defaultFetcher(...);
    }

    /**
     * @throws WebFingerException
     */
    public function finger(string $resource, bool $fallbackToHttp = false): Resource
    {
        $resource = self::normalizeResource($resource);
        $host = self::hostOf($resource);
        $query = '?resource=' . rawurlencode($resource);

        $url = 'https://' . $host . self::ENDPOINT . $query;

        try {
            return $this->fetch($url, true);
        } catch (WebFingerException $e) {
            if (!$fallbackToHttp) {
                throw $e;
            }
        }

        return $this->fetch('http://' . $host . self::ENDPOINT . $query, false);
    }

    private function fetch(string $url, bool $secure): Resource
    {
        $body = ($this->fetcher)($url);

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebFingerException("Response from {$url} is not valid JSON: {$e->getMessage()}", 0, $e);
        }

        if (!\is_array($data)) {
            throw new WebFingerException("Response from {$url} is not a JSON object");
        }

        return Resource::fromArray($data, $url, $secure);
    }

    /**
     * Accepts "user@host", "@user@host", "acct:user@host" or any URI.
     */
    public static function normalizeResource(string $resource): string
    {
        $resource = trim($resource);

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $resource)) {
            return $resource;
        }

        return 'acct:' . ltrim($resource, '@');
    }

    /**
     * @throws WebFingerException
     */
    public static function hostOf(string $resource): string
    {
        if (str_starts_with($resource, 'acct:')) {
            $at = strrpos($resource, '@');
            $host = $at === false ? '' : substr($resource, $at + 1);
        } else {
            $host = (string) parse_url($resource, PHP_URL_HOST);
            if ($host !== '' && ($port = parse_url($resource, PHP_URL_PORT)) !== null) {
                $host .= ':' . $port;
            }
        }

        if ($host === '') {
            throw new WebFingerException("Cannot determine host of \"{$resource}\"; expected user@host or a URL");
        }

        return $host;
    }

    private function defaultFetcher(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Accept: application/jrd+json, application/json;q=0.9'],
            CURLOPT_USERAGENT => 'webfinger-cli (+https://github.com/pfefferle/webfinger-cli)',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new WebFingerException("Request to {$url} failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new WebFingerException("Request to {$url} returned HTTP {$status}");
        }

        return (string) $body;
    }
}
