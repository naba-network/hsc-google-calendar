<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Calendar_Block;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarBlockTest extends TestCase
{
    public function testParsesTheWholeBlock(): void
    {
        $text = "-- Eintrag für Kalender --\nName: Andreas Dreier\nE-Mail: andreas@example.com\nTelefon: +43 660 0000001\nDatum: Sa, 28.11.2026 von 20:00 - 21:15 Uhr\nPersonen: 18\nAusrüstungen: 18\n";

        self::assertSame(
            [
                'name' => 'Andreas Dreier',
                'email' => 'andreas@example.com',
                'phone' => '+43 660 0000001',
                'start' => '2026-11-28T20:00',
                'end' => '2026-11-28T21:15',
                'participants' => '18',
                'rental' => '18',
            ],
            Calendar_Block::parse($text)
        );
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function slotProvider(): array
    {
        return [
            'start only' => ['Datum: Sa, 17.01.2026 um 17:00 Uhr', ['start' => '2026-01-17T17:00']],
            'all day starts at midnight' => ['Datum: Sa, 17.01.2026, ganztägig', ['start' => '2026-01-17T00:00']],
            'en dash' => ['Datum: Sa, 5.2.2026 von 9:30 – 11:00 Uhr', ['start' => '2026-02-05T09:30', 'end' => '2026-02-05T11:00']],
            'no date at all' => ['Datum: bald', []],
            'unknown and empty lines are skipped' => ["Foo: bar\nName:\nkein Doppelpunkt", []],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('slotProvider')]
    public function testParsesSlotsAndSkipsUnusableLines(string $text, array $expected): void
    {
        self::assertSame($expected, Calendar_Block::parse($text));
    }
}
