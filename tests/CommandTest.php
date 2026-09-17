<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger\Tests;

use PHPUnit\Framework\TestCase;
use Pfefferle\WebFinger\Application;
use Pfefferle\WebFinger\Client;
use Pfefferle\WebFinger\Formatter;
use Pfefferle\WebFinger\WebFingerException;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CommandTest extends TestCase
{
    private const JRD = '{"subject":"acct:alice@example.com","aliases":["https://example.com/@alice"],"links":[{"rel":"self","type":"application/activity+json","href":"https://example.com/users/alice"}]}';

    /** @var list<string> */
    private array $requested = [];

    private function tester(callable $responder): ApplicationTester
    {
        $client = new Client(function (string $url) use ($responder): string {
            $this->requested[] = $url;

            return $responder($url);
        });
        $app = new Application($client, new Formatter(fn () => null));
        $app->setAutoExit(false);

        return new ApplicationTester($app);
    }

    public function testPrintsSubjectAliasesAndLinks(): void
    {
        $tester = $this->tester(fn () => self::JRD);

        $status = $tester->run(['resource' => 'alice@example.com']);
        $display = $tester->getDisplay();

        self::assertSame(0, $status, $display);
        self::assertStringContainsString('acct:alice@example.com', $display);
        self::assertStringContainsString('https://example.com/@alice', $display);
        self::assertStringContainsString('https://example.com/users/alice', $display);
        self::assertStringContainsString('https://example.com/.well-known/webfinger', $display);
    }

    public function testJsonOptionPrintsRawDocument(): void
    {
        $tester = $this->tester(fn () => self::JRD);

        $status = $tester->run(['resource' => 'alice@example.com', '--json' => true]);

        self::assertSame(0, $status);
        self::assertSame(json_decode(self::JRD, true), json_decode($tester->getDisplay(), true));
    }

    public function testInsecureOptionFallsBackToHttp(): void
    {
        $tester = $this->tester(function (string $url): string {
            if (str_starts_with($url, 'https://')) {
                throw new WebFingerException('nope');
            }

            return self::JRD;
        });

        $status = $tester->run(['resource' => 'alice@example.com', '--insecure' => true]);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringStartsWith('http://', $this->requested[1]);
        self::assertStringContainsString('insecure', strtolower($tester->getDisplay()));
    }

    public function testLookupFailurePrintsErrorAndFails(): void
    {
        $tester = $this->tester(function (): string {
            throw new WebFingerException('Request to https://example.com/x returned HTTP 404');
        });

        $status = $tester->run(['resource' => 'alice@example.com']);

        self::assertSame(1, $status);
        self::assertStringContainsString('HTTP 404', $tester->getDisplay());
    }

    public function testHelpOptionStillWorks(): void
    {
        $tester = $this->tester(fn () => self::JRD);

        $status = $tester->run(['--help' => true]);

        self::assertSame(0, $status);
        self::assertStringContainsString('--insecure', $tester->getDisplay());
        self::assertSame([], $this->requested);
    }
}
