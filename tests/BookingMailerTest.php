<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking_Mailer;
use Hsc\GoogleCalendar\Booking_Request;
use Hsc\GoogleCalendar\Event;
use Hsc\GoogleCalendar\Mail_Message;
use PHPUnit\Framework\TestCase;

final class BookingMailerTest extends TestCase
{
    /** @var list<array{to: string, subject: string, body: string, alt_body: string, headers: list<string>}> */
    private array $sent = [];

    public function testSendsNotificationAndConfirmation(): void
    {
        self::assertTrue($this->mailer(true)->send($this->request(), $this->event()));

        self::assertCount(2, $this->sent);
        [$club, $visitor] = $this->sent;

        self::assertSame('club@example.test', $club['to']);
        self::assertSame('Verleih Anfrage - Max Mustermann - Sa, 21.11.2026 von 09:30 - 11:00 Uhr', $club['subject']);
        self::assertContains('From: SC Test - Verleih <verleih@example.test>', $club['headers']);
        self::assertContains('Reply-To: Max Mustermann <max@example.com>', $club['headers']);
        self::assertContains('Content-Type: text/html; charset=UTF-8', $club['headers']);
        self::assertStringContainsString("-- Eintrag für Kalender --\nName: Max Mustermann\nE-Mail: max@example.com\nTelefon: +43 660 0000001\nDatum: Sa, 21.11.2026 von 09:30 - 11:00 Uhr\nPersonen: 4\nAusrüstungen: 2\n", $club['alt_body']);
        self::assertSame(
            "Verleih Anfrage\n\n-- Eintrag für Kalender --\nName: Max Mustermann\nE-Mail: max@example.com\nTelefon: +43 660 0000001\nDatum: Sa, 21.11.2026 von 09:30 - 11:00 Uhr\nPersonen: 4\nAusrüstungen: 2\n\n-- E-Mail: --\nDatum: \nMax Mustermann - Sa, 21.11.2026 von 09:30 - 11:00 Uhr\nDatum: \nSa, 21.11.2026 von 09:30 - 11:00 Uhr\nAnzahl: \n4 Personen / 2 Ausrüstungen\nAnsprechperson: \nMax Mustermann - +43 660 0000001\n\nNachricht:\nHallo",
            $club['alt_body']
        );

        self::assertSame('max@example.com', $visitor['to']);
        self::assertContains('From: SC Test - Verleih <verleih@example.test>', $visitor['headers']);
        self::assertStringNotContainsString('Reply-To', implode("\n", $visitor['headers']));
        self::assertStringContainsString('Hallo Max Mustermann,', $visitor['body']);
    }

    public function testHtmlBodyUsesCardLayoutAndBrand(): void
    {
        $this->mailer(true)->send($this->request(), $this->event());

        foreach ($this->sent as $mail) {
            self::assertStringStartsWith('<!DOCTYPE html>', $mail['body']);
            self::assertStringContainsString('background-color:#F4F4F4', $mail['body']);
            self::assertStringContainsString('background-color:#FFFFFF', $mail['body']);
            self::assertStringContainsString('padding:24px', $mail['body']);
            self::assertStringContainsString('SC Samina Hohenems - Verleih', $mail['body']);
            self::assertStringContainsString('<a href="https://sc-hohenems.at"', $mail['body']);
            self::assertStringNotContainsString('©', $mail['body']);
            self::assertNotSame('', $mail['alt_body']);
        }
        self::assertStringContainsString('<div>Name: Max Mustermann</div>', $this->sent[0]['body']);
        self::assertStringContainsString('<div>Ausrüstungen: 2</div>', $this->sent[0]['body']);
    }

    public function testHtmlBodyEscapesUserInput(): void
    {
        $request = Booking_Request::from_array([
            'name' => '<script>alert(1)</script>',
            'email' => 'max@example.com',
            'phone' => '+43 660 0000001',
            'participants' => '1',
            'rental' => '0',
            'message' => "<b>fett</b>\nZeile 2",
        ]);

        $this->mailer(true)->send($request, $this->event());

        foreach ($this->sent as $mail) {
            self::assertStringNotContainsString('<script>', $mail['body']);
            self::assertStringNotContainsString('<b>fett', $mail['body']);
        }
        self::assertStringContainsString("&lt;b&gt;fett&lt;/b&gt;<br>\nZeile 2", $this->sent[0]['body']);
    }

    public function testFailedNotificationSkipsConfirmation(): void
    {
        self::assertFalse($this->mailer(false)->send($this->request(), $this->event()));

        self::assertCount(1, $this->sent);
    }

    public function testNameCannotInjectHeaders(): void
    {
        $request = Booking_Request::from_array([
            'name' => "Evil\r\nBcc: spam@example.com <x>",
            'email' => 'max@example.com',
            'phone' => '+43 660 0000001',
            'participants' => '1',
            'rental' => '0',
        ]);

        $this->mailer(true)->send($request, $this->event());

        foreach ($this->sent[0]['headers'] as $header) {
            self::assertStringNotContainsString("\n", $header);
            self::assertStringNotContainsString("\r", $header);
        }
        self::assertContains('Reply-To: Evil Bcc: spam@example.com x <max@example.com>', $this->sent[0]['headers']);
    }

    public function testMessageStaysOutOfCalendarBlock(): void
    {
        $request = Booking_Request::from_array([
            'name' => 'Max',
            'email' => 'max@example.com',
            'phone' => '+43 660 0000001',
            'participants' => '1',
            'rental' => '0',
            'message' => "Zeile 1\nName: Fake",
        ]);

        $this->mailer(true)->send($request, $this->event());

        [$calendarBlock] = explode('-- E-Mail: --', $this->sent[0]['alt_body']);
        self::assertStringNotContainsString('Fake', $calendarBlock);
        self::assertStringContainsString("Nachricht:\nZeile 1\nName: Fake", $this->sent[0]['alt_body']);
    }

    public function testBuildDoesNotSendAndRedirectedKeepsContent(): void
    {
        $messages = $this->mailer(true)->build($this->request(), $this->event());

        self::assertSame([], $this->sent);

        $test = $messages['notification']->redirected('admin@example.test', '[TEST] ');

        self::assertSame('admin@example.test', $test->to);
        self::assertSame('[TEST] ' . $messages['notification']->subject, $test->subject);
        self::assertSame($messages['notification']->body, $test->body);
        self::assertSame($messages['notification']->headers, $test->headers);
    }

    public function testTestSendDeliversBothMailsToTheTestAddressOnly(): void
    {
        $results = $this->mailer(true)->send_test($this->request(), $this->event(), 'admin@example.test');

        self::assertCount(2, $this->sent);
        self::assertSame(['admin@example.test', 'admin@example.test'], array_column($this->sent, 'to'));
        self::assertSame([null, null], array_column($results, 'error'));
        self::assertSame(
            ['[TEST] Verleih Anfrage - Max Mustermann - Sa, 21.11.2026 von 09:30 - 11:00 Uhr', '[TEST] Deine Buchungsanfrage: Samstag, 21.11.2026, 09:30 – 11:00 Uhr'],
            array_column($this->sent, 'subject')
        );
        // Reply-To and the pasted details use the test address, not the sample visitor.
        self::assertContains('Reply-To: Max Mustermann <admin@example.test>', $this->sent[0]['headers']);
        self::assertStringContainsString('E-Mail: admin@example.test', $this->sent[0]['alt_body']);
        self::assertStringNotContainsString('max@example.com', implode("\n", [...array_column($this->sent, 'body'), ...array_column($this->sent, 'alt_body')]));
    }

    public function testTestSendStillSendsSecondMailWhenFirstFails(): void
    {
        $results = $this->mailer(false)->send_test($this->request(), $this->event(), 'admin@example.test');

        self::assertCount(2, $this->sent);
        self::assertSame(['transport down', null], array_column($results, 'error'));
    }

    public function testDeliverReturnsTransportError(): void
    {
        $mailer = $this->mailer(false);

        self::assertSame('transport down', $mailer->deliver(new Mail_Message('a@example.test', 's', 'b', [])));
        self::assertNull($mailer->deliver(new Mail_Message('a@example.test', 's', 'b', [])));
    }

    private function mailer(bool $notificationSucceeds): Booking_Mailer
    {
        $calls = 0;
        $send = function (Mail_Message $mail) use (&$calls, $notificationSucceeds): ?string {
            $this->sent[] = ['to' => $mail->to, 'subject' => $mail->subject, 'body' => $mail->body, 'alt_body' => $mail->alt_body, 'headers' => $mail->headers];

            return 1 !== ++$calls || $notificationSucceeds ? null : 'transport down';
        };

        return new Booking_Mailer($send, 'verleih@example.test', 'SC Test - Verleih', 'club@example.test');
    }

    private function request(): Booking_Request
    {
        return Booking_Request::from_array([
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'phone' => '+43 660 0000001',
            'participants' => '4',
            'rental' => '2',
            'message' => 'Hallo',
        ]);
    }

    private function event(): Event
    {
        return new Event('abc123', 'Freie Eiszeit', '', '2026-11-21T09:30:00+01:00', '2026-11-21T11:00:00+01:00', false);
    }
}
