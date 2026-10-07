<?php

declare(strict_types=1);

use Hsc\GoogleCalendar\Connection_Result;
use Hsc\GoogleCalendar\Connection_Tester;
use Hsc\GoogleCalendar\Credentials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConnectionTesterTest extends TestCase
{
    private const NOW = 1700000000;
    private const CALENDAR_ID = 'calendar-id@example.test';
    private const CLIENT_EMAIL = 'bot@example.test';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    private array $requests = [];

    private string $privateKey = '';
    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requests = [];

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privatePem);
        $this->privateKey = $privatePem;
        $this->publicKey = openssl_pkey_get_details($key)['key'];
    }

    public function testConnectsAndSignsJwtWithServiceAccountKey(): void
    {
        $tester = $this->tester([
            ['status' => 200, 'body' => '{"access_token":"token-1"}'],
            ['status' => 200, 'body' => '{"summary":"Ice time"}'],
        ]);

        $result = $tester->test(self::CALENDAR_ID, $this->credentialsJson(), self::NOW);

        self::assertTrue($result->ok);
        self::assertSame('Connected to calendar "Ice time".', $result->message);
        self::assertSame(self::NOW, $result->checked);

        parse_str($this->requests[0]['body'], $form);
        [$header, $claims, $signature] = explode('.', $form['assertion']);
        self::assertSame(1, openssl_verify($header . '.' . $claims, $this->decodeBase64Url($signature), $this->publicKey, OPENSSL_ALGO_SHA256));
        $decoded = json_decode($this->decodeBase64Url($claims), true);
        self::assertSame(self::CLIENT_EMAIL, $decoded['iss']);
        self::assertSame(self::NOW + 3600, $decoded['exp']);

        self::assertSame('Bearer token-1', $this->requests[1]['headers']['Authorization']);
        self::assertStringEndsWith('/calendars/calendar-id%40example.test', $this->requests[1]['url']);
    }

    /**
     * @param list<array{status: int, body: string}> $responses
     */
    #[DataProvider('failureProvider')]
    public function testReportsReadableError(string $calendarId, string $json, array $responses, string $expected): void
    {
        $result = $this->tester($responses)->test($calendarId, $json ?: $this->credentialsJson(), self::NOW);

        self::assertFalse($result->ok);
        self::assertStringContainsString($expected, $result->message);
    }

    /**
     * @return array<string, array{string, string, list<array{status: int, body: string}>, string}>
     */
    public static function failureProvider(): array
    {
        return [
            'missing calendar id' => ['', '', [], 'No calendar ID'],
            'invalid json' => [self::CALENDAR_ID, '{nope', [], 'not valid JSON'],
            'json without private key' => [self::CALENDAR_ID, '{"client_email":"a@example.test"}', [], 'no "private_key"'],
            'token rejected' => [self::CALENDAR_ID, '', [['status' => 400, 'body' => '{"error":"invalid_grant","error_description":"Invalid JWT Signature."}']], 'invalid_grant: Invalid JWT Signature.'],
            'calendar not shared' => [self::CALENDAR_ID, '', [['status' => 200, 'body' => '{"access_token":"t"}'], ['status' => 404, 'body' => '{"error":{"message":"Not Found"}}']], 'share the calendar with bot@example.test'],
            'api disabled' => [self::CALENDAR_ID, '', [['status' => 200, 'body' => '{"access_token":"t"}'], ['status' => 403, 'body' => '{"error":{"message":"API disabled"}}']], 'API disabled (HTTP 403)'],
        ];
    }

    public function testInvalidPrivateKeyIsReported(): void
    {
        $json = (string) json_encode(['client_email' => self::CLIENT_EMAIL, 'private_key' => 'not a key']);

        $result = $this->tester([])->test(self::CALENDAR_ID, $json, self::NOW);

        self::assertFalse($result->ok);
        self::assertStringContainsString('private key', $result->message);
    }

    public function testTransportErrorIsReported(): void
    {
        $tester = new Connection_Tester(static function (): array {
            throw new RuntimeException('cURL error 6: Could not resolve host');
        });

        $result = $tester->test(self::CALENDAR_ID, $this->credentialsJson(), self::NOW);

        self::assertFalse($result->ok);
        self::assertStringContainsString('Could not resolve host', $result->message);
    }

    public function testCredentialsRejectWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Credentials::from_json('{"type":"authorized_user","client_email":"a","private_key":"b"}');
    }

    public function testResultRoundTripsThroughStorage(): void
    {
        $result = Connection_Result::failure('boom', self::NOW);

        $restored = Connection_Result::from_stored($result->to_array());

        self::assertEquals($result, $restored);
        self::assertNull(Connection_Result::from_stored('garbage'));
        self::assertNull(Connection_Result::from_stored(['ok' => true]));
    }

    private function credentialsJson(): string
    {
        return (string) json_encode(['type' => 'service_account', 'client_email' => self::CLIENT_EMAIL, 'private_key' => $this->privateKey]);
    }

    /**
     * @param list<array{status: int, body: string}> $responses Queued responses, one per request.
     */
    private function tester(array $responses): Connection_Tester
    {
        return new Connection_Tester(function (string $method, string $url, array $headers, string $body) use (&$responses): array {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
            $next = array_shift($responses);
            self::assertNotNull($next, 'Unexpected extra HTTP request');

            return $next;
        });
    }

    private function decodeBase64Url(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
