<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking;
use Hsc\GoogleCalendar\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function testReadsAllFieldsFromDescription(): void
    {
        $booking = Booking::from_event($this->event(
            "Name: Max Mustermann\nTeilnehmer: 4\nLeihausrüstung: 2\nE-Mail: max@example.com\nTelefon: +43 660 0000001\nAnmerkung: Erstes Mal",
            'Gebucht'
        ));

        self::assertSame('Max Mustermann', $booking->name);
        self::assertSame(4, $booking->participants);
        self::assertSame(2, $booking->rental);
        self::assertSame('max@example.com', $booking->email);
        self::assertSame('+43 660 0000001', $booking->phone);
        self::assertSame('Erstes Mal', $booking->note);
    }

    public function testParsesCalendarBlockFromNotificationMail(): void
    {
        $booking = Booking::from_event($this->event(
            "-- Eintrag für Kalender --\nName: Max Mustermann\nE-Mail: max@example.com\nTelefon: +43 660 0000001\nDatum: Sa, 17.01.2026 von 17:00 - 19:45 Uhr\nPersonen: 4\nAusrüstungen: 2",
            'Gebucht'
        ));

        self::assertSame('Max Mustermann', $booking->name);
        self::assertSame('max@example.com', $booking->email);
        self::assertSame('+43 660 0000001', $booking->phone);
        self::assertSame(4, $booking->participants);
        self::assertSame(2, $booking->rental);
    }

    public function testMissingNameFallsBackToTitleAndNumbersToZero(): void
    {
        $booking = Booking::from_event($this->event('', 'Gebucht: Familie Muster'));

        self::assertSame('Gebucht: Familie Muster', $booking->name);
        self::assertSame(0, $booking->participants);
        self::assertSame(0, $booking->rental);
        self::assertSame('', $booking->email);
    }

    /**
     * @param array{string, int, int} $expected Name, participants, rental.
     */
    #[DataProvider('descriptionProvider')]
    public function testToleratesGoogleHtmlAndSpelling(string $description, array $expected): void
    {
        $booking = Booking::from_event($this->event($description, 'Gebucht'));

        self::assertSame($expected, [$booking->name, $booking->participants, $booking->rental]);
    }

    /**
     * @return array<string, array{string, array{string, int, int}}>
     */
    public static function descriptionProvider(): array
    {
        return [
            'html line breaks' => ['Name: Erika<br>Teilnehmer: 2<br/>Leihausrüstung: 1', ['Erika', 2, 1]],
            'html tags and entities' => ['<b>Name:</b> M&amp;M<br>Teilnehmer: 3', ['M&M', 3, 0]],
            'upper case keys and umlaut spelling' => ["NAME: Muster\nTEILNEHMER: 5\nLeihausruestung: 2", ['Muster', 5, 2]],
            'number with text' => ["Name: A\nTeilnehmer: 6 Personen", ['A', 6, 0]],
            'unknown keys are ignored' => ["Preis: 20\nName: B", ['B', 0, 0]],
            'windows line endings' => ["Name: C\r\nTeilnehmer: 1\r\n", ['C', 1, 0]],
        ];
    }

    private function event(string $description, string $summary): Event
    {
        return new Event('id', $summary, $description, '2026-11-07T10:00:00+01:00', '2026-11-07T11:30:00+01:00', false);
    }
}
