<?php

declare(strict_types=1);

use Hsc\Release\Bumper;
use PHPUnit\Framework\TestCase;

final class BumperTest extends TestCase
{
    public function testBumpDetection(): void
    {
        self::assertSame('patch', Bumper::determineBump(['fix: a', 'docs: b']));
        self::assertSame('minor', Bumper::determineBump(['fix: a', 'feat(ui): b']));
        self::assertSame('major', Bumper::determineBump(['feat: a', 'refactor!: b']));
        self::assertSame('major', Bumper::determineBump(['feat: a BREAKING CHANGE']));
    }

    public function testNextVersion(): void
    {
        self::assertSame('1.0.0', Bumper::nextVersion('0.9.9', 'major'));
        self::assertSame('0.2.0', Bumper::nextVersion('0.1.5', 'minor'));
        self::assertSame('0.1.6', Bumper::nextVersion('0.1.5', 'patch'));
    }

    public function testInvalidVersionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Bumper::nextVersion('1.0', 'patch');
    }

    public function testChangelogSectionGroupsEntries(): void
    {
        $out = Bumper::changelogSection('0.2.0', '2026-01-01', ['feat: add slots', 'fix(api): crash', 'chore: deps']);

        self::assertStringContainsString("## [0.2.0] - 2026-01-01\n", $out);
        self::assertStringContainsString("### Added\n\n- add slots\n", $out);
        self::assertStringContainsString("### Fixed\n\n- crash\n", $out);
        self::assertStringContainsString("### Changed\n\n- deps\n", $out);
    }
}
