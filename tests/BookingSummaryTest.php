<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking;
use Hsc\GoogleCalendar\Booking_Summary;
use Hsc\GoogleCalendar\Event;
use PHPUnit\Framework\TestCase;

final class BookingSummaryTest extends TestCase
{
    public function testGroupsByMonthSortedByStart(): void
    {
        $summary = new Booking_Summary([
            $this->booking('2026-12-05T10:00:00+01:00', 2, 1),
            $this->booking('2026-11-21T10:00:00+01:00', 4, 2),
            $this->booking('2026-11-07T10:00:00+01:00', 3, 0),
        ]);

        $months = $summary->by_month();

        self::assertSame(['2026-11', '2026-12'], array_keys($months));
        self::assertSame(['2026-11-07', '2026-11-21'], array_map(
            static fn (Booking $b): string => substr($b->event->start, 0, 10),
            $months['2026-11']
        ));
    }

    public function testNextIsFirstBookingFromTodayOn(): void
    {
        $summary = new Booking_Summary([
            $this->booking('2026-11-21T10:00:00+01:00', 4, 2),
            $this->booking('2026-11-07T10:00:00+01:00', 3, 0),
            $this->booking('2026-11-14T18:00:00+01:00', 1, 0),
        ]);

        // Today, later in the evening: the booking of today still counts.
        $today = (int) strtotime('2026-11-14T21:30:00+01:00');
        self::assertSame('2026-11-14T18:00:00+01:00', $summary->next($today)?->event->start);
        // Day before: the same booking is upcoming.
        self::assertSame('2026-11-14T18:00:00+01:00', $summary->next($today - 86400)?->event->start);
        // Day after: skips to the next one.
        self::assertSame('2026-11-21T10:00:00+01:00', $summary->next($today + 86400)?->event->start);
        // Everything in the past.
        self::assertNull($summary->next((int) strtotime('2026-12-01T10:00:00+01:00')));
        self::assertNull((new Booking_Summary([]))->next($today));
    }

    public function testTotals(): void
    {
        $summary = new Booking_Summary([
            $this->booking('2026-11-07T10:00:00+01:00', 4, 2),
            $this->booking('2026-12-05T10:00:00+01:00', 3, 1),
        ]);

        self::assertSame(['slots' => 2, 'bookings' => 2, 'participants' => 7, 'rental' => 3], $summary->totals());
        self::assertSame(
            ['bookings' => 1, 'participants' => 4, 'rental' => 2],
            Booking_Summary::totals_of($summary->by_month()['2026-11'])
        );
    }

    public function testBookingsWithoutValidStartAreSkipped(): void
    {
        $summary = new Booking_Summary([$this->booking('broken', 5, 5)]);

        self::assertTrue($summary->is_empty());
        self::assertSame(['slots' => 0, 'bookings' => 0, 'participants' => 0, 'rental' => 0], $summary->totals());
    }

    public function testSlotsAreFreeSlotsPlusBookingsPerMonth(): void
    {
        $summary = new Booking_Summary(
            [
                $this->booking('2026-11-07T10:00:00+01:00', 4, 2),
                $this->booking('2026-11-21T10:00:00+01:00', 3, 1),
            ],
            [
                $this->free('2026-11-14T10:00:00+01:00'),
                $this->free('2026-11-28T10:00:00+01:00'),
                $this->free('2026-12-05T10:00:00+01:00'),
                $this->free('broken'),
            ]
        );

        self::assertSame(
            [
                '2026-11' => ['slots' => 4, 'bookings' => 2, 'participants' => 7, 'rental' => 3],
                '2026-12' => ['slots' => 1, 'bookings' => 0, 'participants' => 0, 'rental' => 0],
            ],
            $summary->rows()
        );
        self::assertSame(['slots' => 5, 'bookings' => 2, 'participants' => 7, 'rental' => 3], $summary->totals());
        self::assertFalse($summary->is_empty());
        self::assertSame(['2026-11'], array_keys($summary->by_month()));
    }

    public function testEmptySummary(): void
    {
        $summary = new Booking_Summary([]);

        self::assertTrue($summary->is_empty());
        self::assertSame([], $summary->by_month());
    }

    private function free(string $start): Event
    {
        return new Event('free', 'Freie Eiszeit', '', $start, $start, false);
    }

    private function booking(string $start, int $participants, int $rental): Booking
    {
        return new Booking(
            new Event('id', 'Gebucht', '', $start, $start, false),
            'Max Mustermann',
            $participants,
            $rental,
            'max@example.com',
            '+43 660 0000001',
            ''
        );
    }
}
