<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventLabelsTest extends TestCase
{
    public function testLabelsOfTimedEvent(): void
    {
        $event = $this->event('2026-11-21T09:30:00+01:00', '2026-11-21T11:00:00+01:00', false);

        self::assertSame('SA, 21.11.', $event->day_label());
        self::assertSame('Samstag, 21.11.2026', $event->date_label());
        self::assertSame('09:30 – 11:00 Uhr', $event->time_label());
        self::assertSame('2026-11', $event->month_key());
    }

    public function testWhenLabelForTimedAndAllDayEvents(): void
    {
        self::assertSame('SA, 05.12. um 21:30 - 22:45 Uhr', $this->event('2026-12-05T21:30:00+01:00', '2026-12-05T22:45:00+01:00', false)->when_label());
        self::assertSame('SA, 21.11. um 10:00 Uhr', $this->event('2026-11-21T10:00:00+01:00', '2026-11-21T10:00:00+01:00', false)->when_label());
        self::assertSame('SO, 22.11., ganztägig', $this->event('2026-11-22', '2026-11-23', true)->when_label());
    }

    public function testSlotLabelForMails(): void
    {
        self::assertSame('Sa, 17.01.2026 von 17:00 - 19:45 Uhr', $this->event('2026-01-17T17:00:00+01:00', '2026-01-17T19:45:00+01:00', false)->slot_label());
        self::assertSame('Sa, 21.11.2026 um 10:00 Uhr', $this->event('2026-11-21T10:00:00+01:00', '2026-11-21T10:00:00+01:00', false)->slot_label());
        self::assertSame('So, 22.11.2026, ganztägig', $this->event('2026-11-22', '2026-11-23', true)->slot_label());
    }

    public function testDaysFromToday(): void
    {
        $event = $this->event('2026-11-21T23:30:00+01:00', '2026-11-22T00:30:00+01:00', false);

        self::assertSame(0, $event->days_from((int) strtotime('2026-11-21T00:10:00+01:00')));
        self::assertSame(2, $event->days_from((int) strtotime('2026-11-19T22:00:00+01:00')));
        self::assertSame(-1, $event->days_from((int) strtotime('2026-11-22T08:00:00+01:00')));
        self::assertNull($this->event('kaputt', 'kaputt', false)->days_from(0));
    }

    public function testAllDayEventHasNoClockTime(): void
    {
        self::assertSame('ganztägig', $this->event('2026-11-22', '2026-11-23', true)->time_label());
    }

    public function testEndEqualToStartShowsOnlyStart(): void
    {
        self::assertSame('10:00 Uhr', $this->event('2026-11-21T10:00:00+01:00', '2026-11-21T10:00:00+01:00', false)->time_label());
    }

    #[DataProvider('brokenStartProvider')]
    public function testUnparsableStartFallsBackToRawValue(string $start): void
    {
        $event = $this->event($start, $start, false);

        self::assertNull($event->month_key());
        self::assertSame($start, $event->day_label());
        self::assertSame($start, $event->date_label());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokenStartProvider(): array
    {
        return [
            'text' => ['not a date'],
            'invalid month' => ['2026-13-45'],
        ];
    }

    private function event(string $start, string $end, bool $allDay): Event
    {
        return new Event('id', 'Freie Eiszeit', '', $start, $end, $allDay);
    }
}
