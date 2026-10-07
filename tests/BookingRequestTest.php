<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking_Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingRequestTest extends TestCase
{
    public function testValidRequestHasNoErrors(): void
    {
        self::assertSame([], $this->request()->errors());
    }

    /**
     * @param array<string, mixed>  $override Fields replacing the valid defaults.
     * @param array<string, string> $expected Field => error code.
     */
    #[DataProvider('invalidProvider')]
    public function testReportsErrors(array $override, array $expected): void
    {
        self::assertSame($expected, $this->request($override)->errors());
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function invalidProvider(): array
    {
        return [
            'name missing' => [['name' => '  '], ['name' => 'name_required']],
            'name too long' => [['name' => str_repeat('a', 101)], ['name' => 'name_required']],
            'email missing' => [['email' => ''], ['email' => 'email_required']],
            'email without tld' => [['email' => 'max@example'], ['email' => 'email_invalid']],
            'email with header injection' => [['email' => "max@example.com\nBcc: evil@example.com"], ['email' => 'email_invalid']],
            'phone letters' => [['phone' => 'abc'], ['phone' => 'phone_invalid']],
            'phone missing' => [['phone' => ''], ['phone' => 'phone_invalid']],
            'zero participants' => [['participants' => '0'], ['participants' => 'participants_range']],
            'too many participants' => [['participants' => '31'], ['participants' => 'participants_range']],
            'participants not a number' => [['participants' => 'two'], ['participants' => 'participants_range']],
            'participants decimal' => [['participants' => '1.5'], ['participants' => 'participants_range']],
            'negative rental' => [['rental' => '-1'], ['rental' => 'rental_range']],
            'rental more than participants' => [['participants' => '2', 'rental' => '3'], ['rental' => 'rental_exceeds']],
            'array instead of string' => [['name' => ['x']], ['name' => 'name_required']],
        ];
    }

    public function testAcceptsPhoneFormats(): void
    {
        foreach (['+43 660 0000000', '05572/12345', '0043 660-000'] as $phone) {
            self::assertArrayNotHasKey('phone', $this->request(['phone' => $phone])->errors(), $phone);
        }
    }

    public function testHoneypotMarksSpam(): void
    {
        self::assertFalse($this->request()->is_spam());
        self::assertTrue($this->request(['website' => 'http://spam.example'])->is_spam());
    }

    public function testNormalisesWhitespaceAndKeepsMessageLineBreaks(): void
    {
        $request = $this->request(['name' => "  Max \n  Mustermann ", 'message' => "Zeile 1\nZeile 2"]);

        self::assertSame('Max Mustermann', $request->name);
        self::assertSame("Zeile 1\nZeile 2", $request->message);
    }

    public function testMessageIsTruncated(): void
    {
        $request = $this->request(['message' => str_repeat('ä', 2500)]);

        self::assertSame(Booking_Request::MAX_MESSAGE, mb_strlen($request->message));
    }

    /**
     * @param array<string, mixed> $override Fields replacing the valid defaults.
     */
    private function request(array $override = []): Booking_Request
    {
        return Booking_Request::from_array($override + [
            'event' => 'abc123',
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'phone' => '+43 660 0000001',
            'participants' => '4',
            'rental' => '2',
            'message' => '',
            'website' => '',
        ]);
    }
}
