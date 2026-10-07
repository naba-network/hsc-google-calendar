<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Event;
use Hsc\GoogleCalendar\Event_Filter;
use PHPUnit\Framework\TestCase;

final class EventFilterTest extends TestCase
{
    public function testEmptyPatternKeepsEverything(): void
    {
        $events = [$this->event('Training'), $this->event('Spiel')];

        self::assertCount(2, Event_Filter::from_pattern('  ')->apply($events));
    }

    public function testMatchesTitleCaseInsensitive(): void
    {
        $result = Event_Filter::from_pattern('^eis(zeit|lauf)')->apply([
            $this->event('Eiszeit Kinder'),
            $this->event('Spiel'),
            $this->event('Heute Eiszeit'),
        ]);

        self::assertCount(1, $result);
        self::assertSame('Eiszeit Kinder', $result[0]->summary);
    }

    public function testMatchesDescription(): void
    {
        $result = Event_Filter::from_pattern('frei')->apply([$this->event('Eiszeit', 'Platz frei')]);

        self::assertCount(1, $result);
    }

    public function testPatternMayContainDelimiterCharacter(): void
    {
        self::assertTrue(Event_Filter::from_pattern('a~b')->matches($this->event('a~b')));
    }

    public function testInvalidPatternThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event_Filter::from_pattern('(unclosed');
    }

    private function event(string $summary, string $description = ''): Event
    {
        return new Event('id', $summary, $description, '2026-10-08T18:00:00+02:00', '2026-10-08T19:00:00+02:00', false);
    }
}
