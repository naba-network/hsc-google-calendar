<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Calendar_Client;
use Hsc\GoogleCalendar\Connection_Tester;
use PHPUnit\Framework\TestCase;

final class CalendarClientTest extends TestCase
{
    private const CALENDAR_ID = 'calendar-id@example.test';

    /** @var list<array{method: string, url: string, headers: array<string, string>}> */
    private array $requests = [];

    public function testFollowsPaginationAndSkipsCancelledEvents(): void
    {
        $client = $this->client([
            ['status' => 200, 'body' => json_encode([
                'items' => [
                    ['id' => '1', 'summary' => 'Eiszeit', 'start' => ['dateTime' => '2026-10-08T18:00:00+02:00'], 'end' => ['dateTime' => '2026-10-08T19:00:00+02:00']],
                    ['id' => '2', 'status' => 'cancelled', 'summary' => 'Weg', 'start' => ['dateTime' => '2026-10-09T18:00:00+02:00']],
                ],
                'nextPageToken' => 'next-1',
            ])],
            ['status' => 200, 'body' => json_encode([
                'items' => [['id' => '3', 'summary' => 'Fest', 'start' => ['date' => '2026-10-10'], 'end' => ['date' => '2026-10-11']]],
            ])],
        ]);

        $events = $client->list_events(self::CALENDAR_ID, null, 'key-1', 1700000000, 1700000000);

        self::assertCount(2, $events);
        self::assertSame('Eiszeit', $events[0]->summary);
        self::assertFalse($events[0]->all_day);
        self::assertTrue($events[1]->all_day);
        self::assertCount(2, $this->requests);
        self::assertStringContainsString('/calendars/calendar-id%40example.test/events?', $this->requests[0]['url']);
        self::assertStringContainsString('key=key-1', $this->requests[0]['url']);
        self::assertStringContainsString('singleEvents=true', $this->requests[0]['url']);
        self::assertStringContainsString('timeMin=2023-11-14T22%3A13%3A20Z', $this->requests[0]['url']);
        self::assertStringNotContainsString('pageToken', $this->requests[0]['url']);
        self::assertStringContainsString('pageToken=next-1', $this->requests[1]['url']);
    }

    public function testTimeMaxIsOnlySentWhenGiven(): void
    {
        $client = $this->client([
            ['status' => 200, 'body' => '{"items":[]}'],
            ['status' => 200, 'body' => '{"items":[]}'],
        ]);

        $client->list_events(self::CALENDAR_ID, null, 'key-1', 1700000000, 1700000000);
        $client->list_events(self::CALENDAR_ID, null, 'key-1', 1700000000, 1700000000, 1800000000);

        self::assertStringNotContainsString('timeMax', $this->requests[0]['url']);
        self::assertStringContainsString('timeMax=2027-01-15T08%3A00%3A00Z', $this->requests[1]['url']);
    }

    public function testFailureThrowsGoogleMessage(): void
    {
        $client = $this->client([['status' => 404, 'body' => '{"error":{"message":"Not Found"}}']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not Found (HTTP 404)');

        $client->list_events(self::CALENDAR_ID, null, 'key-1', 0, 0);
    }

    public function testNoAuthThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->client([])->list_events(self::CALENDAR_ID, null, '', 0, 0);
    }

    /**
     * @param list<array{status: int, body: string}> $responses
     */
    private function client(array $responses): Calendar_Client
    {
        $this->requests = [];
        $send = function (string $method, string $url, array $headers, string $body) use (&$responses): array {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers];

            return array_shift($responses) ?? ['status' => 500, 'body' => ''];
        };

        return new Calendar_Client($send, new Connection_Tester($send));
    }
}
