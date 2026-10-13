<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking_Mailer;
use Hsc\GoogleCalendar\Booking_Request;
use Hsc\GoogleCalendar\Event;
use Hsc\GoogleCalendar\Mail_Message;
use PHPUnit\Framework\TestCase;

final class BookingMailerTest extends TestCase
{
    /** @var list<array{to: string, subject: string, body: string, alt_body: string, attachments: array<string, string>, headers: list<string>}> */
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
            "Verleih Anfrage\n\n-- Eintrag für Kalender --\nName: Max Mustermann\nE-Mail: max@example.com\nTelefon: +43 660 0000001\nDatum: Sa, 21.11.2026 von 09:30 - 11:00 Uhr\nPersonen: 4\nAusrüstungen: 2\n\n-- E-Mail: --\nDatum: \nSa, 21.11.2026 von 09:30 - 11:00 Uhr\nAnzahl: \n4 Personen / 2 Ausrüstungen\nAnsprechperson: \nMax Mustermann - +43 660 0000001\n\nNachricht:\nHallo",
            $club['alt_body']
        );

        self::assertSame('max@example.com', $visitor['to']);
        self::assertContains('From: SC Test - Verleih <verleih@example.test>', $visitor['headers']);
        self::assertStringNotContainsString('Reply-To', implode("\n", $visitor['headers']));
        self::assertStringContainsString('Hallo Max Mustermann,', $visitor['body']);
    }

    public function testNotificationCarriesFilledConfirmationDraft(): void
    {
        $template = tempnam(sys_get_temp_dir(), 'emltpl');
        file_put_contents($template, "X-Unsent: 1\nTo: {{email}}\nSubject: Bestätigung {{termin}}\n\n<p>Hallo {{name}}, {{teilnehmer}} / {{leihausruestung}} / {{unknown}}</p>");

        $messages = (new Booking_Mailer(static fn (): ?string => null, 'verleih@example.test', 'SC Test - Verleih', 'club@example.test', ['verleih-bestaetigung.eml' => $template]))
            ->build(Booking_Request::from_array([
                'name' => '<b>Max</b>',
                'email' => 'max@example.com',
                'phone' => '+43 660 0000001',
                'participants' => '4',
                'rental' => '2',
            ]), $this->event());
        unlink($template);

        $notification = $messages['notification'];
        self::assertSame(['verleih-bestaetigung.eml'], array_keys($notification->attachments));
        self::assertSame(
            "X-Unsent: 1\r\nTo: max@example.com\r\nSubject: =?UTF-8?B?" . base64_encode('Bestätigung SA, 21.11.2026 von 09:30 – 11:00 Uhr') . "?=\r\n\r\n<p>Hallo &lt;b&gt;Max&lt;/b&gt;, 4 / 2 / {{unknown}}</p>",
            $notification->attachments['verleih-bestaetigung.eml']
        );
        self::assertSame([], $messages['confirmation']->attachments);
    }

    public function testDraftFillsPhoneNumbers(): void
    {
        $template = tempnam(sys_get_temp_dir(), 'emltpl');
        file_put_contents($template, "To: {{email}}\n\n<a href=\"tel:{{telefon_verleih_link}}\">{{telefon_verleih}}</a>");

        $messages = (new Booking_Mailer(static fn (): ?string => null, 'verleih@example.test', 'SC Test - Verleih', 'club@example.test', ['verleih-bestaetigung.eml' => $template], '', ['telefon_verleih' => '+43 1234 <56>']))
            ->build($this->request(), $this->event());
        unlink($template);

        self::assertStringEndsWith('<a href="tel:+43123456">+43 1234 &lt;56&gt;</a>', $messages['notification']->attachments['verleih-bestaetigung.eml']);
    }

    public function testNotificationHasMailtoLinkWithCcButNoCcHeader(): void
    {
        $messages = (new Booking_Mailer(static fn (): ?string => null, 'verleih@example.test', 'SC Test - Verleih', 'club@example.test', [], 'cc@example.test'))
            ->build($this->request(), $this->event());

        $notification = $messages['notification'];
        self::assertStringContainsString('href="mailto:max%40example.com?subject=Verleih%20Best%C3%A4tigung%20-%20Max%20Mustermann%20-%20SA%2C%2021.11.2026%20von%2009%3A30', $notification->body);
        self::assertStringContainsString('&amp;cc=cc%40example.test&amp;body=Hallo%20Max%20Mustermann', $notification->body);
        self::assertStringNotContainsString('Cc', implode("\n", [...$notification->headers, ...$messages['confirmation']->headers]));
    }

    public function testCcOnlyInDraftAndDroppedWhenEmpty(): void
    {
        $template = tempnam(sys_get_temp_dir(), 'emltpl');
        file_put_contents($template, "From: \"{{name}}\" <{{email}}>\nTo: {{club_email}}\nCc: {{cc}}\nSubject: x\n\nbody");
        $build = fn (string $cc): string => (new Booking_Mailer(static fn (): ?string => null, 'verleih@example.test', 'SC Test - Verleih', 'club@example.test', ['verleih-bestaetigung.eml' => $template], $cc))
            ->build($this->request(), $this->event())['notification']->attachments['verleih-bestaetigung.eml'];

        self::assertSame("From: \"Max Mustermann\" <max@example.com>\r\nTo: club@example.test\r\nCc: cc@example.test\r\nSubject: x\r\n\r\nbody", $build('cc@example.test'));
        self::assertSame("From: \"Max Mustermann\" <max@example.com>\r\nTo: club@example.test\r\nSubject: x\r\n\r\nbody", $build(''));
        unlink($template);
    }

    public function testNoCcMailtoParameterWhenNotConfigured(): void
    {
        $this->mailer(true)->send($this->request(), $this->event());

        self::assertStringNotContainsString('&amp;cc=', $this->sent[0]['body']);
    }

    public function testClubMailPhoneNumberIsTelLink(): void
    {
        $this->mailer(true)->send($this->request(), $this->event());

        $body = $this->sent[0]['body'];
        self::assertStringContainsString('Telefon: <a href="tel:+436600000001" style="color:#000000;text-decoration:underline;">+43 660 0000001</a>', $body);
        self::assertStringContainsString('Max Mustermann - <a href="tel:+436600000001"', $body);
        self::assertStringNotContainsString('tel:', $this->sent[0]['alt_body']);
    }

    public function testDeclineDraftIsAttachedFromItsOwnTemplate(): void
    {
        $confirmation = tempnam(sys_get_temp_dir(), 'emltpl');
        $decline = tempnam(sys_get_temp_dir(), 'emltpl');
        file_put_contents($confirmation, "Subject: Bestätigung\n\nok");
        file_put_contents($decline, "Subject: Absage {{termin}}\n\nHallo {{name}}");

        $messages = (new Booking_Mailer(
            static fn (): ?string => null,
            'verleih@example.test',
            'SC Test - Verleih',
            'club@example.test',
            [Booking_Mailer::DRAFT_CONFIRMATION => $confirmation, Booking_Mailer::DRAFT_DECLINE => $decline, 'verleih-fehlt.eml' => '/does/not/exist']
        ))->build($this->request(), $this->event());
        unlink($confirmation);
        unlink($decline);

        $attachments = $messages['notification']->attachments;
        self::assertSame(['verleih-bestaetigung.eml', 'verleih-absage.eml'], array_keys($attachments));
        self::assertStringEndsWith("\r\n\r\nHallo Max Mustermann", $attachments['verleih-absage.eml']);
        self::assertStringContainsString('Subject: =?UTF-8?B?', $attachments['verleih-bestaetigung.eml']);
    }

    public function testNoAttachmentWithoutReadableTemplate(): void
    {
        $this->mailer(true)->send($this->request(), $this->event());

        self::assertSame([], $this->sent[0]['attachments']);
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
        self::assertSame($messages['notification']->attachments, $test->attachments);
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
        // Delivery goes to the test address only, the visitor address from the sample stays inside the content.
        self::assertContains('Reply-To: Max Mustermann <max@example.com>', $this->sent[0]['headers']);
        self::assertStringContainsString('E-Mail: max@example.com', $this->sent[0]['alt_body']);
        self::assertStringNotContainsString('admin@example.test', $this->sent[0]['alt_body']);
    }

    public function testTestSendUsesTestAddressWhenSampleHasNoEmail(): void
    {
        $sample = Booking_Request::from_array(['name' => 'Max Mustermann', 'phone' => '+43 660 0000001', 'participants' => '4', 'rental' => '2']);

        $this->mailer(true)->send_test($sample, $this->event(), 'admin@example.test', Booking_Mailer::SCOPE_CLUB);

        self::assertContains('Reply-To: Max Mustermann <admin@example.test>', $this->sent[0]['headers']);
        self::assertStringContainsString('E-Mail: admin@example.test', $this->sent[0]['alt_body']);
    }

    public function testTestSendScopeSelectsWhichMailsAreSent(): void
    {
        $club = $this->mailer(true)->send_test($this->request(), $this->event(), 'admin@example.test', Booking_Mailer::SCOPE_CLUB);
        self::assertSame([Booking_Mailer::LABEL_NOTIFICATION], array_column($club, 'label'));
        self::assertCount(1, $this->sent);

        $this->sent = [];
        $customer = $this->mailer(true)->send_test($this->request(), $this->event(), 'admin@example.test', Booking_Mailer::SCOPE_CUSTOMER);
        self::assertSame([Booking_Mailer::LABEL_CONFIRMATION], array_column($customer, 'label'));
        self::assertCount(1, $this->sent);
        self::assertStringStartsWith('[TEST] Deine Buchungsanfrage', $this->sent[0]['subject']);

        $this->sent = [];
        $both = $this->mailer(true)->send_test($this->request(), $this->event(), 'admin@example.test', 'unknown');
        self::assertCount(2, $both);
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
            $this->sent[] = ['to' => $mail->to, 'subject' => $mail->subject, 'body' => $mail->body, 'alt_body' => $mail->alt_body, 'attachments' => $mail->attachments, 'headers' => $mail->headers];

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
