<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Date_Range;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function testConfiguredRangeIncludesTheLastDay(): void
    {
        $range = new Date_Range('2026-10-01', '2027-10-01', new DateTimeZone('Europe/Vienna'));

        // 2026-10-01 00:00 Vienna (CEST, +02:00) and 2027-10-02 00:00 Vienna (CEST).
        self::assertSame(strtotime('2026-09-30T22:00:00Z'), $range->time_min(strtotime('2026-10-07T12:00:00Z')));
        self::assertSame(strtotime('2027-10-01T22:00:00Z'), $range->time_max());
    }

    public function testEmptyStartMeansBeginningOfCurrentMonthInSiteZone(): void
    {
        $range = new Date_Range('', '', new DateTimeZone('Europe/Vienna'));

        // 2026-10-31 23:30 UTC is already 2026-11-01 in Vienna.
        self::assertSame(strtotime('2026-10-31T23:00:00Z'), $range->time_min(strtotime('2026-10-31T23:30:00Z')));
        self::assertNull($range->time_max());
    }

    #[DataProvider('invalidDayProvider')]
    public function testInvalidDaysAreTreatedAsEmpty(string $day): void
    {
        $range = new Date_Range($day, $day, new DateTimeZone('UTC'));

        self::assertFalse(Date_Range::is_valid_day($day));
        self::assertNull($range->time_max());
        self::assertSame(strtotime('2026-10-01T00:00:00Z'), $range->time_min(strtotime('2026-10-07T12:00:00Z')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDayProvider(): array
    {
        return [
            'german format' => ['01.10.2026'],
            'impossible day' => ['2026-02-30'],
            'text' => ['soon'],
            'empty' => [''],
            'no zero padding' => ['2026-1-1'],
        ];
    }

    public function testKeyDiffersPerRange(): void
    {
        $zone = new DateTimeZone('UTC');

        self::assertNotSame((new Date_Range('2026-10-01', '', $zone))->key(), (new Date_Range('2026-10-02', '', $zone))->key());
    }
}
