<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Updater;
use PHPUnit\Framework\TestCase;

final class UpdaterTest extends TestCase
{
    public function testParsesReleaseWithZipAsset(): void
    {
        $parsed = Updater::parse_release([
            'tag_name' => 'v1.2.3',
            'html_url' => 'https://example.test/r',
            'body' => 'notes',
            'assets' => [
                ['name' => 'other.txt', 'browser_download_url' => 'https://example.test/other'],
                ['name' => Updater::ASSET_NAME, 'browser_download_url' => 'https://example.test/zip'],
            ],
        ]);

        self::assertSame(
            ['version' => '1.2.3', 'package' => 'https://example.test/zip', 'url' => 'https://example.test/r', 'notes' => 'notes'],
            $parsed
        );
    }

    public function testRejectsReleaseWithoutZipAsset(): void
    {
        self::assertNull(Updater::parse_release(['tag_name' => 'v1.0.0', 'assets' => []]));
    }

    public function testRejectsInvalidTagAndNull(): void
    {
        self::assertNull(Updater::parse_release(null));
        self::assertNull(Updater::parse_release([
            'tag_name' => 'nightly',
            'assets' => [['name' => Updater::ASSET_NAME, 'browser_download_url' => 'x']],
        ]));
    }
}
